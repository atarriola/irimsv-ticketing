<?php

namespace App\Http\Controllers;

use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationReadController extends Controller
{
    /**
     * Mark every one of the signed-in user's notifications as read.
     */
    public function store(Request $request): JsonResponse
    {
        $request->user()->unreadNotifications()->update(['read_at' => now()]);

        return response()->json(['unread_count' => 0]);
    }

    /**
     * Mark one of the signed-in user's notifications as read, and return how many remain unread.
     */
    public function update(Request $request, string $notification): JsonResponse
    {
        $user = $request->user();

        $user->notifications()->findOrFail($notification)->markAsRead();

        return response()->json(['unread_count' => $user->unreadNotifications()->count()]);
    }
}
