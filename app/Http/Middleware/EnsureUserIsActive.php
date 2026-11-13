<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        if (Auth::check()) {
            $user = Auth::user();

            // Admin is always exempt from deactivation ("exper roll Admin")
            if (! $user->isAdmin() && ! $user->isActive()) {
                Auth::logout();

                $request->session()->invalidate();
                $request->session()->regenerateToken();

                if ($request->expectsJson() || $request->ajax()) {
                    return response()->json([
                        'message' => 'Your account has been deactivated. Please contact an administrator.',
                        'redirect' => route('login'),
                    ], 403);
                }

                return redirect()->route('login')->withErrors([
                    'username' => 'Your account has been deactivated. Please contact an administrator.',
                ]);
            }
        }

        return $next($request);
    }
}

