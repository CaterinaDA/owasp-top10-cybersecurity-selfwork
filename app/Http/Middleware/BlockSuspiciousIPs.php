<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Symfony\Component\HttpFoundation\Response;

class BlockSuspiciousIPs
{
    public function handle(Request $request, Closure $next): Response
    {
        $ip = $request->ip();
        $key = 'comments_flood_' . $ip;
        $maxAttempts = 3;
        $decayMinutes = 1;

        $attempts = Cache::get($key, 0);

        if ($attempts >= $maxAttempts) {
            return response()->json([
                'message' => 'Too many comments. Please wait before trying again.'
            ], 429);
        }

        Cache::put($key, $attempts + 1, now()->addMinutes($decayMinutes));

        return $next($request);
    }
}
