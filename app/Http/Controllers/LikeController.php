<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\Post;
use App\Models\Like;

class LikeController extends Controller
{
    /**
     * POST /api/posts/{post}/like
     * Marca como "me gusta" un post.
     */
    public function like(Request $request, Post $post)
    {
        $user = $request->user();

        // Evita duplicados
        if ($post->likes()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Ya has dado like a este post'], 409);
        }

        $post->likes()->create(['user_id' => $user->id]);

        return response()->json([
            'likes' => $post->likes()->count(),
            'liked' => true
        ], 201);
    }

    /**
     * DELETE /api/posts/{post}/like
     * Quita el "me gusta" de un post.
     */
    public function unlike(Request $request, Post $post)
    {
        $user = $request->user();

        $like = $post->likes()->where('user_id', $user->id)->first();
        if (! $like) {
            return response()->json(['message' => 'No tienes like en este post'], 404);
        }

        $like->delete();

        return response()->json([
            'likes' => $post->likes()->count(),
            'liked' => false
        ], 200);
    }

    /**
     * GET /api/user/likes
     * Lista todos los posts a los que este usuario ha dado like.
     */
    public function myLikes(Request $request)
    {
        $user = $request->user();

        $posts = $user
            ->likedPosts()
            ->with(['author', 'group'])
            ->withCount('likes')
            ->get()
            ->map(function (Post $post) {
                return [
                    'id'          => $post->id,
                    'excerpt'     => substr($post->content, 0, 50),
                    'content'     => $post->content,
                    'image_path'  => $post->image_path,
                    'group'       => [
                        'id'   => $post->group->id,
                        'name' => $post->group->name,
                    ],
                    'author'      => [
                        'name' => $post->author->name,
                    ],
                    'likes'       => $post->likes_count,
                    'created_at'  => $post->created_at,
                ];
            });

        return response()->json($posts, 200);
    }
}
