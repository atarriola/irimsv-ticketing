<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountPasswordRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AccountPasswordController extends Controller
{
    /**
     * Change the signed-in user's password.
     */
    public function update(UpdateAccountPasswordRequest $request): RedirectResponse
    {
        $request->user()->update(['password' => $request->validated('password')]);

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your password has been changed.']);

        return redirect()->route('account.edit');
    }
}
