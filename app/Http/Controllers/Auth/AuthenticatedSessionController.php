<?php

declare(strict_types=1);

namespace App\Http\Controllers\Auth;

use App\Domains\Administration\Actions\RecordAuditEntry;
use App\Domains\Administration\DTOs\AuditEntry;
use App\Domains\Administration\Models\User;
use App\Http\Controllers\Controller;
use App\Http\Requests\LoginRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AuthenticatedSessionController extends Controller
{
    public function create(Request $request): Response
    {
        return Inertia::render('Auth/Login', [
            'status' => $request->session()->get('status'),
        ]);
    }

    public function store(LoginRequest $request, RecordAuditEntry $audit): RedirectResponse
    {
        $request->authenticate();

        $request->session()->regenerate();

        /** @var User $user */
        $user = $request->user();
        $user->forceFill(['last_login_at' => now()])->save();

        $audit->handle(new AuditEntry(action: 'auth.login', section: 'auth'));

        return redirect()->intended('/');
    }

    public function destroy(Request $request, RecordAuditEntry $audit): RedirectResponse
    {
        $audit->handle(new AuditEntry(action: 'auth.logout', section: 'auth'));

        auth()->guard('web')->logout();

        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/login');
    }
}
