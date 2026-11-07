<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Registra las suscripciones del navegador (Web Push).
 *
 * El navegador entrega un `endpoint` y las llaves con las que el servidor
 * cifra el mensaje; no hay nada guardable sin consentimiento del usuario.
 */
class PushSubscriptionController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url'],
            'keys.p256dh' => ['required', 'string'],
            'keys.auth' => ['required', 'string'],
            'content_encoding' => ['sometimes', 'string'],
        ]);

        $request->user()->updatePushSubscription(
            $validated['endpoint'],
            $validated['keys']['p256dh'],
            $validated['keys']['auth'],
            $validated['content_encoding'] ?? null,
        );

        return response()->json(['status' => 'suscripcion-registrada']);
    }

    public function destroy(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'endpoint' => ['required', 'url'],
        ]);

        $request->user()->deletePushSubscription($validated['endpoint']);

        return response()->json(['status' => 'suscripcion-eliminada']);
    }
}
