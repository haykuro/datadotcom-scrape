<?php

class Scrape_Businesses_Task
{
	private static $scrape_limit;

	public static function run($args)
	{
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
			$business->industries()->sync($industry_ids);

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
	}
}