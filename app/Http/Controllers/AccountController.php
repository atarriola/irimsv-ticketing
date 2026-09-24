<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the signed-in user's account settings.
     */
    public function edit(Request $request): Response
    {
        return Inertia::render('Account/Edit', [
            'account' => $request->user()->only(['name', 'email']),
        ]);
    }

    /**
     * Update the signed-in user's name and email address.
     */
    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $request->user()->update($request->validated());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your details have been updated.']);

        return redirect()->route('account.edit');
    }
}
