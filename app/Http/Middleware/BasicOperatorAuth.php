<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Optional HTTP Basic gate for the single operator. Disabled when
 * AUTOCLIP_BASIC_USER / AUTOCLIP_BASIC_PASS are not both set.
 */
class BasicOperatorAuth
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = (string) config('autoclip.access.user');
        $pass = (string) config('autoclip.access.password');

        if ($user === '' || $pass === '') {
            return $next($request);
        }

        $givenUser = (string) $request->getUser();
        $givenPass = (string) $request->getPassword();

        if (! hash_equals($user, $givenUser) || ! hash_equals($pass, $givenPass)) {
            return response('Unauthorized', 401, ['WWW-Authenticate' => 'Basic realm="ClipForge"']);
        }

        return $next($request);
    }
}
