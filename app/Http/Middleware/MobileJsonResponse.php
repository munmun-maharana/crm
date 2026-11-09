<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\ViewErrorBag;
use Symfony\Component\HttpFoundation\Response;

/**
 * Lets the mobile API (routes/api/v1.php) reuse the exact same web controllers
 * as the Laravel website. Web controllers answer with `back()->with('success')`
 * or `back()->withErrors()`; this middleware turns those redirects into JSON so
 * the Flutter app gets identical business logic with a JSON contract.
 */
class MobileJsonResponse
{
    public function handle(Request $request, Closure $next): Response
    {
        // Always behave like an API client (JSON validation errors, JSON 403/404).
        $request->headers->set('Accept', 'application/json');
        $request->headers->set('X-Requested-With', 'XMLHttpRequest');

        // Stateless in-memory session so flash()/withErrors() in web controllers work.
        config(['session.driver' => 'array']);
        $session = app('session')->driver('array');
        $session->start();
        app()->instance('session.store', $session);
        $request->setLaravelSession($session);
        app('redirect')->setSession($session);

        $response = $next($request);

        if ($response instanceof RedirectResponse) {
            $errors = $session->get('errors');
            if ($errors instanceof ViewErrorBag && $errors->any()) {
                $bag = $errors->getBag('default')->any() ? $errors->getBag('default') : collect($errors->getBags())->first();

                return new JsonResponse([
                    'ok' => false,
                    'message' => $bag->first(),
                    'errors' => $bag->toArray(),
                ], 422);
            }

            return new JsonResponse([
                'ok' => true,
                'message' => (string) ($session->get('success') ?? 'Saved.'),
            ]);
        }

        return $response;
    }
}

