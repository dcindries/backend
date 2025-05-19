<?php
// app/Http/Controllers/UserController.php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Routing\Controller as BaseController;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use App\Models\User;

class UserController extends BaseController
{
    public function __construct()
    {
        // Protegemos con Sanctum todos menos index() y store()
        $this->middleware('auth:sanctum')->except(['index','store']);
    }

    /**
     * GET /api/users
     * Listado de todos los usuarios.
     */
    public function index()
    {
        return response()->json(User::all(), 200);
    }

    /**
     * POST /api/users
     * Registrar nuevo usuario.
     */
    public function store(Request $request)
    {
        $data = $request->validate([
            'name'     => 'required|string|max:255',
            'email'    => 'required|email|unique:users,email',
            'password' => 'required|string|min:6',
            'role'     => 'sometimes|in:user,admin',
        ]);

        $data['password'] = Hash::make($data['password']);
        $user = User::create($data);

        return response()->json($user, 201);
    }

    /**
     * GET /api/user
     * Devuelve los datos del usuario autenticado.
     */
    public function profile(Request $request)
    {
        return response()->json($request->user(), 200);
    }

    /**
     * PUT /api/user
     * Actualizar el propio perfil (name, email, foto).
     */
    public function updateProfile(Request $request)
    {
        $user = $request->user();

        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'sometimes|required|email|unique:users,email,'.$user->id,
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        if ($request->hasFile('profile_photo')) {
            // Borra la antigua
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            // Sube y guarda en la columna ‘profile_photo_path’
            $path = $request->file('profile_photo')->store('profile_photos','public');
            $user->profile_photo_path = $path;
        }

        if (isset($data['name']))  $user->name  = $data['name'];
        if (isset($data['email'])) $user->email = $data['email'];

        $user->save();

        return response()->json($user->fresh(), 200);
    }



    /**
     * PUT /api/users/{id}
     * Actualizar usuario (admin).
     */
    public function update(Request $request, $id)
    {
        $user = User::findOrFail($id);

        $data = $request->validate([
            'name'          => 'sometimes|required|string|max:255',
            'email'         => 'sometimes|required|email|unique:users,email,'.$id,
            'password'      => 'nullable|string|min:6',
            'profile_photo' => 'nullable|image|max:2048',
        ]);

        // Si llega nueva contraseña, la hasheamos
        if (! empty($data['password'])) {
            $user->password = Hash::make($data['password']);
        }

        // Si llega nueva foto, eliminamos la antigua y subimos la nueva
        if ($request->hasFile('profile_photo')) {
            if ($user->profile_photo_path) {
                Storage::disk('public')->delete($user->profile_photo_path);
            }
            $path = $request->file('profile_photo')
                ->store('profile_photos', 'public');
            $user->profile_photo_path = $path;
        }

        // Name / Email
        if (isset($data['name']))  $user->name  = $data['name'];
        if (isset($data['email'])) $user->email = $data['email'];

        $user->save();

        return response()->json($user->fresh(), 200);
    }

    public function show($id)
    {
        $user = User::findOrFail($id);
        return response()->json($user, 200);
    }



    /**
     * DELETE /api/users/{id}
     * Eliminar usuario (admin).
     */
    public function destroy($id)
    {
        User::findOrFail($id)->delete();
        return response()->json(null, 204);
    }
}
