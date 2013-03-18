<?php

class Business extends Eloquent
{
	public function create_or_find($args)
	{
		if(!isset($args['name']) || !isset($args['jigsaw_id']))
		{
			throw new Exception('Missing a paramater. (requires \'name\' and \'jigsaw_id\')');
			return false;
		}
		$business = Business::where_name($args['name'])->where_jigsaw_id($args['jigsaw_id'])->first();
		if(!is_object($business))
		{
			// business doesn't exist, create it.
			$business = new Business;
			$business->name = $args['name'];
			$business->jigsaw_id = $args['jigsaw_id'];
			$business->created_at = date('Y-m-d H:i:s');
			$business->save();
		}

		return $business;
	}
	public function industries()
	{
		return $this->has_many_and_belongs_to('Industry', 'business_industries');
	}

	public function headquarter()
	{
		return $this->has_one('Headquarter');
	}
}