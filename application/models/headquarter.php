<?php

class Headquarter extends Eloquent
{
	public static $table = 'business_headquarters';

	public function business()
	{
		return $this->belongs_to('Business');
	}
}