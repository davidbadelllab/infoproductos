<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ApifyCredentialsController extends Controller
{
    /**
     * Mostrar formulario de credenciales de Apify
     */
    public function edit()
    {
        return Inertia::render('settings/credentials/apify', [
            'apify_api_token' => auth()->user()->apify_api_token,
            'apify_actor_id' => auth()->user()->apify_actor_id,
        ]);
    }

    /**
     * Actualizar credenciales de Apify
     */
    public function update(Request $request)
    {
        $validated = $request->validate([
            'apify_api_token' => 'required|string|max:255',
            'apify_actor_id' => 'required|string|max:255',
        ]);

        $request->user()->update([
            'apify_api_token' => $validated['apify_api_token'],
            'apify_actor_id' => $validated['apify_actor_id'],
        ]);

        return back()->with('success', 'Credenciales actualizadas correctamente');
    }
}
