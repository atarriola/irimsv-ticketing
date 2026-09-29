<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\ResetUserPasswordRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class UserPasswordController extends Controller
{
    /**
     * Display the form for setting a new password on an LRMIS account.
     */
    public function edit(User $user): Response
    {
        Gate::authorize('resetPassword', $user);

        return Inertia::render('Admin/Users/Password', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'position' => $user->position,
            ],
        ]);
    }

    /**
     * Set a new password on the account. Rotating the remember token, together
     * with the session middleware noticing the new hash, signs the account out
     * of every browser it is logged in to.
     */
    public function update(ResetUserPasswordRequest $request, User $user): RedirectResponse
    {
        $user->password = $request->validated('password');
        $user->setRememberToken(Str::random(60));
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "The password of {$user->name} has been reset."]);

        return redirect()->route('admin.users.index');
    }
}
