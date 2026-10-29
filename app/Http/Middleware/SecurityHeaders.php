<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class SecurityHeaders
{
    // Todo el CSS y JS se sirve desde public/, sin código inline ni recursos externos.
    private const CSP = "default-src 'self'; script-src 'self'; style-src 'self'; img-src 'self' data:; manifest-src 'self'; worker-src 'self'; connect-src 'self'; form-action 'self'; frame-ancestors 'none'; base-uri 'self'; object-src 'none'";

    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $response->headers->set('Content-Security-Policy', self::CSP);
        $response->headers->set('X-Content-Type-Options', 'nosniff');
        $response->headers->set('X-Frame-Options', 'DENY');
        $response->headers->set('Referrer-Policy', 'same-origin');
        $response->headers->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=()');
        if ($request->isSecure()) $response->headers->set('Strict-Transport-Security', 'max-age=31536000');
        // Las páginas con datos financieros no se guardan en el navegador ni se muestran con "atrás" después de cerrar sesión.
        if ($request->user()) $response->headers->set('Cache-Control', 'no-store, private');
        return $response;
    }
}
