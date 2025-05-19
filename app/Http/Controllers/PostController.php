<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Post;
use App\Models\Group;

class PostController extends BaseController
{
    public function __construct()
    {
        // Sólo index/show sin auth; el resto requiere Sanctum
        $this->middleware('auth:sanctum')->except(['index','show']);
    }

    /**
     * GET /api/posts
     * - Admin (admin@gmail.com): todos los posts
     * - Otros: solo posts de grupos públicos
     * - Si se pasa ?group_id=XX, filtra también por ese grupo
     */
    public function index(Request $request)
    {
        $user = $request->user();
        if (! $user && ($token = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                $user = $pat->tokenable;
            }
        }
        $query = Post::with(['author', 'group', 'comments.user'])
            ->withCount('likes as likes');
        if ($request->has('group_id')) {
            $query->where('group_id', $request->query('group_id'));
        }
        if ($user && $user->email === 'admin@gmail.com') {
        } else {
            $query->where(function($q) use ($user) {
                $q->whereHas('group', fn($g) => $g->where('is_public', true));
                if ($user) {
                    $q->orWhereHas('group.members', fn($g2) =>
                    $g2->where('user_id', $user->id)
                    );
                }
            });
        }
        $posts = $query->get();

        return response()->json($posts, 200);
    }

    /**
     * GET /api/posts/{id}
     * Muestra un post (sólo miembros si es privado)
     */
    public function show(Request $request, $id)
    {
        // Carga post con author, grupo y comentarios con usuario
        $post  = Post::with(['author','group','comments.user'])->findOrFail($id);
        $group = $post->group;

        // Identificar usuario
        $user = $request->user();
        if (! $user && ($token = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                $user = $pat->tokenable;
            }
        }

        // Si el grupo es privado, sólo miembros pueden ver
        if (! $group->is_public) {
            $isMember = $group->members()
                ->where('user_id', optional($user)->id)
                ->exists();
            if (! $isMember) {
                return response()->json(['message'=>'Acceso denegado'], 403);
            }
        }

        return response()->json($post, 200);
    }

    /**
     * POST /api/posts
     * Crea un nuevo post y lo devuelve con relaciones
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'content'   => 'required|string',
            'group_id'  => 'required|integer|exists:groups,id',
            'image'     => 'nullable|image|max:2048',
        ]);

        $user = $request->user();

        $imagePath = null;
        if ($request->hasFile('image')) {
            $file     = $request->file('image');
            $filename = 'post_' . time() . '.' . $file->getClientOriginalExtension();
            $path     = $file->storeAs('post_images', $filename, 'public');
            $imagePath = asset('storage/' . $path);
        }

        $post = Post::create([
            'content'    => $data['content'],
            'group_id'   => $data['group_id'],
            'created_by' => $user->id,
            'image_path' => $imagePath,
        ]);

        // Cargar relaciones antes de responder
        return response()->json(
            $post->load(['author','group','comments.user']),
            201
        );
    }

    /**
     * PUT /api/posts/{id}
     * Actualiza un post (solo autor o admin)
     */
    public function update(Request $request, $id)
    {
        $post  = Post::findOrFail($id);
        $user  = $request->user();
        $isAdmin = $user && $user->email === 'admin@gmail.com';

        if (! $isAdmin && $post->created_by !== $user->id) {
            return response()->json(['message'=>'No autorizado'], 403);
        }

        $data = $request->validate([
            'content'    => 'sometimes|required|string',
            'image_path' => 'nullable|string|max:255'
        ]);

        $post->update($data);

        return response()->json(
            $post->fresh()->load(['author','group','comments.user']),
            200
        );
    }

    /**
     * DELETE /api/posts/{id}
     * Elimina un post (solo autor o admin)
     */
    public function destroy(Request $request, $id)
    {
// dentro de destroy(Request $request, $id)
        $post = Post::with('group')->findOrFail($id);
        $user = $request->user();
        $isSuperAdmin    = $user->email === 'admin@gmail.com';
        $isAuthor        = $post->created_by === $user->id;
        $isGroupCreator  = $post->group->created_by === $user->id;

        if (! ($isSuperAdmin || $isAuthor || $isGroupCreator)) {
            return response()->json(['message'=>'No autorizado'], 403);
        }

        $post->delete();
        return response()->json(['message'=>'Publicación eliminada'], 200);

    }
}
