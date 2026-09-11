<?php

namespace App\Core\Middleware;

use App\Core\Request;
use App\Core\Response;

class AuthMiddleware implements MiddlewareInterface
{
    public function handle(Request $request, callable $next): Response
    {
        if (empty($_SESSION['user_id'])) {
            return Response::html('Unauthorized', 401);
        }

        return $next($request);
    }
}
