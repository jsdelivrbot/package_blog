<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;

class CreateBlogPostShopItemVarsTable extends Migration {
	protected $table='blog_post_shop_item_vars';
	/**
	 * Run the migrations.
	 *
	 * @return void
	 */
	public function up(){
		if (!Schema::hasTable($this->table)) {
			Schema::create($this->table, function(Blueprint $table){
				$table->integer('post_shop_item_id')->index();
				$table->integer('post_cat_id');
				$table->integer('post_id');
				$table->text('note');
				$table->timestamps();
			});
		}
	}


	/**
	 * Reverse the migrations.
	 *
	 * @return void
	 */
	public function down(){
		Schema::drop($this->table);
	}

}
