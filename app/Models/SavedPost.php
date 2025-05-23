<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use App\Models\Post;
use App\Models\User;

class SavedPost extends Model
{
    protected $table = 'saved_posts';
    protected $fillable = ['user_id', 'post_id'];

    public function post()
    {
        return $this->belongsTo(Post::class);
    }
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
