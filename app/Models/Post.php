<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use App\Models\User;
use App\Models\Group;
use App\Models\Comment;

class Post extends Model
{
    use HasFactory;

    protected $fillable = [
        'content',
        'image_path',
        'group_id',
        'created_by',
    ];

    /** Relación con comentarios */
    public function comments()
    {
        return $this->hasMany(Comment::class);
    }

    /** Relación con el autor (usuario) */
    public function author()
    {
        return $this->belongsTo(User::class, 'created_by');
    }

    /** Relación con el grupo al que pertenece */
    public function group()
    {
        return $this->belongsTo(Group::class, 'group_id');
    }
    public function likers()
    {
        return $this->belongsToMany(User::class, 'post_user_likes')
            ->withTimestamps();
    }
    public function likesCount()
    {
        return $this->likers()->count();
    }
    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function savedPosts()
    {
        return $this->hasMany(SavedPost::class);
    }
    public function savedBy()
    {
        return $this->hasMany(SavedPost::class);
    }


}
