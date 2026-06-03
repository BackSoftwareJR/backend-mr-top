<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use App\Enums\UserType;
use App\Models\User;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class RoleMiddleware
{
    /**
     * @param  Closure(Request): Response  $next
     */
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        if (! $user instanceof User) {
            return response()->json([
                'success' => false,
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Autenticazione richiesta.',
                ],
            ], Response::HTTP_UNAUTHORIZED);
        }

        foreach ($roles as $role) {
            if ($this->userHasRole($user, $role)) {
                return $next($request);
            }
        }

        return response()->json([
            'success' => false,
            'error' => [
                'code' => 'FORBIDDEN',
                'message' => 'Non hai i permessi per accedere a questa risorsa.',
            ],
        ], Response::HTTP_FORBIDDEN);
    }

    private function userHasRole(User $user, string $role): bool
    {
        return match ($role) {
            'consumer' => $user->user_type === UserType::Consumer,
            'partner' => $user->user_type === UserType::B2b
                || $user->roles()->whereIn('name', ['partner', 'partner_owner', 'partner_staff'])->exists(),
            'superadmin' => $user->user_type === UserType::Superadmin
                || $user->roles()->whereIn('name', ['superadmin', 'super_admin'])->exists(),
            default => $user->roles()->where('name', $role)->exists(),
        };
    }
}
