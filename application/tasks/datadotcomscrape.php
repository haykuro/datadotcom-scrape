<?php

class DataDotComScrape_Task
{
	private static $scrape_limit;

	private static function JSONDB($biz_data)
	{
		$data_string = json_encode($biz_data);

		// echo "\n.... SENDING ".strlen($data_string)." BYTES: '{$data_string}'\n";

		$ch = curl_init('http://storage.tnapi.com/jigsaw');
		curl_setopt($ch, CURLOPT_HTTPAUTH, CURLAUTH_BASIC) ;
		curl_setopt($ch, CURLOPT_USERPWD, "tnapi-storage:48usTe0NbQRH7kTx0B65986bCPfM4uR8rDxQtsDY");
		curl_setopt($ch, CURLOPT_CUSTOMREQUEST, "POST");
		curl_setopt($ch, CURLOPT_POSTFIELDS, $data_string);
		curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
		// curl_setopt($ch, CURLOPT_VERBOSE, 1);
		curl_setopt($ch, CURLOPT_HTTPHEADER, array(
		    'Content-Type: application/json',
		    'Content-Length: ' . strlen($data_string))
		);

		$result = curl_exec($ch);
		curl_close($ch);

		return $result;
	}

	private static function mySQL($biz_data)
	{
		// does the business already exist?
		$business = Business::create_or_find(array(
			'name' => $biz_data['name'],
			'jigsaw_id' => $biz_data['jigsaw_id']
		));

		// update the business information.
		$business->contacts_available = $biz_data['contacts_available'];
		$business->name = $biz_data['name'];
		$business->website = $biz_data['website'];
		$business->phone = $biz_data['phone'];
		$business->employees = $biz_data['employees'];
		$business->revenue = $biz_data['revenue'];
		$business->ownership = $biz_data['ownership'];
		$business->updated_at = date('Y-m-d H:i:s');
		// save the business.
		$business->save();

		// Business Industries
		$industry_ids = array();
		foreach($biz_data['industries'] as $txt)
		{
			$industry = Industry::create_or_find($txt);
			$industry_ids[] = $industry->id;
		}

		// populate.
		echo $business->id;
		echo $biz_data['jigsaw_id'];
		$business->industries()->sync($industry_ids);
		die('stop');

		// address information

		// get/create city
		$city = City::create_or_find($biz_data['headquarters']['city']);

		// get/create state
		$state = State::create_or_find($biz_data['headquarters']['state']);

		// associate city to state
		$state->cities()->attach($city->id);

		// create country
		$country = Country::create_or_find($biz_data['headquarters']['country']);

		// associate state to country
		$country->states()->attach($state->id);

		// Headquarters
		$headquarter = Headquarter::where_address_1($biz_data['headquarters']['address_1'])
						->where_business_id($business->id)
						->where_zip($biz_data['headquarters']['zip'])->first();
		if(!is_object($headquarter))
		{
			// not found, insert it.
			$headquarter = new Headquarter;
			$headquarter->business_id = $business->id;
			$headquarter->address_1 = $biz_data['headquarters']['address_1'];
		}

		$headquarter->city_id = $city->id;
		$headquarter->state_id = $state->id;
		$headquarter->zip = $biz_data['headquarters']['zip'];
		$headquarter->country_id = $country->id;
		$headquarter->updated_at = date('Y-m-d H:i:s');
		$headquarter->save();
	}

	public static function run($args)
	{
		$mysql = false;
		$began_scraping = time();
		$began_scraping_text = '[.] Began Scraping at '.date('Y-m-d H:i:s', $began_scraping)."\n";
		Log::write('DataDotCom_Scraper', $began_scraping_text);
		echo $began_scraping_text;
		unset($began_scraping_text);

		$continue_from = ( (is_numeric($args[0]) && (int)$args[0] > 0) ? (int)$args[0] : 1);
		self::$scrape_limit = ( (is_numeric($args[1]) && (int)$args[1] > 0) ? (int)$args[1] : 100);

		for($i = $continue_from; $i < (self::$scrape_limit + $continue_from); $i++)
		{
			Log::write('DataDotCom_Scraper', '[.] Scraping biz_id: '.$i."\n");
			$biz_data = DataDotCom::scrapeBusiness(array(
				'biz_id' => $i
			));

			if($biz_data === false)
			{
				Log::write('DataDotCom_Scraper', '[!] 404 on Biz_Id: '.$i."\n");
				continue;
			}

			if($mysql==true)
			{
				self::mySQL($biz_data);
			}
			else
			{
				// post to DB via JSON (thank you Randell.)
				if(strlen(self::JSONDB($biz_data)) > 0)
				{
					Log::write('DataDotCom_Scraper', '[!] Failed to CURL POST Biz_Id: '.$i."\n");
				}
			}
		}

		$finished_at = time();
		$finished_text = '[.] Finished Scraping at '.date('Y-m-d H:i:s', $finished_at).' ('.($finished_at-$began_scraping).' seconds)'."\n";
		Log::write('DataDotCom_Scraper', $finished_text);
		echo $finished_text;

		return true;
	}
}