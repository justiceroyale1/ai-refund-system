<?php

namespace App\Http\Middleware;

use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveBroadcastIdentity
{
    public function __construct(private readonly ResolveDemoCustomer $resolveDemoCustomer) {}

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->header(ResolveDemoCustomer::HEADER) !== null) {
            return $this->resolveDemoCustomer->handle($request, $next);
        }

        if ($request->user('web') instanceof User) {
            return $next($request);
        }

        return $this->resolveDemoCustomer->handle($request, $next);
    }
}
