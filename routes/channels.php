<?php

use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Support\Facades\Broadcast;

// A user's own notifications: nobody else may listen in.
Broadcast::channel('App.Models.User.{id}', fn (User $user, string $id): bool => $user->id === $id);

// Live updates for the forum feed: anyone allowed to browse the forum may follow it.
Broadcast::channel('forum', fn (User $user): bool => $user->can('viewAny', ForumThread::class));

// Live updates for one thread's conversation: anyone allowed to read the thread may follow it.
Broadcast::channel('forum.thread.{thread}', fn (User $user, ForumThread $thread): bool => $user->can('view', $thread));
