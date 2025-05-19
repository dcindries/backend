<?php

namespace App\Http\Controllers;

use Illuminate\Routing\Controller as BaseController;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Laravel\Sanctum\PersonalAccessToken;
use App\Models\Group;

class GroupController extends BaseController
{
    public function __construct()
    {
        // Protege con Sanctum todos los métodos excepto index y show
        $this->middleware('auth:sanctum')->except(['index', 'show']);
    }

    /**
     * GET /api/groups
     * - Si el token corresponde a admin@gmail.com devuelve TODOS los grupos
     * - Si no, solo públicos
     */
    public function index(Request $request)
    {
        // 1) Intentar usuario autenticado vía Sanctum
        $user = $request->user();

        // 2) Si no hay sesión pero sí Bearer token, decodifícalo manualmente
        if (! $user && $token = $request->bearerToken()) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                $user = $pat->tokenable;
            }
        }

        // 3) Si es admin@gmail.com → todos; sino solo públicos
        if ($user && $user->email === 'admin@gmail.com') {
            $groups = Group::withCount('members')->get();
        } else {
            $groups = Group::where('is_public', true)
                ->withCount('members')
                ->get();
        }

        return response()->json($groups, 200);
    }

    /**
     * GET /api/groups/{id}
     * Muestra detalle; para privados solo miembros
     */
    public function show(Request $request, $id)
    {
        // 1) Carga el grupo con contador y la relación creator
        $group = Group::withCount('members')
            ->with('creator')    // <-- aquí
            ->findOrFail($id);

        // 2) Obtener usuario igual que en index()
        $user = $request->user();
        if (! $user && ($token = $request->bearerToken())) {
            if ($pat = PersonalAccessToken::findToken($token)) {
                $user = $pat->tokenable;
            }
        }

        // 3) Determinar si es miembro
        $isMember = $user
            ? $group->members()->where('user_id', $user->id)->exists()
            : false;

        // 4) Control de acceso a privados
        if (! $group->is_public && ! $isMember) {
            return response()->json(['message'=>'Acceso denegado'], 403);
        }

        // 5) Devolver grupo + isMember
        return response()->json([
            'group'    => $group,
            'isMember' => $isMember
        ], 200);
    }

    /**
     * POST /api/groups
     * Crea un grupo y une al creador automáticamente.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'        => 'required|string|max:255',
            'description' => 'nullable|string',
            'is_public'   => 'required|boolean',
        ]);

        $user = $request->user();
        $data['created_by'] = $user->id;
        $data['access_key'] = $data['is_public'] ? null : Str::random(8);

        $group = Group::create($data);
        $group->members()->attach($user->id);

        $fresh = Group::withCount('members')->findOrFail($group->id);
        return response()->json($fresh, 201);
    }

    /**
     * PUT /api/groups/{id}
     * Actualiza un grupo (solo su creador).
     */
    public function update(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();

        if ($group->created_by !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }

        $data = $request->validate([
            'name'        => 'sometimes|required|string|max:255',
            'description' => 'nullable|string',
            'is_public'   => 'sometimes|required|boolean',
        ]);

        // Si cambia a privado y no tiene clave, genera una
        if (array_key_exists('is_public', $data)
            && $data['is_public'] === false
            && !$group->access_key
        ) {
            $group->access_key = Str::random(8);
        }

        $group->update($data);
        $fresh = $group->fresh()->loadCount('members');
        return response()->json($fresh, 200);
    }

    /**
     * DELETE /api/groups/{id}
     * Elimina un grupo (solo su creador).
     */
    public function destroy(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();
        $isAdmin = $user && $user->email === 'admin@gmail.com';
        if (! $isAdmin && $group->created_by !== $user->id) {
            return response()->json(['message' => 'No autorizado'], 403);
        }
        $group->delete();
        return response()->json(['message' => 'Grupo eliminado'], 200);
    }

    /**
     * POST /api/groups/{id}/join
     * Se une a un grupo (valida clave para privados).
     */
    public function join(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();

        if (!$group->is_public) {
            $key = $request->input('access_key');
            if (!$key || $key !== $group->access_key) {
                return response()->json(['message' => 'Clave inválida'], 403);
            }
        }

        if ($group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Ya eres miembro'], 409);
        }

        $group->members()->attach($user->id);
        return response()->json(['message' => 'Unido al grupo'], 200);
    }

    /**
     * POST /api/groups/{id}/leave
     * Abandona un grupo; si no quedan miembros elimina el grupo;
     * si quedan, promueve al miembro más antiguo.
     */
    public function leave(Request $request, $id)
    {
        $group = Group::findOrFail($id);
        $user  = $request->user();

        if (!$group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'No eres miembro'], 409);
        }

        $group->members()->detach($user->id);

        $remaining = $group->members()
            ->withPivot('created_at')
            ->orderBy('group_user.created_at', 'asc')
            ->get();

        if ($remaining->isEmpty()) {
            $group->delete();
            return response()->json(['message' => 'Has abandonado; el grupo se eliminó'], 200);
        }

        $newCreator = $remaining->first();
        $group->created_by = $newCreator->id;
        $group->save();

        return response()->json([
            'message'     => 'Has abandonado el grupo',
            'new_creator' => $newCreator->id
        ], 200);
    }

    /**
     * GET /api/my-private-groups
     * Lista los grupos privados del usuario (oculta access_key si no es creador).
     */
    public function myPrivateGroups(Request $request)
    {
        $user = $request->user();
        $groups = $user->groups()
            ->where('is_public', false)
            ->withCount('members')
            ->get()
            ->transform(function ($g) use ($user) {
                if ($g->created_by !== $user->id) {
                    unset($g->access_key);
                }
                return $g;
            });

        return response()->json($groups, 200);
    }

    /**
     * POST /api/groups/join-by-code
     * Une al usuario a un grupo dado su access_key.
     */
    public function joinByCode(Request $request)
    {
        $data = $request->validate([
            'access_key' => 'required|string'
        ]);

        $user  = $request->user();
        $group = Group::where('access_key', $data['access_key'])->first();
        if (! $group) {
            return response()->json(['message' => 'Clave de grupo inválida.'], 404);
        }
        if ($group->members()->where('user_id', $user->id)->exists()) {
            return response()->json(['message' => 'Ya eres miembro de este grupo.'], 409);
        }

        $group->members()->attach($user->id);
        return response()->json([
            'message' => "Te has unido al grupo '{$group->name}' exitosamente."
        ], 200);
    }
}
