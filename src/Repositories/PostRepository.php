<?php
namespace XRA\Blog\Repositories;

//use XRA\XRA\Repositories\BaseRepository;
use XRA\XRA\Repositories\AbstractRepository;

use XRA\Blog\Models\Post;

class PostRepository extends AbstractRepository{
	/**
     * Specify Model class name
     *
     * @return string
     */
    protected $model = Post::class;
}