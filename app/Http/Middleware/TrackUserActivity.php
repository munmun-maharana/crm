<?php

namespace App\Http\Middleware;

use App\Models\UserActivityLog;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        try {
            $user = $request->user();
            if (! $user || $user->isAdmin()) {
                return $response;
            }

            $path = '/' . ltrim($request->path(), '/');
            if (str_starts_with($path, '/activity-track') || $path === '/up') {
                return $response;
            }

            $isApi = str_starts_with($path, '/api/');
            $method = strtoupper($request->method());

            // Log non-GET web requests and all API requests server-side;
            // GET web page views, duration on page, clicks & selects are logged by the frontend tracker.
            if ($isApi || $method !== 'GET') {
                $routeName = $request->route()?->getName();
                UserActivityLog::create([
                    'user_id' => $user->id,
                    'user_role' => (string) $user->role,
                    'session_id' => $request->hasSession() ? $request->session()->getId() : null,
                    'event_type' => $isApi ? 'api' : 'submit',
                    'page_path' => mb_substr($path, 0, 255),
                    'page_title' => $routeName ?: null,
                    'target_label' => $routeName ?: ($method . ' ' . $path),
                    'target_value' => $this->summarizeInput($request),
                    'method' => $method,
                    'duration_seconds' => 0,
                    'ip_address' => $request->ip(),
                    'user_agent' => mb_substr((string) $request->userAgent(), 0, 255),
                ]);
            }
        } catch (\Throwable) {
            // Never disrupt user requests if activity logging fails
        }

        return $response;
    }

    private function summarizeInput(Request $request): ?string
    {
        $keys = array_keys($request->except(['_token', '_method', 'password', 'password_confirmation', 'current_password']));
        if (empty($keys)) {
            return null;
        }

        return mb_substr('Fields: ' . implode(', ', array_slice($keys, 0, 12)), 0, 255);
    }
}
