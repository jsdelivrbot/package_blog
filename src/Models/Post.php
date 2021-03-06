<?php

namespace XRA\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Builder;

use Laravel\Scout\Searchable;


//--- Models ---
use XRA\LU\Models\User;
//------- Traits --------
use XRA\Extend\Traits\FilterTrait;
use XRA\Extend\Traits\ImportTrait;
use XRA\Extend\Traits\Updater;

//use XRA\Blog\Models\Post\PostTrait;

//use Laralang;

//https://developers.google.com/search/docs/data-types/articles
use Intervention\Image\Facades\Image;
use ImageOptimizer;

use Cache;

/**
 * XRA\Blog\Models\Post
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\XRA\Blog\Models\PostContent[] $PostContent
 * @property-read \XRA\Blog\Models\Category $category
 * @property-read \Illuminate\Database\Eloquent\Collection|\XRA\Blog\Models\Comment[] $comments
 * @property-read \XRA\LU\Models\User $user
 * @mixin \Eloquent
 */
class Post extends Model{
	use FilterTrait;
	use Searchable; //ne update quando aggiungo un array mi da errore
	use Updater;
	use ImportTrait;
	//use PostTrait;

	protected $dates = [
		'created_at',
		'updated_at',
		'deleted_at',
		'published_at',
	];

	protected $casts = [
		//'is_admin' => 'boolean',
		//'content' => 'array',
		'content_type' => 'array',
		'image_resize_src' => 'array',
		//'url' => 'array',
		'url_lang' => 'array',
		'linked_count'=> 'array',
		'related_count'=> 'array',
		'relatedrev_count'=> 'array',
		'tags'=>'string',
		'parent_id' => 'integer',
	];

	//protected $primaryKey = ['post_id','lang'];  //problemi ovunque nel crud
	//protected $primaryKey = 'post_id';  //aggiorna sempre tutti anche quelli delle lingue diverse, fino a bug risolto usiamo PostRev



	/**
	 * The table associated with the model.
	 *
	 * @var string
	 */
	protected $table = 'blog_posts';

	/**
	 * The attributes that are mass assignable.
	 *
	 * @var array
	 */
	protected $fillable = [
		'post_id', //importante per le tabelle collegate
		'lang',
		'title',
		'subtitle',
		'guid',
		'type',
		'txt',
		'image_src','image_alt','image_title',
		'meta_description',
		'meta_keyword',
		'author_id',
		'created_at',
		'updated_at',
		'published',
		//'tags',
		'title',
		'description',
		'content',
		'parent_id',  //nella prox versione forse va a prendere il setAttributeId
		'url',
		'url_lang', //buffer
		'image_resize_src', // buffer
		'linked_count', // buffer
		'related_count', // buffer
		'relatedrev_count', //buffer


	];

	protected $appends = [/*'linked',*//*'category_id',*//*'tags',*//*'parent_id'*//*,'pivot'*/]; // category_id dipende dalla tabella linked

	protected $primaryKey = 'id';  //no give error because the key is post_id + lang
	public $incrementing = true;

	public function rules(){
		//esempio da documentazione ufficiale.. io forse avrei fatto una variabile array e basta https://laravel.com/docs/5.6/validation
		//https://unnikked.ga/how-to-auto-validate-eloquent-models-on-laravel-5-3-47d8f8cc5cdf
		$rules=[
			'title' => 'required|min:3',
		];
		return $rules;
	}



	//--------------------------------------------------
	public function __construct($attributes =[] ){
		parent::__construct($attributes);
		$this->importInit();
	}
	
	public function getRouteKeyName(){
		return 'guid';
		//return \Request::segment(1) === 'admin' ? 'post_id' : 'guid';
	}

	//----------- RELATION SHIP --------------
	public function postCount(){
		return $this->hasMany(PostCount::class,'post_id','post_id');
	}

	public function postCats(){
		$type='postCat_x_'.$this->type;  //c'e' il rev
		return $this->relatedrev()->wherePivot('type',$type);
	}
	public function posts(){
		$type=$this->type.'_x_post';  
		return $this->related()->wherePivot('type',$type);
	}

	public function homes(){
		$type='home_x_'.$this->type;  //c'e' il rev
		return $this->relatedrev()->wherePivot('type',$type);
	}


	public function sons($type='parent'){
		if($this->type == $this->guid){
			if(\Request::input('force')==1){
				$rows=Post::ofType($this->type)->get();
				foreach($rows as $row){
					$row->parent_id=$this->post_id;
					$row->save();
				}
				PostRelated::whereRaw('post_id=related_id')->delete();
			}
			//*/
		}
		$rows=$this->relatedrevType($type)->with(['related'/*,'relatedrev','parentPost','sons'*/]);//->with('sons')->with('parentPost');//->withCount('sons'); con il withcount va in loop infinito
		return $rows;
	}

