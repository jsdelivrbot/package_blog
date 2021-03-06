<?php

namespace XRA\Blog\Models;

use Illuminate\Database\Eloquent\Model;

//use Laravel\Scout\Searchable;

use Carbon\Carbon;
use XRA\Extend\Traits\Updater;

/**
 * { item_description }
 * da fare php artisan scout:import XRA\Blog\Models\Post
 *
 * @mixin \Eloquent
 */

class Feed extends Model{
	//use Searchable; //se non si crea prima indice da un sacco di errori
	use Updater;
	protected $table = "blog_feeds";
	protected $fillable = ['post_id'];

	public function formFields(){
		//$view=CrudTrait::getView(); //non posso usarla perche' restituisce la view del chiamante
		//return view('blog::admin.post.partials.'.strtolower(class_basename($this)) )->with('row',$this);
		return false;
	}
}