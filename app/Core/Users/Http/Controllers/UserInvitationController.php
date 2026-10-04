<?php

namespace App\Core\Users\Http\Controllers;

use App\Core\Users\Models\UserInvitation;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\ValidationException;
use Inertia\Inertia;
use Inertia\Response;

class UserInvitationController
{
    public function accept(string $token): Response
    {
        $invitation = UserInvitation::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $token))
            ->first();

        if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
            abort(404);
        }

        return Inertia::render('Auth/AcceptInvitation', [
            'token' => $token,
            'email' => $invitation->user->email,
            'name' => $invitation->user->name,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'token' => ['required', 'string'],
            'password' => ['required', 'string', 'min:8', 'confirmed'],
        ]);

        $invitation = UserInvitation::query()
            ->with('user')
            ->where('token_hash', hash('sha256', $data['token']))
            ->first();

        if (! $invitation || $invitation->isAccepted() || $invitation->isExpired()) {
            throw ValidationException::withMessages([
                'token' => 'دعوت‌نامه نامعتبر یا منقضی شده است.',
            ]);
        }

        $invitation->user->update([
            'password' => Hash::make($data['password']),
            'status' => 'active',
        ]);

        $invitation->update([
            'accepted_at' => now(),
        ]);

        return Redirect::route('login')->with('status', 'حساب کاربری فعال شد. اکنون می‌توانید وارد شوید.');
    }
}