	public function archive(){
		$rows= $this->hasMany(Post::class,'type','type')
				->where('lang',$this->lang)
				->where('guid','!=',$this->type)
				->with(['relatedrev','related']);
		//*
		if(\Request::has('lat') && \Request::has('lng') && !\Request::has('mapWestLng') && !\Request::has('mapSouthLat')){
			$currentLocation = [
	    		'latitude'  => \Request::input('lat'),
	    		'longitude' => \Request::input('lng'),
			];

			$distance = 15; //km
			/*
			if(\Request::has('mapWestLng') && \Request::has('mapEastLng') ){
				$lng_d=abs(\Request::input('mapWestLng') - \Request::input('mapEastLng'));
				if($distance<$lng_d*111) $distance=$lng_d*111;
				$rows=$rows->ofLongitudeBetween($this->type,[\Request::input('mapWestLng'),\Request::input('mapEastLng')]);
				//dd($lng_d*111);
			}
			if(\Request::has('mapSouthLat') && \Request::has('mapNorthLat') ){
           		$lat_d=abs(\Request::input('mapSouthLat') - \Request::input('mapNorthLat'));
           		if($distance<$lat_d*111) $distance=$lat_d*111;
           		$rows=$rows->ofLatitudeBetween($this->type,[\Request::input('mapSouthLat'),\Request::input('mapNorthLat')]);
           	}
           	*/
			$rows=$rows->findNearest($this->type,$currentLocation, $distance,1000);
		}
		//*
		if(\Request::has('mapWestLng') && \Request::has('mapEastLng') ){ // da spostare in archive direttamente ?
           	$rows=$rows->ofLongitudeBetween($this->type,[\Request::input('mapWestLng'),\Request::input('mapEastLng')]);
        }
        if(\Request::has('mapSouthLat') && \Request::has('mapNorthLat') ){
           	$rows=$rows->ofLatitudeBetween($this->type,[\Request::input('mapSouthLat'),\Request::input('mapNorthLat')]);
        }
        if (\Request::has('q')) {
			$query=\Request::input('q');
			//dd(get_class($rows));//"Illuminate\Database\Eloquent\Relations\HasMany"
			$rows = $rows->ofSearch($query);  //of => scopeOf

		}
        //*/


		//*/
		return $rows;
	}

	/*--- to study
	public function getArchiveAttribute(){
        return $this->getRelationValue('archive')->keyBy('guid');
    }
	*/

	public function scopeFindNearest($query,$type,$currentLocation, $distance,$limit){
		$this->type=$type;
		return  $query->whereHas('linked', function ($q) use ($currentLocation, $distance,$limit) {
			$q->findNearest($currentLocation, $distance,$limit);
		});
	}




	public function topArchiveLinked($params=[]){
		extract($params);
		$skey=str_plural($type).'_count';
		$res=$this->archive->map(function ($row,$k) use($params,$skey){
					extract($params);
					//$row->$skey=$row->linkedCountType($type);
					$q=$row->linkedCountType($type);
					return $row;
				})
				//->sortByDesc($skey)
				->sortByDesc('linked_count.'.$type);
				;
		if(isset($limit)){
			$res=$res->slice(0,$limit);
		}
		return $res;
	}

