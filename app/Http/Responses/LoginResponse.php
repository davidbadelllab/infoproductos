<?php

namespace App\Http\Responses;

use Illuminate\Http\JsonResponse;
use Inertia\Inertia;
use Laravel\Fortify\Contracts\LoginResponse as LoginResponseContract;

class LoginResponse implements LoginResponseContract
{
    public function toResponse($request)
    {
        // Usar Inertia::location() para forzar recarga completa del navegador
        // Esto actualiza el meta tag CSRF token
        return Inertia::location(config('fortify.home', '/'));
    }
}
