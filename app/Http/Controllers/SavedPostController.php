<?php

namespace App\Http\Controllers;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use App\Models\SavedPost;
use Illuminate\Support\Facades\Auth;

class SavedPostController extends BaseController
{
    public function __construct()
    {
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    public function save($postId)
    {
        $user = Auth::user();
        SavedPost::firstOrCreate([
            'user_id' => $user->id,
            'post_id' => $postId,
        ]);

        return response()->json(['message' => 'Post guardado correctamente']);
    }

    public function unsave($postId)
    {
        $user = Auth::user();
        SavedPost::where([
            'user_id' => $user->id,
            'post_id' => $postId,
        ])->delete();

        return response()->json(['message' => 'Post eliminado de guardados']);
    }

    public function index()
    {
        $user = Auth::user();
        $posts = SavedPost::with('post.group')
            ->where('user_id', $user->id)
            ->get();
        return response()->json($posts);
    }
}
