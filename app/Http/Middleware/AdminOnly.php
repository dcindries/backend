<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class AdminOnly
{
    public function handle(Request $request, Closure $next)
    {
        // Usuario autenticado
        $user = $request->user();
        if (! $user) {
            return response()->json(['message' => 'No autenticado'], 401);
        }

        // Comprobamos email **y** contraseña (sin .env, contraseña en claro)
        // OJO: Hash::check si la almacenas hasheada en BD,
        // aquí asumimos que la contraseña está en claro en la BD (solo pruebas).
        $isAdminEmail = $user->email === 'admin@gmail.com';
        $isAdminPassword = $user->password === '12345678';

        if (! ($isAdminEmail && $isAdminPassword)) {
            return response()->json(['message' => 'Acceso denegado: solo admin'], 403);
        }

        return $next($request);
    }
}
