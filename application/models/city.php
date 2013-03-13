<?php

class City extends Eloquent
{
	public function state()
	{
		return $this->has_many_and_belongs_to('State', 'state_cities');
	}

	public function create_or_find($txt)
	{
		$city = City::where_name($txt)->first();
		if(!is_object($city))
		{
			$city = new City;
			$city->name = $txt;
			$city->save();
		}

		return $city;
	}
}