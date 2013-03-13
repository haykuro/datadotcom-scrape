<?php

class Country extends Eloquent
{
	public function states()
	{
		return $this->has_many_and_belongs_to('State', 'country_states');
	}

	public function create_or_find($txt)
	{
		$country = Country::where_name($txt)->first();
		if(!is_object($country))
		{
			$country = new Country;
			$country->name = $txt;
			$country->save();
		}

		return $country;
	}
}