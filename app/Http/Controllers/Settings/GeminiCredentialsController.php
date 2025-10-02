<?php

namespace App\Http\Controllers\Settings;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;

class GeminiCredentialsController extends Controller
{
    public function edit()
    {
        $user = auth()->user();
        $user->makeVisible('gemini_api_key');

        return Inertia::render('settings/credentials/gemini', [
            'gemini_api_key' => $user->gemini_api_key,
        ]);
    }

    public function update(Request $request)
    {
        $validated = $request->validate([
            'gemini_api_key' => 'required|string|max:255',
        ]);

        $request->user()->update([
            'gemini_api_key' => $validated['gemini_api_key'],
        ]);

        return back()->with('success', 'Credenciales de Gemini actualizadas correctamente');
    }
}


