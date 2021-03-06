<?php

namespace XRA\Blog\Models;

use Illuminate\Database\Eloquent\Model;
use Laravel\Scout\Searchable;
use XRA\Extend\Traits\Updater;

/**
 * XRA\Blog\Models\Category
 *
 * @property-read \Illuminate\Database\Eloquent\Collection|\XRA\Blog\Models\Comment[] $comments
 * @property-read \Illuminate\Database\Eloquent\Collection|\XRA\Blog\Models\Post[] $posts
 * @mixin \Eloquent
 */
class Category extends Model
{
    use Searchable;
    use Updater;
    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'blog_categories';

    /**
     * The attributes that are mass assignable.
     *
     * @var array
     */
    protected $fillable = ['name','guid'];

    public function posts()
    {
        return $this->hasMany(Post::class);
    }

    public function comments()
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    public function deletePosts()
    {
        foreach ($this->posts as $post) {
            $post->delete();
        }
    }

    public function deleteComments()
    {
        foreach ($this->comments as $comment) {
            $comment->delete();
        }
    }

    public function url()
    {
        //dd($this->toArray());
        $url=route('blog.category', ['guid_category'=>$this->guid]);
        return $url;
    }

    public function formFields(){
        //$view=CrudTrait::getView(); //non posso usarla perche' restituisce la view del chiamante
        //return view('blog::admin.post.partials.'.strtolower(class_basename($this)) )->with('row',$this);
        return false; //category non ha campi collegati per ora
    }
}
