<?php

class DataDotCom
{
	private static $scrapeURL = 'https://connect.data.com/company/view/';
	private static $blacklist_characters = array(
		'blacklist' => array(
			"\xc2\xa0",
			"\x0d\x0a"
		),
		'replacement' => array(
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
		curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
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

		return preg_replace('/\s{2,}/is', ' ', trim(str_replace(self::$blacklist_characters['blacklist'], self::$blacklist_characters['replacement'], $str)));
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

		$cache_name = 'Scraper.DataDotCom.scrapeBusiness-'.sha1(json_encode($args)).($second_try ? '-try2' : '');
		if(!Cache::has($cache_name))
		{
			$biz_id = (isset($args['biz_id']) ? $args['biz_id'] : false);
			if($biz_id === false)
			{
				throw new Exception('biz_id is required');
				return false;
			}

			$content = self::_curl(array(
				'url' => self::$scrapeURL.$biz_id
			));

			if($content)
			{
				if(preg_match('/Sorry, an error occurred. We are notified and will fix it./is', $content))
				{
					if($second_try === false)
					{
						// Log::write('Scraper.DataDotCom.scrapeBusiness', 'trying again...');
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
					// it should be good, let's scrape.
					$biz = array(
						'type' => 'datadotcom_biz',
						'jigsaw_url' => self::$scrapeURL.$biz_id
					);

					// load up our DOMDocument object.
					$DOM = new DOMDocument;
					@$DOM->loadHTML($content);

					$biz['jigsaw_id'] = (int)$biz_id;

					// determine the # of contacts available
					preg_match('/(\d+) Contact[s]? at this company/is', $content, $matches);
					$biz['contacts_available'] = (isset($matches[1]) ? (int)$matches[1] : 0);

					// find and locate all the tables with relevant information
					$tables = $DOM->getElementsByTagName('table');
					$biz_info_table = $tables->item(0);
					/*
					$level_table = $tables->item(1);
					$dept_table = $tables->item(2);
					*/

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
									$foo = self::clean_str(array('str'=>$matches[$i]));
									if(strlen($foo) > 0)
									{
										$industries[] = $foo;
									}
								}
							}
							else if(strlen($res) > 0)
							{
								$industries[] = $res;
							}
						}

						$biz['industries'] = $industries;
					}

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
}