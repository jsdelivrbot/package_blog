<?php
namespace XRA\Blog\Controllers\Admin\blog\post;

use App\Http\Controllers\Controller;
use Carbon\Carbon;
use Illuminate\Http\Request;
 
//--- extends ---
use XRA\Extend\Traits\CrudSimpleTrait as CrudTrait;
use XRA\Extend\Traits\ArtisanTrait;

//--- Models ---//
use XRA\Blog\Models\PostContent;
use XRA\Blog\Models\Post;

class PhotoController extends Controller
{
    use CrudTrait{
        store as protected storeTrait;
    }

    public function getModel()
    {
        return new Post;
    }
    public function index(Request $request)
    {
        $params = \Route::current()->parameters();
        extract($params);
        if (isset($lang)) {
            \App::setlocale($lang);
        } //?
        //echo '<pre>';print_r($params);echo '</pre>';
        //Post::where('post_id',0)->update(['post_id'=>'id']);
        //Post::statement();
        /*
        $rows1=Post::where('lang',$lang)->ofRelatedType('topbar');
        echo '<pre>';print_r($rows1->toSql()); echo '</pre>';
        echo '<pre>';print_r($rows1->get()->toArray()); echo '</pre>';
        dd('['.__LINE__.']['.__FILE__.']');
        */

        \DB::update('update blog_posts set post_id=id where post_id=0');

        $row=Post::where('post_id', $id_post)->where('lang', $lang)->first();
        $rows=$row->related('photo')->orderBy('pivot_pos');
        /*
        echo '<pre>';print_r($rows->toSql()); echo '</pre>';
        echo '<pre>';print_r($rows->get()->toArray()); echo '</pre>';
        dd('['.__LINE__.']['.__FILE__.']');
        */
        //dd($rows->toSql());
        $view=CrudTrait::getView();
        return view($view)->with('allrows', $rows)
            ->with('params', array_merge($request->all(), $params))
            ->with('row', $row);
    }


    public function store(Request $request)
    {
        $params = \Route::current()->parameters();
        extract($params);
        $row=Post::where('post_id', $id_post)->where('lang', $lang)->first();
        $request->_out='model';
        $row_new=$this->storeTrait($request);
        $row_new->post_id=$row_new->id;
        $row_new->save();
        //echo '<h3>'.$row_new->post_id.'</h3>';
        $row->related()->attach($row_new->post_id, ['type'=>'photo']);
    }
}
