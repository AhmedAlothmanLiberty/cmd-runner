<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class InternalBasicAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->getUser();
        $pass = $request->getPassword();

        $okUser = hash_equals((string) env('INTERNAL_BASIC_USER'), (string) $user);
        $okPass = hash_equals((string) env('INTERNAL_BASIC_PASS'), (string) $pass);

        if (!$okUser || !$okPass) {
            return response('Unauthorized', 401, [
                'WWW-Authenticate' => 'Basic realm="CMD Runner"',
            ]);
        }

        return $next($request);
    }
}
