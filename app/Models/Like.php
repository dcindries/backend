<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Like extends Model
{
    /**
     * Tabla sobre la que trabaja este modelo
     */
    protected $table = 'post_user_likes';

    /**
     * Campos que se pueden asignar masivamente.
     */
    protected $fillable = ['user_id'];

    /**
     * Relación al post que recibió el like.
     */
    public function post()
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * Relación al usuario que dio el like.
     */
    public function user()
    {
        return $this->belongsTo(User::class);
    }
}
