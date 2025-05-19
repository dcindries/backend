<?php
namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class User extends Authenticatable
{
    use HasApiTokens, Notifiable;
    use HasFactory, Notifiable;
    protected $fillable = [
        'name',
        'email',
        'password',
        'role',
        'profile_photo_path',    // la columna real
    ];

    protected $appends = [
        'is_admin',
        'profile_photo_url',     // atributo appended
    ];

    public function getIsAdminAttribute(): bool
    {
        return $this->id === 1;
    }

    /**
     * Devuelve la URL pública de la foto (o null).
     */
    public function getProfilePhotoUrlAttribute(): ?string
    {
        return $this->profile_photo_path
            ? asset("storage/{$this->profile_photo_path}")
            : null;
    }

    public function groups()
    {
        return $this->belongsToMany(Group::class, 'group_user')->withTimestamps();
    }

    public function likedPosts()
    {
        return $this->belongsToMany(Post::class, 'post_user_likes')->withTimestamps();
    }

    public function likes()
    {
        return $this->hasMany(Like::class);
    }

    public function savedPosts()
    {
        return $this->belongsToMany(
            Post::class,
            'post_user_saves',
            'user_id',
            'post_id'
        )->withTimestamps();
    }

}
