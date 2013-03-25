<?php

class Create_Cache {

	/**
	 * Make changes to the database.
	 *
	 * @return void
	 */
	public function up()
	{
		Schema::create('laravel_cache', function($table){
			$table->increments('id');
			$table->string('key');
			$table->text('value');
			$table->integer('expiration');
		});
	}

	/**
	 * Revert the changes to the database.
	 *
	 * @return void
	 */
	public function down()
	{
		Schema::drop('laravel_cache');
	}

}