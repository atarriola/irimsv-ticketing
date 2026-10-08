<?php

namespace App\Http\Controllers;

use App\Http\Requests\UpdateNotificationPreferencesRequest;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AccountNotificationController extends Controller
{
    /**
     * Turn the signed-in user's email notifications on or off.
     */
    public function update(UpdateNotificationPreferencesRequest $request): RedirectResponse
    {
        $wantsEmail = $request->boolean('email_notifications');

        $request->user()->preferences()->updateOrCreate([], ['email_notifications' => $wantsEmail]);

        Inertia::flash('toast', ['type' => 'success', 'message' => $wantsEmail
            ? 'You will get an email when something happens to your tickets.'
            : 'You will only be told in the app from now on.']);

        return back();
    }
}
