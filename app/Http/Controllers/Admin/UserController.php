<?php

namespace App\Http\Controllers\Admin;

use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreUserRequest;
use App\Http\Requests\Admin\UpdateUserRequest;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Inertia\Inertia;
use Inertia\Response;

class UserController extends Controller
{
    /**
     * Display the accounts.
     */
    public function index(Request $request): Response
    {
        Gate::authorize('viewAny', User::class);

        $users = User::query()
            ->withCount('tickets')
            ->orderBy('name')
            ->orderBy('id')
            ->paginate(15)
            ->through(fn (User $user): array => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'is_admin' => $user->isAdmin(),
                'tickets_count' => $user->tickets_count,
                'created_at' => $user->created_at->toFormattedDateString(),
                'can' => [
                    'delete' => $request->user()->can('delete', $user),
                ],
            ]);

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
        ]);
    }

    /**
     * Display the form for creating an account.
     */
    public function create(): Response
    {
        Gate::authorize('create', User::class);

        return Inertia::render('Admin/Users/Form', [
            'account' => null,
            'roles' => $this->roleOptions(),
            'canChangeRole' => true,
        ]);
    }

    /**
     * Create an account.
     */
    public function store(StoreUserRequest $request): RedirectResponse
    {
        $user = new User($request->safe()->only(['name', 'email', 'password']));
        $user->role = $request->enum('role', UserRole::class);
        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "An account for {$user->name} has been created."]);

        return redirect()->route('admin.users.index');
    }

    /**
     * Display the form for editing an account.
     */
    public function edit(Request $request, User $user): Response
    {
        Gate::authorize('update', $user);

        return Inertia::render('Admin/Users/Form', [
            'account' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'role' => $user->role->value,
            ],
            'roles' => $this->roleOptions(),
            'canChangeRole' => $request->user()->can('changeRole', $user),
        ]);
    }

    /**
     * Update an account, resetting its password only when a new one is given.
     */
    public function update(UpdateUserRequest $request, User $user): RedirectResponse
    {
        $user->fill($request->safe()->only(['name', 'email']));
        $user->role = $request->enum('role', UserRole::class);

        if ($request->filled('password')) {
            $user->password = $request->validated('password');
        }

        $user->save();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name}'s account has been updated."]);

        return redirect()->route('admin.users.index');
    }

    /**
     * Delete an account together with everything it owns.
     */
    public function destroy(User $user): RedirectResponse
    {
        Gate::authorize('delete', $user);

        $user->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => "{$user->name}'s account has been deleted."]);

        return redirect()->route('admin.users.index');
    }

    /**
     * Get the roles an account can be given.
     *
     * @return list<array{value: string, label: string}>
     */
    private function roleOptions(): array
    {
        return [
            ['value' => UserRole::User->value, 'label' => 'Member'],
            ['value' => UserRole::Admin->value, 'label' => 'Administrator'],
        ];
    }
}
