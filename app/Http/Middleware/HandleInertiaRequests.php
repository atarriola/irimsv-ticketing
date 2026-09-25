<?php

namespace App\Http\Middleware;

use Illuminate\Http\Request;
use Inertia\Middleware;

class HandleInertiaRequests extends Middleware
{
    /**
     * The root template that's loaded on the first page visit.
     *
     * @see https://inertiajs.com/server-side-setup#root-template
     *
     * @var string
     */
    protected $rootView = 'app';

    /**
     * Determines the current asset version.
     *
     * @see https://inertiajs.com/asset-versioning
     */
    public function version(Request $request): ?string
    {
        return parent::version($request);
    }

    /**
     * Define the props that are shared by default.
     *
     * @see https://inertiajs.com/shared-data
     *
     * @return array<string, mixed>
     */
    public function share(Request $request): array
    {
        return [
            ...parent::share($request),
            'auth' => [
                'user' => $this->authenticatedUser($request),
            ],
            // The badge on the notification bell; the layout polls this when no WebSocket is connected.
            // Until the notifications table has been migrated the count is simply zero, so no page breaks.
            'unreadNotifications' => fn (): int => rescue(fn (): int => $request->user()?->unreadNotifications()->count() ?? 0, 0, report: false),
        ];
    }

    /**
     * Get the signed-in user's details that are safe to expose to the client.
     *
     * @return array{id: string, name: string, email: string, position: string, photo_url: string|null, is_admin: bool}|null
     */
    private function authenticatedUser(Request $request): ?array
    {
        $user = $request->user();

        if ($user === null) {
            return null;
        }

        return [
            'id' => $user->id,
            'name' => $user->name,
            'email' => $user->email,
            'position' => $user->position,
            'photo_url' => $user->photo_url,
            'is_admin' => $user->isAdmin(),
        ];
    }
}
