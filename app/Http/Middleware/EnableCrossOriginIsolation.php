<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnableCrossOriginIsolation
{
    /**
     * Handle an incoming request.
     * Attach COOP & COEP headers to enable SharedArrayBuffer and multi-threaded WebAssembly WebCodecs decoding.
     */
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Enable Cross-Origin Isolation for high-performance WebAssembly WebCodecs player
        $response->headers->set('Cross-Origin-Opener-Policy', 'same-origin');
        $response->headers->set('Cross-Origin-Embedder-Policy', 'credentialless');

        return $response;
    }
}
