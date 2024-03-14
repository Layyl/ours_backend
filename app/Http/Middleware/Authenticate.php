<?php

namespace App\Http\Middleware;

use Illuminate\Auth\Middleware\Authenticate as Middleware;
use Illuminate\Http\Request;
use Illuminate\Auth\AuthenticationException;
use Symfony\Component\HttpFoundation\Response;

class Authenticate extends Middleware
{
    /**
     * Handle an unauthenticated user.
     *
     * @param  \Illuminate\Http\Request  $request
     * @param  array  $guards
     * @return void
     *
     * @throws \Illuminate\Auth\AuthenticationException
     */
    protected function redirectTo($request)
    {
        if ($request->expectsJson()) {
            throw new AuthenticationException('Unauthenticated.', []);
        }

        abort(Response::HTTP_UNAUTHORIZED, 'Unauthorized');
    }
}
