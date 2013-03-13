<?php

class Create_Biz_Table {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('businesses', function($table){
			$table->increments('id');
			$table->integer('jigsaw_id');
			$table->integer('contacts_available');
			$table->string('name');
			$table->string('website');
			$table->string('phone');
			$table->string('employees');
			$table->string('revenue');
			$table->string('ownership');
			$table->timestamps();
		});

		Schema::create('cities', function($table){
			$table->increments('id');
			$table->string('name');
			$table->timestamps();
		});

		Schema::create('states', function($table){
			$table->increments('id');
			$table->string('name');
			$table->timestamps();
		});

		Schema::create('state_cities', function($table){
			$table->increments('id');
			$table->integer('state_id');
			$table->integer('city_id');
			$table->timestamps();
		});

		Schema::create('countries', function($table){
			$table->increments('id');
			$table->string('name');
			$table->timestamps();
		});

		Schema::create('country_states', function($table){
			$table->increments('id');
			$table->integer('country_id');
			$table->integer('state_id');
			$table->timestamps();
		});

		Schema::create('business_headquarters', function($table){
			$table->increments('id');
			$table->integer('business_id');
			$table->string('address_1');
			$table->integer('city_id');
			$table->integer('state_id');
			$table->string('zip');
			$table->integer('country_id');
			$table->timestamps();
		});

		Schema::create('industries', function($table){
			$table->increments('id');
			$table->string('name');
		});

		Schema::create('business_industries', function($table){
			$table->increments('id');
			$table->integer('business_id');
			$table->integer('industry_id');
			$table->timestamps();
		});
	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		$array_of_tables_to_drop = array(
			'businesses', 'cities', 'states', 'state_cities', 'countries',
			'country_states', 'business_headquarters', 'industries', 'business_industries'
		);
		foreach($array_of_tables_to_drop as $table_name)
		{
			Schema::drop($table_name);
		}
	}

}