<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AccountController extends Controller
{
    /**
     * Display the signed-in user's LRMIS account details.
     */
    public function __invoke(Request $request): Response
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
        ]);
    }
}
