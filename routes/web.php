<?php

use App\Http\Controllers\AccountController;
use App\Http\Controllers\AccountPasswordController;
use App\Http\Controllers\Admin\CategoryController as AdminCategoryController;
use App\Http\Controllers\Admin\ForumTopicController as AdminForumTopicController;
use App\Http\Controllers\Admin\UserController as AdminUserController;
use App\Http\Controllers\Auth\AuthenticatedSessionController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ForumReplyController;
use App\Http\Controllers\ForumReplyReactionController;
use App\Http\Controllers\ForumThreadController;
use App\Http\Controllers\ForumThreadModerationController;
use App\Http\Controllers\ForumThreadReactionController;
use App\Http\Controllers\TicketCommentController;
use App\Http\Controllers\TicketController;
use App\Http\Controllers\TicketStatusController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/', [AuthenticatedSessionController::class, 'create'])->name('login');
    Route::get('/login', fn () => redirect()->route('login'));
    Route::post('/login', [AuthenticatedSessionController::class, 'store'])->name('login.store');
});

Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthenticatedSessionController::class, 'destroy'])->name('logout');

    Route::get('/dashboard', DashboardController::class)->name('dashboard');
    Route::resource('tickets', TicketController::class)->whereNumber('ticket')->middlewareFor('store', 'throttle:posting');
    Route::patch('/tickets/{ticket}/status', [TicketStatusController::class, 'update'])->whereNumber('ticket')->name('tickets.status.update');
    Route::get('/tickets/{ticket}/comments', [TicketCommentController::class, 'index'])->whereNumber('ticket')->name('tickets.comments.index');
    Route::post('/tickets/{ticket}/comments', [TicketCommentController::class, 'store'])->whereNumber('ticket')->middleware('throttle:posting')->name('tickets.comments.store');
    Route::delete('/ticket-comments/{comment}', [TicketCommentController::class, 'destroy'])->whereNumber('comment')->name('ticket-comments.destroy');

    Route::get('/account', [AccountController::class, 'edit'])->name('account.edit');
    Route::patch('/account', [AccountController::class, 'update'])->name('account.update');
    Route::put('/account/password', [AccountPasswordController::class, 'update'])->name('account.password.update');

    Route::prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', AdminUserController::class)->except('show')->whereNumber('user');
        Route::resource('categories', AdminCategoryController::class)->except('show')->whereNumber('category');
        Route::resource('forum-topics', AdminForumTopicController::class)->except('show')->parameters(['forum-topics' => 'topic']);
    });

    Route::prefix('forum')->name('forum.')->group(function () {
        Route::get('/', [ForumThreadController::class, 'index'])->name('index');
        Route::post('/threads', [ForumThreadController::class, 'store'])->middleware('throttle:posting')->name('threads.store');
        Route::get('/threads/{thread}', [ForumThreadController::class, 'show'])->whereNumber('thread')->name('threads.show');
        Route::get('/threads/{thread}/edit', [ForumThreadController::class, 'edit'])->whereNumber('thread')->name('threads.edit');
        Route::put('/threads/{thread}', [ForumThreadController::class, 'update'])->whereNumber('thread')->name('threads.update');
        Route::delete('/threads/{thread}', [ForumThreadController::class, 'destroy'])->whereNumber('thread')->name('threads.destroy');

        Route::patch('/threads/{thread}/moderation', [ForumThreadModerationController::class, 'update'])->whereNumber('thread')->name('threads.moderation.update');
        Route::post('/threads/{thread}/reactions', [ForumThreadReactionController::class, 'store'])->whereNumber('thread')->middleware('throttle:posting')->name('threads.reactions.store');
        Route::post('/replies/{reply}/reactions', [ForumReplyReactionController::class, 'store'])->whereNumber('reply')->middleware('throttle:posting')->name('replies.reactions.store');
        Route::get('/threads/{thread}/replies', [ForumReplyController::class, 'index'])->whereNumber('thread')->name('threads.replies.index');
        Route::post('/threads/{thread}/replies', [ForumReplyController::class, 'store'])->whereNumber('thread')->middleware('throttle:posting')->name('threads.replies.store');
        Route::get('/replies/{reply}/edit', [ForumReplyController::class, 'edit'])->whereNumber('reply')->name('replies.edit');
        Route::put('/replies/{reply}', [ForumReplyController::class, 'update'])->whereNumber('reply')->name('replies.update');
        Route::delete('/replies/{reply}', [ForumReplyController::class, 'destroy'])->whereNumber('reply')->name('replies.destroy');
    });
});
