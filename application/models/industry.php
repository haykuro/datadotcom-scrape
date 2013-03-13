<?php

class Industry extends Eloquent
{
	public static $timestamps = false;

	public function create_or_find($txt)
	{
		$industry = Industry::where_name($txt)->first();
		if(!is_object($industry))
		{
			$industry = new Industry;
			$industry->name = $txt;
			$industry->save();
		}

		return $industry;
	}
}