<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

class EnsureUserHasRole
{
    public function handle(Request $request, Closure $next, string ...$roles): Response
    {
        $user = $request->user();

        abort_unless($user && $user->is_active, 403, 'Akun pengguna sedang dinonaktifkan.');
        abort_unless($user && in_array($user->role, $roles, true), 403, 'Anda tidak memiliki akses ke halaman ini.');

        if ($user->role !== 'admin_pusat') {
            abort_unless($user->branch_id, 403, 'Akun belum ditautkan ke cabang sekolah.');
        }

        return $next($request);
    }
}
