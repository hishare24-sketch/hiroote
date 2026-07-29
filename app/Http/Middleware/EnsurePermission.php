<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Domains\Administration\Enums\Permission;
use Closure;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Symfony\Component\HttpFoundation\Response;

/**
 * Route-level permission check: `->middleware('permission:providers.manage')`.
 *
 * An unknown permission string is a programming error, not a denial — it
 * throws loudly rather than silently locking everyone out of a route.
 */
final class EnsurePermission
{
    public function handle(Request $request, Closure $next, string ...$permissions): Response
    {
        foreach ($permissions as $name) {
            $permission = Permission::tryFrom($name);

            if ($permission === null) {
                throw new InvalidArgumentException("Unknown permission [{$name}].");
            }

            if ($request->user()?->cannot($permission->value) !== false) {
                throw new AuthorizationException;
            }
        }

        return $next($request);
    }
}
