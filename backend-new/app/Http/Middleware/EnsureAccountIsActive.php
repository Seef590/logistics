<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAccountIsActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();

        if ($user?->is_banned) {
            return response()->json(['error' => 'Compte suspendu définitivement'], 403);
        }

        if ($user?->suspended_until?->isFuture()) {
            return response()->json(['error' => 'Compte suspendu temporairement'], 403);
        }

        return $next($request);
    }
}
