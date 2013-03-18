<?php

class API_Controller extends Base_Controller {
	public $restful = true;

	public function get_four11dotinfo_business($id)
	{
		return Response::json(Four11DotInfo::scrapeBusiness(array(
			'biz_id' => $id
		)));
	}

	public function get_four11dotinfo_person($id)
	{
		return Response::json(Four11DotInfo::scrapePerson(array(
			'person_id' => $id
		)));
	}

	public function get_datadotcom_business($id)
	{
		return Response::json(DataDotCom::scrapeBusiness(array(
			'biz_id' => $id
		)));
	}

}