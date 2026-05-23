<?php

namespace App\Http\Middleware;

use App\Services\PerformanceLogBuffer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class FlushPerformanceLogMiddleware
{
    public function handle(Request $request, Closure $next): Response
    {
        return $next($request);
    }

    public function terminate(Request $request, Response $response): void
    {
        PerformanceLogBuffer::flush();
    }
}
