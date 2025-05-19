<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Comment;

class CommentController extends BaseController
{
    public function __construct()
    {
        // Protege con Sanctum todos los métodos excepto index y show
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * GET /api/comments
     * - Admin (admin@gmail.com): todos los comentarios
     * - Otros: solo comentarios cuyos posts estén en grupos públicos
     */
    public function index(Request $request)
    {
        // Identificar usuario (incluye bearer token)
        $user = $request->user();
        if (! $user && ($t = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($t)) {
                $user = $pat->tokenable;
            }
        }

        $query = Comment::with(['user', 'post.group']);

        if (! ($user && $user->email === 'admin@gmail.com')) {
            // Filtrar: solo comentarios de posts en grupos públicos
            $query->whereHas('post.group', fn($q) => $q->where('is_public', true));
        }

        $comments = $query->get();
        return response()->json($comments, 200);
    }

    /**
     * GET /api/comments/{id}
     * Muestra un comentario, validando que si el post es privado solo lo vean miembros o admin.
     */
    public function show(Request $request, $id)
    {
        $comment = Comment::with(['user', 'post.group'])->findOrFail($id);
        $group   = $comment->post->group;

        // Identificar usuario
        $user = $request->user();
        if (! $user && ($t = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($t)) {
                $user = $pat->tokenable;
            }
        }

        if (! $group->is_public) {
            $isMember = $group->members()
                ->where('user_id', optional($user)->id)
                ->exists();

            if (! $isMember && (! $user || $user->email !== 'admin@gmail.com')) {
                return response()->json(['message' => 'Acceso denegado'], 403);
            }
        }

        return response()->json($comment, 200);
    }

    /**
     * POST /api/comments
     * Crea un nuevo comentario.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'post_id' => 'required|exists:posts,id',
            'content' => 'required|string',
        ]);

        $user = $request->user();

        $comment = Comment::create([
            'post_id' => $data['post_id'],
            'user_id' => $user->id,
            'content' => $data['content'],
        ]);

        return response()->json($comment->load(['user', 'post.group']), 201);
    }

    /**
     * PUT /api/comments/{id}
     * Actualiza un comentario (solo autor o admin).
     */
    public function update(Request $request, $id)
    {
        $comment = Comment::findOrFail($id);
        $user    = $request->user();
        $isAdmin = $user && $user->email === 'admin@gmail.com';

        if (! $isAdmin && $comment->user_id !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'content' => 'required|string',
        ]);

        $comment->update($data);

        return response()->json($comment->fresh()->load(['user', 'post.group']), 200);
    }

    /**
     * DELETE /api/comments/{id}
     * Elimina un comentario (solo autor o admin).
     */
    public function destroy(Request $request, $id)
    {
// dentro de destroy(Request $request, $id)
        $comment = Comment::with('post.group')->findOrFail($id);
        $user    = $request->user();

        $isSuperAdmin   = $user->email === 'admin@gmail.com';
        $isCommenter    = $comment->user_id === $user->id;
        $isGroupCreator = $comment->post->group->created_by === $user->id;

        if (! ($isSuperAdmin || $isCommenter || $isGroupCreator)) {
            return response()->json(['message'=>'No autorizado'], 403);
        }

        $comment->delete();
        return response()->json(['message'=>'Comentario eliminado'], 200);

    }
}
