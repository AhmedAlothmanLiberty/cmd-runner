<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Symfony\Component\HttpFoundation\Response;

class TrackUserActivity
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        if (! auth()->check()) {
            return $response;
        }

        $user = $request->user();
        $lastSeen = $request->session()->get('last_seen_at');
        $shouldUpdate = true;
        if ($lastSeen) {
            $lastSeenAt = $lastSeen instanceof \Carbon\CarbonInterface
                ? $lastSeen
                : Carbon::parse($lastSeen);
            $diffMinutes = now()->diffInMinutes($lastSeenAt, false);
            $shouldUpdate = $diffMinutes < 0 || $diffMinutes >= 2;
        }

        if ($shouldUpdate) {
            $user->forceFill(['last_seen_at' => now()])->save();
            $request->session()->put('last_seen_at', now());
        }

        return $response;
    }
}
