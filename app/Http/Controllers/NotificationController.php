<?php

namespace App\Http\Controllers;

use App\Http\Resources\NotificationResource;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Notifications\DatabaseNotification;
use Inertia\Inertia;
use Inertia\Response;

class NotificationController extends Controller
{
    /**
     * The number of notifications shown in the bell's panel.
     */
    public const int PANEL_SIZE = 20;

    /**
     * The number of notifications loaded at a time on the notifications page.
     */
    public const int PAGE_SIZE = 20;

    /**
     * Show the signed-in user's notifications, all of them or only the unread ones.
     *
     * The bell's panel asks for JSON and gets the latest few; a visit to the page gets them a page at a time.
     */
    public function index(Request $request): JsonResponse|Response
    {
        $user = $request->user();
        $filter = $request->input('filter') === 'unread' ? 'unread' : 'all';
        $notifications = $filter === 'unread' ? $user->unreadNotifications() : $user->notifications();

        if ($request->expectsJson()) {
            return response()->json([
                'data' => NotificationResource::collection($notifications->limit(self::PANEL_SIZE)->get())->resolve($request),
                'unread_count' => $user->unreadNotifications()->count(),
            ]);
        }

        return Inertia::render('Notifications/Index', [
            'filter' => $filter,
            'notifications' => Inertia::scroll(fn () => $notifications
                ->paginate(self::PAGE_SIZE)
                ->withQueryString()
                ->through(fn (DatabaseNotification $notification): array => NotificationResource::make($notification)->resolve($request))),
        ]);
    }
}
