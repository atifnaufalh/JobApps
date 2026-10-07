<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        if (! $request->user()) {
            return $request->expectsJson()
                ? response()->json(['error' => 'Silakan masuk sebagai admin.'], 401)
                : redirect()->route('admin.login');
        }

        if ($request->user()->role !== 'admin') {
            return response()->json(['error' => 'Akses khusus admin.'], 403);
        }

        return $next($request);
    }
}
