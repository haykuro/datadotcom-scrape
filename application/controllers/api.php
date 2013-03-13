<?php

class API_Controller extends Base_Controller {
	public $restful = true;

	public function get_business($id)
	{
		return Response::json(DataDotCom::scrapeBusiness(array(
			'biz_id' => $id
		)));
	}

}