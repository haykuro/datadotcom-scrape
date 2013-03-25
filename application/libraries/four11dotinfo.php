<?php

class Four11DotInfo
{
	private static $business_scrapeURL = 'http://411.info/business/';
	private static $person_scrapeURL = 'http://411.info/people/';

	private static $blacklist_characters = array(
		'blacklist' => array(
			"\xc2\xa0",
			"\x0d\x0a",
			"\xc3\x82\xc2",
			"\xc3\xa2\xc2\x80\xc2\x99",
			"\xc2\xad"
		),
		'replacement' => array(
			"",
			"",
			"",
			"",
			""
		)
	);

	private static function _curl($args)
	{
		if(!is_array($args))
		{
			throw new Exception('$args should be an array.');
			return false;
		}

		$url = (isset($args['url']) ? $args['url'] : false);
		if($url === false)
		{
			throw new Exception('expected URL');
			return false;
		}

		$ch = curl_init();
		curl_setopt($ch, CURLOPT_URL, $url);
		curl_setopt($ch, CURLOPT_HEADER, false);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

		$agents = array(
			'Mozilla/5.0 (Macintosh; Intel Mac OS X 10.7; rv:7.0.1) Gecko/20100101 Firefox/7.0.1',
			'Mozilla/5.0 (X11; U; Linux i686; en-US; rv:1.9.1.9) Gecko/20100508 SeaMonkey/2.0.4',
			'Mozilla/5.0 (Windows; U; MSIE 7.0; Windows NT 6.0; en-US)',
			'Mozilla/5.0 (Macintosh; U; Intel Mac OS X 10_6_7; da-dk) AppleWebKit/533.21.1 (KHTML, like Gecko) Version/5.0.5 Safari/533.21.1'
		);
		curl_setopt($ch, CURLOPT_USERAGENT, $agents[array_rand($agents)]);
		// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$content = curl_exec($ch);
		curl_close($ch);

		return $content;
	}

	private static function clean_str($args)
	{
		$str = (isset($args['str']) ? $args['str'] : false);
		if(is_string($str) && strlen($str) < 1)
		{
			return '';
		}
		else if(!$str)
		{
			throw new Exception("expected paramter 'str'");
			return false;
		}

		$strip_tags = (isset($args['strip_tags']) ? $args['strip_tags'] : false);
		if($strip_tags == true)
		{
			$str = strip_tags($str);
		}

		return utf8_decode(preg_replace('/\s{2,}/is', ' ', trim(str_replace(self::$blacklist_characters['blacklist'], self::$blacklist_characters['replacement'], $str))));
	}

	private static function getElementsByClassName($Elements, $ClassName)
	{
		$Matched = array();

		try
		{
		    foreach($Elements as $node)
		    {
		        if( ! $node -> hasAttributes())
		            continue;

		        $classAttribute = $node -> attributes -> getNamedItem('class');

		        if( ! $classAttribute)
		            continue;

		        $classes = explode(' ', $classAttribute -> nodeValue);

		        if(in_array($ClassName, $classes))
		            $Matched[] = $node;
		    }
		}
		catch(Exception $e)
		{
			throw new Exception($e->getMessage());
		}

	    return $Matched;
	}

