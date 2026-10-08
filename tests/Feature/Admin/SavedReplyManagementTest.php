<?php

use App\Models\SavedReply;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an admin can list, write, edit and delete saved replies', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)
        ->post(route('admin.saved-replies.store'), ['title' => 'Ask for a screenshot', 'body' => 'Could you send a screenshot of what you see?'])
        ->assertRedirect(route('admin.saved-replies.index'))
        ->assertInertiaFlash('toast.message', 'The saved reply "Ask for a screenshot" has been created.');

    $reply = SavedReply::sole();
    expect($reply->user_id)->toBe($admin->id);

    $this->actingAs($admin)
        ->get(route('admin.saved-replies.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/SavedReplies/Index')
            ->has('replies', 1)
            ->where('replies.0.title', 'Ask for a screenshot')
            ->where('replies.0.author', $admin->name));

    $this->actingAs($admin)
        ->get(route('admin.saved-replies.edit', $reply))
        ->assertInertia(fn (Assert $page) => $page->component('Admin/SavedReplies/Form')->where('reply.title', 'Ask for a screenshot'));

    $this->actingAs($admin)
        ->put(route('admin.saved-replies.update', $reply), ['title' => 'Screenshot please', 'body' => 'A screenshot would help.'])
        ->assertRedirect(route('admin.saved-replies.index'));

    expect($reply->fresh()->title)->toBe('Screenshot please');

    $this->actingAs($admin)->delete(route('admin.saved-replies.destroy', $reply))->assertRedirect(route('admin.saved-replies.index'));

    expect(SavedReply::count())->toBe(0);
});

test('a saved reply needs a title and a message', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.saved-replies.store'), ['title' => '', 'body' => ''])
        ->assertSessionHasErrors(['title', 'body']);
});

test('a member cannot manage saved replies', function (string $method, string $routeName) {
    $reply = SavedReply::factory()->create();

    $this->actingAs(User::factory()->create())
        ->{$method}(route($routeName, $reply), ['title' => 'x', 'body' => 'y'])
        ->assertForbidden();
})->with([
    'list' => ['get', 'admin.saved-replies.index'],
    'create' => ['post', 'admin.saved-replies.store'],
    'update' => ['put', 'admin.saved-replies.update'],
    'delete' => ['delete', 'admin.saved-replies.destroy'],
]);

test('the ticket page offers saved replies to administrators only', function () {
    SavedReply::factory()->create(['title' => 'Known issue', 'body' => 'This is a known issue.']);
    $requester = User::factory()->create();
    $ticket = Ticket::factory()->for($requester, 'requester')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->has('savedReplies', 1)->where('savedReplies.0.body', 'This is a known issue.'));

    $this->actingAs($requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page->has('savedReplies', 0));
});
