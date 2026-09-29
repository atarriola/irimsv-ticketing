<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateAccountRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the signed-in user's LRMIS account details.
     */
    public function show(Request $request): Response
    {
        $user = $request->user();

        return Inertia::render('Account/Show', [
            'account' => [
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
                'position' => $user->usertype->type_name,
                'status' => $user->status->label(),
            ],
            'can' => [
                'update' => $request->user()->can('update', $user),
            ],
        ]);
    }

    /**
     * Display the form for editing the signed-in administrator's own details and password.
     */
    public function edit(Request $request): Response
    {
        $user = $request->user();

        Gate::authorize('update', $user);

        return Inertia::render('Account/Edit', [
            'account' => [
                'firstname' => $user->firstname,
                'middlename' => $user->middlename,
                'lastname' => $user->lastname,
                'extension_name' => $user->extension_name,
                'username' => $user->username,
                'email' => $user->email,
                'contact_number' => $user->contact_number,
            ],
        ]);
    }

    /**
     * Update the signed-in administrator's own details.
     */
    public function update(UpdateAccountRequest $request): RedirectResponse
    {
        $request->user()->update($request->accountAttributes());

        Inertia::flash('toast', ['type' => 'success', 'message' => 'Your account details have been saved.']);

        return redirect()->route('account.edit');
    }
}
