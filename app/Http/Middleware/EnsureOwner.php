<?php

namespace App\Http\Middleware;

use App\Services\OwnerAccess;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

class EnsureOwner
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! OwnerAccess::allows($request->user()->email)) {
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();
            abort(403);
        }

        return $next($request);
    }
}
