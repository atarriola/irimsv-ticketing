<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display the LRMIS accounts with their helpdesk roles.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $search = $request->string('q')->trim()->toString();

        $users = User::query()
            ->select(['id', 'firstname', 'lastname', 'extension_name', 'username', 'email', 'status', 'usertype_id', 'created_at'])
            ->with('usertype:id,type_name')
            ->withCount('tickets')
            ->when($search !== '', fn (Builder $query) => $query->search($search))
            ->orderBy('lastname')
            ->orderBy('firstname')
            ->orderBy('id')
            ->paginate(15)
            ->withQueryString()
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'username' => $user->username,
                'email' => $user->email,
                'position' => $user->usertype->type_name,
                'status' => $user->status->label(),
                'is_active' => $user->isActive(),
                'is_admin' => $user->isAdmin(),
                'tickets_count' => $user->tickets_count,
                'created_at' => $user->created_at?->toFormattedDateString(),
                'can' => [
                    'changeRole' => $request->user()->can('changeRole', $user),
                ],
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'filters' => ['q' => $search],
        ]);
    }
}