	/**
	 * Scrapes a business page, returns either an array, object, or JSON string.
	 *
	 * @param  array
	 * @return mixed
	 * @author Steve Birstok
	 **/
	public static function scrapeBusiness($args, $second_try=false)
	{
		$return_value = false;

		$cache_name = 'Scraper.411Info.scrapeBusiness-'.sha1(json_encode($args)).($second_try ? '-try2' : '');
		if(!Cache::has($cache_name))
		{
			$biz_id = (isset($args['biz_id']) ? $args['biz_id'] : false);
			if($biz_id === false)
			{
				throw new Exception('biz_id is required');
				return false;
			}

			$biz = array(
				'type' => '411_biz',
				'id_411'  => $biz_id,
				'url_411' => self::$business_scrapeURL.$biz_id
			);

			$content = self::_curl(array(
				'url' => $biz['url_411']
			));

			if($content)
			{
				// check if page exists
				if(preg_match('/Page you reqested not found.../is', $content))
				{
					if($second_try === false)
					{
						// Log::write('Scraper.411Info.scrapeBusiness', 'trying again...');
						# Sleep for 3 seconds, this will stop any erroneous connection problems.
						sleep(3);
						$biz = self::scrapeBusiness($args, true);
						Cache::put($cache_name, $biz, 5);
						return $biz;
					}
					else
					{
						return false;
					}
				}
				else
				{
					// pull business name
					preg_match('/<h3 class="bname" itemprop="name">(.*)?<\/h3>/i', $content, $match);
					$biz['name'] = (isset($match[1]) ? $match[1] : false);

									// scrape 'spoof' url to hide the goodies!
					preg_match('/<a href="\/directory\/(.*)?\/(.*?)\/(.*)?\/" itemprop="url"><span itemprop="title">/', $content, $match);
					$biz['url_411'] = self::$business_scrapeURL."{$match[1]}/{$match[2]}/".strtoupper(Str::slug($biz['name']))."/{$biz['id_411']}.html";

					// pull phone number
					preg_match('/<span class="value" itemprop="telephone">(.*)?<\/span>/i', $content, $match);
					$biz['phone'] = (isset($match[1]) ? $match[1] : false);

					// load up our DOMDocument object.
					$DOM = new DOMDocument;
					@$DOM->loadHTML($content);

					// get address (thank you 411.info for providing a nice #id tag with proper formatting for this :) )
					$biz['location'] = array();
					$loc = explode(', ', $DOM->getElementById('dir_end')->getAttribute('value'));
					for($i = 0; $i < count($loc); $i++)
					{
						if($i == 0)
						{
							$biz['location']['address_1'] = $loc[$i];
						}
						else if($i == 1)
						{
							$biz['location']['city'] = $loc[$i];
						}
						else if($i == 2)
						{
							$biz['location']['state'] = $loc[$i];
						}
						else if($i >= 3)
						{
							$biz['location']['state'] .= ', '.$loc[$i];
						}
					}

					// pull postal code
					preg_match('/<span class="postal-code profile_address" itemprop="postalCode">(.*)?<\/span>/i', $content, $match);
					$biz['location']['zip'] = (isset($match[1]) ? $match[1] : false);

					// pull extra information <legend>(.*)</legend><div>(.*)>/div>
					// cycle through all the divs from top to bottom
					// find the business profile one by class.
					$left_profile_div = self::getElementsByClassName($DOM->getElementsByTagName('div'), 'bprofile_left');
					if(!is_array($left_profile_div))
					{
						throw new Exception('unexpected error.');
						return false;
					}
					$left_profile_div = $left_profile_div[0];
					$all_tags = $left_profile_div->getElementsByTagName('*');
					$extra_info = array();
					$ii = 0;
					$legend = false;
					$biz['categories'] = array();
					for($i = 0; $i < $all_tags->length; $i++)
					{
						if(strtolower($all_tags->item($i)->tagName) == 'legend' && !preg_match('/Rate this company/is', $all_tags->item($i)->nodeValue))
						{
							$legend = $i;
						}
						else if(strtolower($all_tags->item($i)->tagName) == 'div' && $legend !== false)
						{
							$legend = trim($all_tags->item($legend)->nodeValue, ':');
							if(strtolower($legend) == 'categories')
							{
								$categories_el = $all_tags->item($i);
								foreach($categories_el->getElementsByTagName('a') as $a)
								{
									$biz['categories'][] = self::clean_str(array('str'=>$a->nodeValue));
									/*$biz['categories'][] = array(
										'url' => 'http://411.info'.$a->getAttribute('href'),
										'name' => self::clean_str(array('str'=>$a->nodeValue))
									);*/
								}
								unset($categories_el);
							}
							else if(strtolower($legend) == 'services')
							{
								$biz['services'] = array_map(function($m) { return trim($m, 'and '); }, explode(', ', $all_tags->item($i)->nodeValue));
							}
							else
							{
								$extra_info[] = array('legend'=>$legend, 'div'=>$DOM->saveHTML($all_tags->item($i)));
							}
							$legend = false;
						}
					}

					$biz['extra_info'] = implode("<hr />\n", array_map(function($m) {
						return '<legend>'.$m['legend'].'</legend>'.$m['div'];
					}, $extra_info));
					unset($left_profile_div);
					unset($all_tags);

					/*
					die(Response::json($biz));
					echo $content;
					die;

					// determine the # of contacts available
					preg_match('/(\d+) Contact[s]? at this company/is', $content, $matches);
					$biz['contacts_available'] = (isset($matches[1]) ? $matches[1] : 0);

					// find and locate all the tables with relevant information
					$tables = $DOM->getElementsByTagName('table');
					$biz_info_table = $tables->item(0);

					// Extract Data from Biz Info Table
					$total_rows = $biz_info_table->getElementsByTagName('tr')->length;
					for($i = 0; $i < $total_rows; $i++)
					{
						$tds = $biz_info_table->getElementsByTagName('tr')->item($i)->getElementsByTagName('td');
						$key = str_replace(' ', '_', strtolower(trim($tds->item(0)->nodeValue)));
						if($key == 'headquarters')
						{
							preg_match('/<td .*?>\s+(.*)?<br>\s+(.*)?<br>\s+(.*)?\s+.*?<a.*?<\/td>/is', $DOM->saveHTML($tds->item(1)), $matches);
							preg_match('/(.*)?,\s+(.*)?[\s]+(.*)?/is', $matches[2], $city_state_zip);
							$biz['headquarters'] = array(
								'address_1' => self::clean_str(array('str'=>$matches[1], 'strip_tags'=>true)),
								'city' => self::clean_str(array('str'=>$city_state_zip[1])),
								'state' => self::clean_str(array('str'=>$city_state_zip[2])),
								'zip' => self::clean_str(array('str'=>$city_state_zip[3])),
								'country' => self::clean_str(array('str'=>$matches[3], 'strip_tags'=>true))
							);
						}
						else
						{
							$biz[$key] = self::clean_str(array('str'=>($tds->item(1)->nodeValue)));
						}
					}

					if(isset($biz['industries']))
					{
						$industries = array();
						$list = explode(', ', $biz['industries']);
						foreach($list as $industry)
						{
							$res = self::clean_str(array('str'=>$industry));
							preg_match('/(.*)?\s{2,}+(.*)?/is', $res, $matches);
							if(count($matches) > 0)
							{
								for($i = 1; $i < count($matches); $i++)
								{
									$industries[] = self::clean_str(array('str'=>$matches[$i]));
								}
							}
							else
							{
								$industries[] = $res;
							}
						}

						$biz['industries'] = $industries;
					}*/

					Cache::put($cache_name, $biz, 5);
				}
			}

			return false;
		}
		else
		{
			$return_value = Cache::get($cache_name);
		}

		return $return_value;
	}

