<?php

class DataDotCom
{
	private static $scrapeURL = 'https://connect.data.com/company/view/';

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
		// curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
		$content = curl_exec($ch);
		curl_close($ch);

		return $content;
	}

	/**
	 * Scrapes a business page, returns either an array, object, or JSON string.
	 *
	 * @param  array
	 * @return mixed
	 * @author Steve Birstok
	 **/
	public static function scrapeBusiness($args)
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
				return false;
			}
			else
			{
				// it should be good, let's scrape.
				$biz = array(
					'jigsaw_url' => self::$scrapeURL.$biz_id
				);

				// load up our DOMDocument object.
				$DOM = new DOMDocument;
				@$DOM->loadHTML($content);

				$biz['jigsaw_id'] = $biz_id;

				// determine the # of contacts available
				preg_match('/(\d+) Contact[s]? at this company/is', $content, $matches);
				$biz['contacts_available'] = (isset($matches[1]) ? $matches[1] : 0);

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
							'address_1' => trim($matches[1]),
							'city' => trim($city_state_zip[1]),
							'state' => trim($city_state_zip[2]),
							'zip' => trim($city_state_zip[3]),
							'country' => str_replace("\xc2\xa0", "", trim($matches[3]))
						);
					}
					else
					{
						$biz[$key] = trim($tds->item(1)->nodeValue);
					}
				}

				if(isset($biz['industries']))
				{
					$biz['industries'] = explode(', ', $biz['industries']);
				}

				return $biz;
			}
		}

		return false;
	}
}