<?php
namespace XRA\Blog\Controllers\Post;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
//--- extends ---
use XRA\Extend\Traits\CrudContainerItemTrait as CrudTrait;

class PostCatController extends Controller{
	use CrudTrait;
}