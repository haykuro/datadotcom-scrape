<?php

class State extends Eloquent
{
	public function cities()
	{
		return $this->has_many_and_belongs_to('City', 'state_cities');
	}

	public function create_or_find($txt)
	{
		$state = State::where_name($txt)->first();
		if(!is_object($state))
		{
			$state = new State;
			$state->name = $txt;
			$state->save();
		}

		return $state;
	}
}