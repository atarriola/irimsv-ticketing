<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\UpdateUserRoleRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class UserRoleController extends Controller
{
    /**
     * Give an LRMIS account a helpdesk role.
     */
    public function update(UpdateUserRoleRequest $request, User $user): RedirectResponse
    {
        $user->assignRole($request->enum('role', UserRole::class));

        $description = $user->isAdmin() ? 'an administrator' : 'a member';

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name} is now {$description}."]);

        return back();
    }
}