	public function topArchive($params=[]){
		$type='all';
		extract($params);
		if(\Request::input('force')==1){
			$conn=$this->getConnection();
			$sql='insert into blog_post_count(post_id,relationship,type,q) (select post_id,"related","'.$type.'",0 from blog_posts
				where lang="it" and guid!=type and type="'.$this->type.'" and NOT EXISTS (
			    	SELECT post_id,relationship,type FROM blog_post_count WHERE post_id = blog_posts.post_id and relationship="related" and type="'.$type.'"
				)) ';
			$res=$conn->statement($sql);
			if($type=='all'){
				$sql="update blog_post_count as A set q= (select count(*) from blog_post_related as B
				where A.post_id=B.post_id) where relationship='related' and type='all'";
				$res=$conn->statement($sql);
			}else{
				$sql="update blog_post_count as A set q= (select count(*) from blog_post_related as B
				where A.post_id=B.post_id and type='".$this->type."_x_".$type."') where relationship='related' and type='".$type."'";
				$res=$conn->statement($sql);
			}

		}

        $res=$this->archive()
        		->leftjoin('blog_post_count','blog_posts.post_id','=','blog_post_count.post_id')
        		->where('blog_post_count.relationship','related')
        		->where('blog_post_count.type',$type)
        		->select('blog_posts.*')
        		->orderBy('blog_post_count.q','desc');

		if(isset($limit)){
			$res=$res->limit($limit);
		}
		return $res->get();
	}

	public function topArchiveRev($params=[]){
		extract($params);
		$skey=str_plural($type).'rev_count';
		$res= $this->archive->map(function ($row,$k) use ($params,$skey){
					extract($params);
					//$row->$skey=$row->relatedrevCountType($type);
					$q=$row->relatedrevCountType($type);
					return $row;
				})
				//->sortByDesc($skey);
				->sortByDesc('relatedrev_count.'.$type);
		if(isset($limit)){
			$res=$res->slice(0,$limit);
		}
		return $res;
	}

	public function relatedNotOrdered(){
		//belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey,$parentKey, $relatedKey, $relation)
		$pivot_fields=['type','pos','price','price_currency','id'];
		$rows= $this->belongsToMany(Post::class, 'blog_post_related', 'post_id', 'related_id','post_id','post_id')
				->withPivot($pivot_fields)
				->using(PostRelatedPivot::class)
				->where('lang', \App::getLocale())
				//->orderBy('pivot_pos');
				//->with(['related'])
				;
		return $rows;
	}
	public function related(){
		//belongsToMany($related, $table, $foreignPivotKey, $relatedPivotKey,$parentKey, $relatedKey, $relation)
		$pivot_fields=['type','pos','price','price_currency','id'];
		$rows= $this->belongsToMany(Post::class, 'blog_post_related', 'post_id', 'related_id','post_id','post_id')
				->withPivot($pivot_fields)
				->using(PostRelatedPivot::class)
				->where('lang', \App::getLocale())
				->orderBy('blog_post_related.pos','asc');
				//->with(['related'])
				;
		return $rows;
	}

	public function relatedrev(){ //related reverse
		$pivot_fields=['type','pos','price','price_currency','id'];
		$rows= $this->belongsToMany(Post::class, 'blog_post_related', 'related_id', 'post_id','post_id','post_id')
				->withPivot($pivot_fields)
				//->using(PostRelatedPivot::class)
				->where('lang', \App::getLocale());
		return $rows;
	}

	public function links(){
		return $this->hasMany(PostLink::class,'post_id','post_id');
	}

	public function relatedObj($obj){
		$relationship=$this->type.'_x_'.$obj->type;
		$related_id=$obj->post_id;
		return $this->related->where('pivot.type',$relationship)->where('pivot.related_id',$related_id);
	}

	public function is_collect($obj){
		if(is_object($obj) && get_class($obj) == 'Illuminate\Database\Eloquent\Collection') {
			return true;
		}
		return false;
	}

	public function relatedPivot($obj){
		$parz=[];
		$parz['post_id']=$this->post_id;
		$parz['related_id']=$obj->post_id;
		$parz['type']=$this->type.'_x_'.$obj->type;
		$pivot=PostRelated::firstOrCreate($parz);
		return $pivot;
	}


	public function relatedOrAttach($obj){  //si usa con le collection da splittare in 2 funzioni
		$relationship=$this->type.'_x_'.$obj->type;
		$related_id=$obj->post_id;
		if(!isset($this->$relationship)){
			//$this->$relationship=$this->related->where('pivot.type',$relationship); // to TEST
			$this->$relationship=$this->related()->wherePivot('type',$relationship); // to TEST
		}
		if($this->is_collect($this->$relationship)) {
			$b=$this->$relationship->where('pivot.related_id',$related_id); // to TEST
		}else{
			$b=$this->$relationship->wherePivot('related_id',$related_id); // to TEST
		}
		if($b->first()==null){
			$a=$this->related()->attach($related_id,['type'=>$relationship]);
			$b=$this->related()->wherePivot('type',$relationship)->wherePivot('related_id',$related_id);//questo per avere la funzione refreshata
			if($this->is_collect($this->$relationship)) {
				$this->$relationship->push($b->first());
			}
		}else{
		}
		return $b;
	}

	public function postRelated(){
		return $this->hasMany(PostRelated::class, 'post_id', 'post_id');
	}

	public function parentPost(){
		//return $this->related->where('pivot.type', 'parent');
		return $this->relatedType('parent');
	}

	public function relatedObjType($type){
		$relationship=$this->type.'_x_'.$type;
		return $this->related->where('pivot.type', $relationship);
	}

	public function relatedCount($type){
		if(strpos($type,'_x_')===false){
			$type=$this->type.'_x_'.$type;
		}
		$related=$this->relatedType($type);
		return $related->count();
	}


	public function relatedType($type){
		if(strpos($type,'_x_')===false){
			$type=$this->type.'_x_'.$type;
		}
		return $this->related()->wherePivot('type', $type);//->where('lang',\App::getLocale());
		//return $this->related->where('pivot.type', $type);//->where('lang',\App::getLocale());
	}
	public function relatedrevType($type){
		return $this->relatedrev()->wherePivot('type', $type);//->where('lang',\App::getLocale());
	}

	public function linked(){
		return $this->linkedType($this->type);
	}

	public function linkedOrCreate(){
		return $this->linkedTypeOrCreate($this->type);
	}

	public function linkedRestaurant(){
		return $this->linkedType('restaurant');
	}


	public function linkedType($type){

		$model=config('xra.model.'.$type);
		//*
		if($model==null){
			//echo '<h3>['.__LINE__.'][]['.__FILE__.']</h3>';
			//dd($this);
			//return $this->hasOne(Post::class, 'post_id', 'post_id');
            //return null;
            $model=config('xra.model.post');
		}
		$with=[];
		//dd($type);
		//*
		switch($type){
			case 'restaurant':$with[]='cuisines'; break;
			case 'location': $with[]='restaurants'; break;
			case 'cuisine': $with[]='ingredientCats';$with[]='recipes'; break;

			default: break;
		}
		//*/
		//*/
		$row=$this->hasOne($model, 'post_id', 'post_id')->with($with);
		//if($row==null){//
		//---
		//*--  tolto cosi' non crea righe
		if(\Request::has('force')){
			if(!$row->exists()){
				$row0=$model::create(['post_id'=>$this->post_id]);
				$row=$this->hasOne($model, 'post_id', 'post_id');
			}
		}
		//*/
		return $row;
	}

	public function linkedTypeOrCreate($type){

		$model=config('xra.model.'.$type);
		//*
		if($model==null){
			echo '<h3>['.__LINE__.'][]['.__FILE__.']</h3>';
			dd($this);
			//return $this->hasOne(Post::class, 'post_id', 'post_id');
			return null;
		}
		$with=[];
		//dd($type);
		//*
		switch($type){
			case 'restaurant':$with[]='cuisines'; break;
			case 'location': $with[]='restaurants'; break;
			case 'cuisine': $with[]='ingredientCats';$with[]='recipes'; break;

			default: break;
		}
		//*/
		//*/
		$row=$this->hasOne($model, 'post_id', 'post_id')->with($with);
		//if($row==null){//
		//---
		//*--  tolto cosi' non crea righe

			if(!$row->exists()){
				$row0=$model::create(['post_id'=>$this->post_id]);
				$row=$this->hasOne($model, 'post_id', 'post_id');
			}

		//*/
		return $row;
	}

	public function linkedModel($type=null){
		if($type==null) $type=$this->type;
		$model=config('xra.model.'.$type);
		return $model;
	}
	public function linkedRowModel($type=null){
		$model=$this->linkedModel($type);
		$row=$model::firstOrCreate(['post_id'=>$this->post_id]);
		return $row;
	}


	public function field($fieldname){
		if (in_array($fieldname, Post::fields()->all())) {
			return $this->$fieldname;
		}
		$linked=$this->linked()->first();
		return $linked->$fieldname;
	}

	public function author(){
		return $this->belongsTo(User::class,'author_id','auth_user_id')->withDefault(['url'=>'---','handle'=>'---']);
	}

	public function relatedHasMany(){
		return $this->hasMany(PostRelated::class, 'post_id', 'post_id');
	}


	//------------- SCOPE OF --------------------------------
	public function scopeOfLinkedField($query,$k,$v){
		$rows= $query->whereHas('linked', function ($q) use ($k,$v) {
			$q->where($k,$v);
		});
		return $rows;
	}

	public function scopeOfLocality($query,$locality){
		$this->type='restaurant';
		$rows= $query->whereHas('linked', function ($q) use ($locality) {
			$q->where('locality',$locality);
		});
		return $rows;
	}

	public function scopeOfLinkedWhereRaw($query,$whereRaw){
		return $query->whereHas('linked', function ($q) use ($whereRaw) {
			$q->whereRaw($whereRaw);
		});
	}

	public function scopeOfLongitudeBetween($query, $type,$longitude_arr){
		$this->type=$type;
		return  $query->whereHas('linked', function ($q) use ($longitude_arr) {
			$q->whereBetween('longitude',$longitude_arr);
		});
	}

	public function scopeOfLatitudeBetween($query, $type,$latitude_arr){
		$this->type=$type;
		return  $query->whereHas('linked', function ($q) use ($latitude_arr) {
			$q->whereBetween('latitude',$latitude_arr);
		});

	}

	public function scopeOfSearch($query, $q){
		return $query->where('title','like','%'.$q.'%')->orWhere('subtitle','like','%'.$q.'%');
	}

	public function scopeOfParent($query, $related_id){
		return  $query->whereHas('relatedrev', function ($q) use ($related_id) {
			$q->where('related_id', $related_id);
		});
		//return $query->relatedrev()->wherePivot('related_id',$related_id);
	}
	public function scopeOfParentId($query, $parent_id){
		$rows= $query->whereHas('related', function ($q) use ($parent_id) {
			$q->where('related_id', $parent_id);
		});
		die( $rows->toSql());
	}

	public function scopeOfRelatedId($query, $related_id){
		return  $query->whereHas('related', function ($q) use ($related_id) {
			$q->where('related_id', $related_id);
		});
	}


	public function scopeOfType($query, $type){
		$rows=$query->where('type',$type)->where('guid','!=',$type)->where('title','!=','')->where('lang',\App::getLocale()); //title != '', dovrebbe essere inutile..
		return $rows;
	}

	public function scopeOfRelatedType($query, $type){
		//return $query->relatedType($type)->orderBy('aaa');
		$rows= $query->whereHas('relatedHasMany', function ($q) use ($type) {
			$q->where('blog_post_related.type', $type);
			$q->orderBy('pos');
		});//->where('lang',\App::getLocale());
		if($type=='topbar'){
			$rows=$rows->with('sons')->withCount('sons');
		}
		return $rows;

	}

	public function scopeOfRelatedRevPostId($query,$post_id){
		$where=['post_id'=>$post_id];
		$rows= $query->whereHas('relatedrev', function ($q) use ($where) {
			foreach($where as $k=>$v){
				$q->where('blog_post_related.'.$k, $v);
			};
		});
		return $rows;
	}

	public function scopeOfRelatedQuery($query,$where){
		$rows= $query->whereHas('relatedrev', function ($q) use ($where) {
			foreach($where as $k=>$v){
				$q->where('blog_post_related.'.$k, $v);
			};
		});
		return $rows;
	}

	public function scopeOfRelatedrevType($query, $type)
	{
		return  $query->whereHas('relatedrev', function ($q) use ($type) {
			$q->where('blog_post_related.type', $type);
		})->where('lang', \App::getLocale());
	}





	public function image_resized_cropped($params){
		extract($params);
		//$image_resized='assets_packs/img/'.$width.'x'.$height.'/'.basename($image_path);
		if($image_path==null){
			return 'assets_packs/img/'.$width.'x'.$height.'/nophoto.png';
		}
		$image_resized='assets_packs/img/'.$width.'x'.$height.'/'.str_slug($this->title).'.jpg';
		if(\File::exists(public_path($image_resized))) return $image_resized; //immagine la creo 1 volta sola
		if(substr($image_path,0,2)=='//'){
			$image_path='http:'.$image_path;
		}

		if(!\File::exists($image_path) ){
			//echo '['.__LINE__.']['.__FILE__.']';

			//return false;
		}

		$allowedMimeTypes = ['image/jpeg','image/gif','image/png','image/bmp','image/svg+xml'];
		$allowedExtensions=['jpg','png','gif'];
		$pathinfo = pathinfo($image_path);
		if(!isset($pathinfo['extension'])) return $image_path;
		if(!in_array($pathinfo['extension'],$allowedExtensions)) return $image_path;
		//if(!in_array($contentType,$allowedMimeTypes)) return $image_path;

		//return $image_path;

		$img = Image::make($image_path);

		$img->resize($width, null, function ($constraint) {
			$constraint->aspectRatio();
		});

		if ($img->height() > $height) {
			/* //voglio croppare l'immagine per non lasciare bordi brutti
			$img->resize(null, $height, function ($constraint) {
				$constraint->aspectRatio();
			});
			*/
		   $x0=0;
		   $y0=rand(0,$img->height() - $height);
		   $img->crop($width,$height,$x0,$y0);

		}
		$w0 = $img->width();
		$h0 = $img->height();
		$canvas = Image::canvas($width, $height, '#fdfdfd');
		$x=round(($width - $w0)/2,0);
		$y=round(($height -$h0)/2,0);
		$canvas->insert($img, null, $x, $y);

		\File::makeDirectory(dirname(public_path($image_resized)), 0775, true, true);
		$canvas->save(public_path($image_resized),75);
		ImageOptimizer::optimize(public_path($image_resized));
		//app(Spatie\ImageOptimizer\OptimizerChain::class)->optimize(public_path($image_resized));
		return $image_resized;
	}

	 public function image_resized_canvas($params){
		extract($params);
		//$image_resized='assets_packs/img/'.$width.'x'.$height.'/'.basename($image_path);
		if($image_path==null){
			//return 'assets_packs/img/'.$width.'x'.$height.'/nophoto.png';
			$image_path=public_path('images/nophoto.png');
		}
		$image_resized='assets_packs/img/'.$width.'x'.$height.'/'.str_slug($this->title).'.jpg';
		//if(\File::exists(public_path($image_resized))) return $image_resized; //immagine la creo 1 volta sola
		if(substr($image_path,0,2)=='//'){
			$image_path='http:'.$image_path;
		}
		$allowedMimeTypes = ['image/jpeg','image/gif','image/png','image/bmp','image/svg+xml'];
		$allowedExtensions=['jpg','png','gif'];
		$pathinfo = pathinfo($image_path);
		if(!isset($pathinfo['extension'])){
			$pathinfo['extension']='';
		}
		$pos=strpos($pathinfo['extension'],'&');
		if($pos!==false){
			$pathinfo['extension']=substr($pathinfo['extension'],0,$pos);
		}

		if(!isset($pathinfo['extension'])) return $image_path;
		if(!in_array($pathinfo['extension'],$allowedExtensions)) return $image_path;
		//if(!in_array($contentType,$allowedMimeTypes)) return $image_path;
		//* -- spostato in importtrait
		$str0='https://s2.qwant.com';//questo fa un redirect
		$str1='http://s2.qwant.com';
		$str2='https://s2.qwant.com';//questo fa un redirect
		$str3='http://s2.qwant.com';
		if(substr($image_path,0,strlen($str0))==$str0 || substr($image_path,0,strlen($str1))==$str1 ||
		   substr($image_path,0,strlen($str2))==$str2 || substr($image_path,0,strlen($str3))==$str3){
			$pos=strpos($image_path,'?');
			$image_path1=substr($image_path, $pos+1);
			parse_str($image_path1, $output);
			//echo '<h3>'.$image_path1.'</h3>';
			$image_path=urldecode($output['u']);
		}
		//*/
		$str='/laravel-filemanager/';
		if(substr($image_path,0,strlen($str))==$str){
			$image_path=public_path(substr($image_path,strlen($str)));
			//die($image_path);
		}
		//die($image_path);

		//return $image_path;
		try {
			$img = Image::make($image_path);
		} catch (\Intervention\Image\Exception\NotReadableException $e) {


			$filename=str_slug(basename($image_path)).'.'.$pathinfo['extension'];
			/*
			if(!\Storage::disk('cache')->exists($filename)){
				\Storage::disk('cache')->put($filename, fopen($image_path, 'r'));
			}

			*/
			//$asset = new FileAsset();

			//$jpg=imagecreatefromjpeg($image_path);

			//die('['.__LINE__.']['.__FILE__.']');
			$img_content=$this->cacheRequestFile('GET',$image_path);
			\Storage::disk('cache')->put($filename, $img_content);
			try {
				$img = Image::make(\Storage::disk('cache')->path($filename));
			} catch (\Intervention\Image\Exception\NotReadableException $e) {
				//echo '<h3>['.__LINE__.']['.__FILE__.']</h3>';
				$this->image_src='';
				$this->image_resize_src=[];
				$this->save();
				return null;
			}
			//$img_content=base64_encode($img_content);
			//$im = imagecreatefromstring($img_content);

			//$img=Image::make($img_content);
			//*
			//echo '<h3>['.__LINE__.']['.__FILE__.']</h3>';
			//return ;
			//echo $content;

			//*/

		}

		$img->resize($width, null, function ($constraint) {
			$constraint->aspectRatio();
		});

		if ($img->height() > $height) {
			$img->resize(null, $height, function ($constraint) {
				$constraint->aspectRatio();
			});
		}

		$w0 = $img->width();
		$h0 = $img->height();
		$canvas = Image::canvas($width, $height, '#fdfdfd');
		$x=round(($width - $w0)/2,0);
		$y=round(($height -$h0)/2,0);
		$canvas->insert($img, null, $x, $y);

		\File::makeDirectory(dirname(public_path($image_resized)), 0775, true, true);
		$canvas->save(public_path($image_resized),75);
		//ImageOptimizer::optimize(public_path($image_resized));   //--- CAPIRE SE NE VALE LA PENA
		//app(Spatie\ImageOptimizer\OptimizerChain::class)->optimize(public_path($image_resized));
		return $image_resized;
	}

	public function imageResizeSrc($params){
		$type='canvas';//default value;
		extract($params);
		$src=$this->image_src;
		$dim=$width.'x'.$height.'_'.$type;
		$buff=$this->image_resize_src;
		if(!is_array($buff)) $buff=[];
		if(isset($buff[$dim])){
			if($buff[$dim][0]!='/'){
				$buff[$dim]='/'.$buff[$dim];
			}
			return $buff[$dim];
		}
		$image_path=$src;
		$str=asset('');
		if(substr($this->image_src,0,strlen($str))==$str ){
			$image_path=public_path(str_replace($str,'',$this->image_src));
		}
		$params['image_path']=$image_path;
		$src1=$this->image_resized_canvas($params);
		$buff[$dim]=$src1;
		if(isset($this->relatedrev_count)) unset($this->relatedrev_count);
		if(isset($this->restaurantsrev_count)) unset($this->restaurantsrev_count);
		if(isset($this->alls_count)) unset($this->alls_count);
		$this->image_resize_src=$buff;
		$this->save();
		return $buff[$dim];


	}

	public function image_html($params){

		$type='canvas'; // canvas ,cropped
		$class='';
		$src=$this->image_src;
		extract($params);
		$src1=$this->imageResizeSrc($params);
		return '<img src="'.asset($src1).'" alt="'.$this->image_alt.'" title="'.$this->image_title.'"  width="'.$width.'px" height="'.$height.'px" class="'.$class.'"/>';
	}



	public function urlLang($lang){
		$url=$this->url_lang;

		if(!isset($url[$lang])){

			if(!$this->post_id){
				$segments=\Request::segments();
				$segments[0]=$lang;
				$url=(implode('/',$segments));
				return url($url);
			}
			$row=POST::where('post_id', $this->post_id)->where('lang',$lang)->first();
			if($row==null){
				$rowlang=$this->replicate();
				$rowlang->lang=$lang;
				$fields=['title','subtitle','txt','image_alt','image_title','guid'];
				foreach($fields as $field){
					if($field=='guid' && $this->guid==$this->type){
						//root
					}else{
						$tmp=$this->trans(['q'=>$this->$field,'from'=>$this->lang,'to'=>$lang]);
						if($tmp!=''){
							$rowlang->$field=$tmp;
						}
						if($field=='guid'){
							$rowlang->$field=str_slug($rowlang->$field);
						}
					}
				}
				$rowlang->url=null;
				$rowlang->url_lang=null;
				$rowlang->save();
				$url[$lang]=str_replace(url('/'),'',$rowlang->url);
				$this->url_lang=$url;
				$this->save();
				return url($url[$lang]);
			}

			$url[$lang]=str_replace(url('/'),'',$row->url);
			$this->url_lang=$url;

			$this->save();
			return url($url[$lang]);
		}
		$str='/'.$lang.'/home';//http://food.local/en/home/test-home-it
		if(strpos($url[$lang],$str)!==false){
			return url($lang);
		}
		return url($url[$lang]);
	}

	public function url_edit($lang){
		return $this->urlLang($lang);
		/*
		$row=POST::where('post_id', $this->post_id);
		$rowlang=$row->where('lang', $lang);
		if ($rowlang->count()==0) {
			$rowlang = $this->replicate();
			$rowlang->id=null;
			$rowlang->lang=$lang;
			$rowlang->save();
		} else {
			$rowlang=$rowlang->first();
		}
		//
		$params = \Route::current()->parameters();
		//\App::setLocale($params['lang']);
		$params['id_post']=$rowlang->id;
		//$params['id_seo']=$rowlang->id;
		$params['lang']=$lang;
		$routename = \Route::current()->getName();
		return route($routename, $params);
		*/
	}



	public function linkedFormFields(){

		$model=config('xra.model.'.$this->type);
		//echo('<h3>['.__LINE__.']['.__FILE__.']</h3>');dd($model);
		if($this->post_id!=null){
			$row=$model::firstOrCreate(['post_id'=>$this->post_id]);  //crea un vero record
			//$row=$model::firstOrNew(['post_id'=>$this->post_id]);   //crea solo l'oggetto
			//$row=$this->linked()->firstOrNew();
			return $row->formFields();
		}else{
			$row = new $model;
			return $row->formFields();
		}
	}//end function

	//------------- MUTUATORS -----------
	//*
	public function setPivotAttribute_NOOOO($value){
		unset($this->attributes['pivot']);
		$this->pivot=$value;
	}
	/*
	public function getPriceAttribute($value){
		return '6';
	}
	*/

	public function getPostIdAttribute($value){
		if($value==null && isset($this->attributes['id']) ){
			$this->attributes['post_id']=$this->attributes['id'];
			$this->post_id=$this->attributes['id'];
			$this->save();
			return $this->attributes['post_id'];
		}
		return $value;
	}
	/*
	public function getLongitudeAttribute($value){
		return $this->linked->longitude;
	}

	public function getLatitudeAttribute($value){
		return $this->linked->latitude;
	}
	*/
	public function setParentIdAttribute($value){
		//die('['.__LINE__.']['.__FILE__.']['.$value.']');
		$relationship='parent';
		//*
		if($this->parent_id != $value){
			$this->related()->attach($value,['type'=>$relationship]);
		}else{

		}
		//*/
		//*
		//$parents=$this->related->where('pivot.type',$relationship);// 33 secondi !!!
		/*
		$n_parents=$parents->count();
		if($n_parents>1){
			PostRelated::where('type',$relationship)
				->where('post_id',$this->post_id)
				->where('related_id',$value)
				->limit($n_parents - 1)
				->delete();
		}
		//*/
		/*


		//$this->related()->attach($value,['type'=>$relationship]);
		*/
	}


	public function setPostIdAttribute($value){
		if($value==null && isset($this->attributes['id']) ){
			$this->attributes['post_id']=$this->attributes['id'];
		}else{
			$this->attributes['post_id']=$value;
		}
	}

	public function getMetaDescriptionAttribute($value){

		if(strlen($value)<5 ){
			$meta_description=strip_tags(ucfirst($this->type).' '.$this->title.' '.$this->subtitle.' '.$this->txt.' '.$value);
			$meta_description=str_replace('&nbsp;',' ',$meta_description);
			$meta_description=htmlspecialchars_decode($meta_description);
			$meta_description=trim($meta_description);
			$meta_description=substr($meta_description,0,260);
			$this->meta_description=$meta_description;
			$this->attributes['meta_description']=$meta_description;
			$this->save();
			return $this->attributes['meta_description'];
		}
		//[Warning] Meta description tag is too long for Bing. Bing recommends keeping the description text under 160 characters in length.
		//[Warning]Meta description tag is too long for Yahoo. Yahoo recommends limiting your description tag to 256 characters.
		return substr($value,0,256);
	}


	public function getImageAltAttribute($value){
		if($value==null && isset($this->attributes['title']) ){
			$value='Image of '.$this->attributes['title'];
		}
		return $value;
	}

	public function getImageTitleAttribute($value){
		if($value==null && isset($this->attributes['title']) ){
			$value=''.$this->attributes['title'];
		}
		return $value;
	}




	public function getParentIdAttribute($value){
		if($value==null){
			$row=$this->related->where('pivot.type','parent')->first();
			if ($row==null) {
				$value=0;
			}else{
				$value=$row->post_id;//$row->first()->pivot->related_id;
			}
		}
		return $value;
	}
	public function getAdmUrlAttribute($value){
		//return url('admin/blog/'.$)
		return 'oooo';
	}
	public function setUrlAttribute($value){
		if($value==''){
			$parentPost=$this->related->where('pivot.type','parent')->first();
			if ($parentPost!=null) {
				$value=$this->lang.'/'.$parentPost->guid.'/'.$this->guid;
			}elseif ($this->guid==$this->type) {
				$value=$this->lang.'/'.$this->guid;
			}else{
				$value=$this->lang.'/'.$this->type.'/'.$this->guid;
			}
		}
		$this->attributes['url'] = '/'.$value;
		//dd($this->attributes['url']);
	}
	public function getUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->url;
		}
		if($value==''){
			$this->setUrlAttribute($value);
			$value=$this->attributes['url'];
		}
		/*
		$lang=\App::getLocale();
		$str=''.$lang.'/home';//http://food.local/en/home/test-home-it
		if(strpos($value,$str)!==false){
			return url($lang);
		}
		//*/
		return url($value);
	}

	public function getMoveupUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->moveup_url;
		}
		return $this->getUrlAct('moveup');
	}
	public function getMovedownUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->movedown_url;
		}
		return $this->getUrlAct('movedown');
	}
	public function getIndexUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->index_url;
		}
		return $this->getUrlAct('index');
	}
	public function getCreateUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->create_url;
		}
		return $this->getUrlAct('create');
	}
	public function getEditUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->edit_url;
		}
		return $this->getUrlAct('edit');
	}
	public function getUpdateUrlAttribute($value){
		if(isset($this->pivot)){
			return $this->pivot->update_url;
		}
		return $this->getUrlAct('update');
	}

	public function getUrlAct($act){
		$params=\Route::current()->parameters();
		$routename=\Request::route()->getName();
		$routename_arr=explode('.',$routename);
		$ris=null;
		foreach($params as $k=>$v){
			if(is_object($v) && $v->is($this)){
				$ris=$k;
				break;
			}
		}
		
		$ris_tmp=str_replace('item','container',$ris);
		$k=array_search($ris_tmp,$routename_arr);
		/*
		if($k<1){
			echo '[['.$k.']]';
			ddd($ris);
		}
		*/
		//ddd($k);
		if($ris_tmp==$ris && $act=='edit'){
			$n=str_replace('container','',$ris);
			$params['item'.$n]=$params['container'.$n];
		}
		if($ris_tmp!=$ris && $act=='index'){
			$act='show';
		}
		$routename_act=implode('.',array_slice($routename_arr,0,$k+1)).'.'.$act;
		return route($routename_act,$params);
	}

	/*
	public function setLinkedCountAttribute($value){

	}
	*/
	public function relatedCountType($type){
		if(!is_array($this->related_count)){
			$this->related_count=[];
		}
		if(isset($this->related_count[$type])){
			return $this->related_count[$type];
		}
		$rows=$this->related;
		if($type!='all'){
			$rows=$rows->where('type',$type);
		}
		$q=$rows->count();
		$tmp=$this->related_count;
		$tmp[$type]=$q;
		$this->related_count=$tmp;
		$this->save();
	}

	public function relatedrevCountType($type){
		if(!is_array($this->relatedrev_count)){
			$this->relatedrev_count=[];
		}
		if(isset($this->relatedrev_count[$type])){
			return $this->relatedrev_count[$type];
		}
		$rows=$this->relatedrev;
		$rows=$rows->where('type',$type);
		$q=$rows->count();
		$tmp=$this->relatedrev_count;
		$tmp[$type]=$q;
		$this->relatedrev_count=$tmp;
		$this->save();
	}


	public function linkedCountType($type){
		if(!is_array($this->linked_count)){
			$this->linked_count=[];
		}
		if(isset($this->linked_count[$type])){
			return $this->linked_count[$type];
		}
		$tmp=str_plural($type);
		$rows=$this->linked->$tmp;
		$linked_count=$rows->count();
		$tmp=$this->linked_count;
		$tmp[$type]=$linked_count;
		$this->linked_count=$tmp;
		$this->save();
		return $linked_count;
	}


	/*
	public function getLinkedCountAttribute($value){
		if($value==null){
			$this->setLinkedCountAttribute($value);
			$value=$this->attributes['linked_count'];
		}
		return $value;
	}
	*/

	/*
	public function getUrlAttribute($value){
		//$parentPost=$this->parentPost->first();
		//$key=__FUNCTION__.$this->post_id.'_'.$this->lang;
		//$value = Cache::rememberForever($key, function() {
		if($value==''){
			$value=[];
		}elseif(!is_array($value)){
			if($value[0]='{')
				$value=json_decode($value,true);
			else
				$value=[];
		}
		if(!isset($value[$this->lang])) {
			//die('['.__LINE__.']['.__FILE__.']');
			$parentPost=$this->related->where('pivot.type','parent')->first();
			if ($parentPost!=null) {
				$url=$this->lang.'/'.$parentPost->guid.'/'.$this->guid;
			}elseif ($this->guid==$this->type) {
				$url=$this->lang.'/'.$this->guid;
			}else{
				$url=$this->lang.'/'.$this->type.'/'.$this->guid;
			}
			$value[$this->lang]=$url;
			//$this->url=$value;
			//$this->save();
		}
		return url($value[$this->lang]);
		//});
		//return $value;
	}
	*/

	//--- fare anche il set atribute mutuators ?
	public function getGuidAttribute($value){
		if ($value=='' && isset($this->attributes['title'])) {
			$value=str_slug($this->attributes['title']);
			$this->attributes['guid'] = $value;
			$this->save();
		}
		return $value;
	}

	public function setGuidAttribute($value){
		//$tags=\Request::input('tags');
		//if($tags!='') $this->setTagsAttribute($tags);  //non lo chiama da solo con il mutuators.. lo chiamo da qui
		if (strlen($value)<3 || $value==null) {
			if(!isset($this->attributes['title'])){
				$this->attributes['title']='no-set';
			}
			$this->attributes['guid'] = str_slug($this->attributes['title']);
		}else{
			$this->attributes['guid'] =$value;
		}
	}
	//* genera dei loop da fix
	public function getImageSrcAttributeAA($value){
		if($value==''){
			if($this->linked==null){
			}else{
				$value=$this->linked->image_src;
				$this->image_src=$value;
				$this->save();
			}
		}
		$str='/laravel-filemanager/';
		if(substr($value,0,strlen($str)) == $str ){
			$str=substr($value,strlen($str));
			return asset($str);
		}
		return $value;
	}
	//*/
	//*
	public function getSubtitleAttributeAA($value){
		//return $value;
		if($value==''){
			if($this->linked==null){
				echo '<h3>['.__LINE__.']['.__FILE__.']</h3>';
				dd($this);
				//----
			}else{
				$value=$this->linked->subtitle;
				if($value!=null){
					$this->subtitle=$value;
					$this->save();
				}
			}
		}
		return $value;
	}
	//*/

	public function getReviewsCountAttribute($value)
	{
		return 1;
	}


	public function getRatingAvgAttribute($value)
	{
		//rating fro 0 to 5
		return 2;
	}

	public function getTagsAttribute($value){
		//rating fro 0 to 5
		$rows=$this->relatedType($this->type.'_x_tag')->get();

		return $rows->implode('title', ',');//pluck('title');
	}

	public function setTagsAttribute($value){
		//echo '<h3>POST</h3><pre>';print_r($_POST);echo '</pre>';
		$my_tags=collect(explode(',',$this->tags));
		$post_tags=collect(explode(',',$value));
		$add_tags=$post_tags->diff($my_tags);
		$sub_tags=$my_tags->diff($post_tags);
		/*
		echo '<h3>My</h3><pre>';print_r($my_tags->all());echo '</pre>';
		echo '<h3>Post</h3><pre>';print_r($post_tags->all());echo '</pre>';
		echo '<h3>Add</h3><pre>';print_r($add_tags->all());echo '</pre>';
		echo '<h3>Sub</h3><pre>';print_r($sub_tags->all());echo '</pre>';
		die('['.__LINE__.']['.__FILE__.']');
		*/

		foreach($add_tags->all() as $tag){
			$obj=Post::firstOrCreate(['title'=>trim($tag),'lang'=>\App::getLocale()]);
			if($obj->post_id==null){
				$obj->post_id=$obj->id;
				$obj->save();
			}
			$rel=$this->type.'_x_tag';
			$this->related()->attach($obj->post_id,['type'=>$rel]);
		}
		foreach($sub_tags->all() as $tag){
			$obj=Post::firstOrCreate(['title'=>trim($tag),'lang'=>\App::getLocale()]);
			if($obj->post_id==null){
				$obj->post_id=$obj->id;
				$obj->save();
			}
			$rel=$this->type.'_x_tag';
			$this->related()->detach($obj->post_id,['type'=>$rel]);
		}


	}//end set Tags

	public function breadcrumbs($row=null){
		$ris=[];
		//*
		if($this->parent_id==0 && $this->type!=$this->guid){
			$container=Post::firstOrCreate([
				'lang'=>\App::getLocale(),
				'guid'=>$this->type,
				'type'=>$this->type
			],[
				'title'=>$this->type
			]);
			if($container0->post_id==null){
				$container0->post_id=$container0->id;
				$container0->save();
			}
			$this->related()->attach($container0->post_id,['type'=>'parent']);
		}
		$parent=$this->parentPost->first();

		$max_iter=5;
		while ($parent!=null && $max_iter>0) {
			$ris=array_merge([$parent], $ris);
			$parent=$parent->parentPost->first();
			$max_iter--;
		}

		return $ris;
		//*/
	}//end breadcrumbs



	public function PostContentJson()
	{
		return json_encode($this->PostContent()->get());
	}

	public function PostContent()
	{
		return $this->hasMany(PostContent::class, 'post_id', 'id');
	}

	public function allCategories()
	{
		return new Category;
	}
	public function category() {
		return $this->belongsTo(Category::class);
	}

	public function comments(){
		return $this->hasMany(Comment::class);
	}

	public function deleteComments(){
		foreach ($this->comments as $comment) {
			$comment->delete();
		}
	}

	public function news_field(){
		return $this->hasOne(NewsField::class, 'post_id', 'id');
	}

	public function table_fields($model){
		return $this->hasOne('\XRA\Blog\Models\\'.$model, 'post_id', 'id');
	}

	public function foodType($model){
		return $this->hasOne('\XRA\Food\Models\\'.$model, 'post_id', 'id');
	}

	public function user(){
		return $this->belongsTo(User::class);
	}


	public function longitudeBetween($longitude_arr){
		die('['.__LINE__.']['.__FILE__.']');
		dd($this);
	}




	public function image($params=[]){
		$html='<img src="'.$this->image_src().'" alt="'.$this->image_alt.'" title="'.$this->image_title.'"';
		if (isset($params['height'])) {
			$html.=' height="'.$params['height'].'"';
		}
		$html.=' />';
		return $html;
	}


	/*--- QUANDO TORLGO TNTSEARCH
	public static function search($query, $callback = null){
		//return Searchable::search($query, $callback);
		$fields=self::fields();
		$rows=self::where($fields[0],$query);
		for($i=1;$i<count($fields);$i++){
			$rows=$rows->orWhere($fields[$i],$query);
		}
		return $rows;

	}

	public function searchableAs(){
		return config('scout.prefix').$this->getTable();
	}

	public function toSearchableArray(){
		return $this->toArray();
	}
	*/
	public function toSearchableArray(){
		return [];//$this->toArray();
	}
	/*
	https://scotch.io/tutorials/achieving-geo-search-with-laravel-scout-and-algolia
	public function toSearchableArray(){
		$record = $this->toArray();

		//if(is_object($this->linked) && isset($this->linked->latitude)){
        	$record['_geoloc'] = [
            	'lat' => $this->linked->latitude,
            	'lng' => $this->linked->longitude,
        	];
    	//}

        unset($record['created_at'], $record['updated_at']); // Remove unrelevant data
        //unset($record['latitude'], $record['longitude']);

        return $record;

	}
	*/
	public function formFields(){
		//$view=CrudTrait::getView(); //non posso usarla perche' restituisce la view del chiamante
		//return view('blog::admin.post.partials.'.strtolower(class_basename($this)) )->with('row',$this);
		return ;
	}


}