	/**
	 * Scrapes a business page, returns either an array, object, or JSON string.
	 *
	 * @param  array
	 * @return mixed
	 * @author Steve Birstok
	**/
	public static function scrapePerson($args, $second_try=false)
	{
		$return_value = false;

		$cache_name = 'Scraper.411Info.scrapePerson-'.sha1(json_encode($args)).($second_try ? '-try2' : '');
		if(!Cache::has($cache_name))
		{
			$person_id = (isset($args['person_id']) ? $args['person_id'] : false);
			if($person_id === false)
			{
				throw new Exception('person_id is required');
				return false;
			}

			$person = array(
				'type' => '411_person',
				'id_411'  => $person_id,
				'url_411' => self::$person_scrapeURL.$person_id,
			);

			$content = self::_curl(array(
				'url' => $person['url_411']
			));

			if($content)
			{
				// check if page exists
				if(preg_match('/No results found for your request... Please, try again.../is', $content))
				{
					if($second_try === false)
					{
						// Log::write('Scraper.411Info.scrapePerson', 'trying again...');
						# Sleep for 3 seconds, this will stop any erroneous connection problems.
						sleep(3);
						$biz = self::scrapePerson($args, true);
						Cache::put($cache_name, $biz, 5);
						return $biz;
					}
					else
					{
						return false;
					}
				}
				else
				{
					// pull person given-name
					preg_match('/<span class="given-name">(.*)?<\/span>/i', $content, $match);
					$person['given_name'] = (isset($match[1]) ? self::clean_str(array('str'=>$match[1])) : false);

					// pull person family-name
					preg_match('/<span class="family-name">(.*)?<\/span>/i', $content, $match);
					$person['family_name'] = (isset($match[1]) ? self::clean_str(array('str'=>$match[1])) : false);

					// combine them.
					$person['full_name'] = implode(', ', array($person['family_name'], $person['given_name']));

					// scrape 'spoof' url to hide the goodies!
					preg_match('/<a href="\/people-directory\/(.*)?\/(.*?)\/(.*)?\/" itemprop="url"><span itemprop="title">/', $content, $match);
					$person['url_411'] = self::$person_scrapeURL."{$match[1]}/{$match[2]}/".ucwords($person['family_name']).'-'.ucwords($person['given_name'])."/{$person['id_411']}.html";

					// pull phone number
					preg_match('/<span class="value" itemprop="telephone">(.*)?<\/span>/i', $content, $match);
					$person['phone'] = (isset($match[1]) ? self::clean_str(array('str'=>$match[1])) : false);

					// load up our DOMDocument object.
					$DOM = new DOMDocument;
					@$DOM->loadHTML($content);

					// get address (thank you 411.info for providing a nice #id tag with proper formatting for this :) )
					$person['location'] = array();
					$loc = explode(', ', $DOM->getElementById('dir_end')->getAttribute('value'));
					for($i = 0; $i < count($loc); $i++)
					{
						if($i == 0)
						{
							$person['location']['address_1'] = self::clean_str(array('str'=>$loc[$i]));
						}
						else if($i == 1)
						{
							$person['location']['city'] = self::clean_str(array('str'=>$loc[$i]));
						}
						else if($i == 2)
						{
							$person['location']['state'] = self::clean_str(array('str'=>$loc[$i]));
						}
						else if($i == 3)
						{
							$person['location']['zip'] = self::clean_str(array('str'=>$loc[$i]));
						}
						else if($i = 4)
						{
							$person['location']['extra'] = self::clean_str(array('str'=>$loc[$i]));
						}
						else if($i >= 4)
						{
							$person['location']['extra'] .= ', '.self::clean_str(array('str'=>$loc[$i]));
						}
					}

					/*
					// pull postal code
					preg_match('/<span class="postal-code profile_address" itemprop="postalCode">(.*)?<\/span>/i', $content, $match);
					$biz['location']['zip'] = (isset($match[1]) ? $match[1] : false);
					*/

					Cache::put($cache_name, $person, 5);
				}
			}

			return false;
		}
		else
		{
			$return_value = Cache::get($cache_name);
		}

		return $return_value;
	}
}