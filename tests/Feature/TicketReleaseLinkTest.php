<?php

use App\Enums\NewsKind;
use App\Enums\TicketEventKind;
use App\Models\NewsPost;
use App\Models\Ticket;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('an admin can link a feature request to the release post that delivered it', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->featureRequest()->create();
    $release = NewsPost::factory()->create(['kind' => NewsKind::Release, 'title' => 'Version 2.4']);
    NewsPost::factory()->create(['kind' => NewsKind::Announcement]);

    $this->actingAs($admin)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.linkRelease', true)
            ->has('releasePosts', 1)
            ->where('releasePosts.0.id', $release->id));

    $this->actingAs($admin)
        ->put(route('tickets.release.update', $ticket), ['news_post_id' => $release->id])
        ->assertRedirect()
        ->assertInertiaFlash('toast.message', 'The request is now linked to "Version 2.4".');

    expect($ticket->fresh()->news_post_id)->toBe($release->id)
        ->and($ticket->events()->where('kind', TicketEventKind::ReleaseLinked)->sole()->details)->toBe(['title' => 'Version 2.4']);

    $this->actingAs($ticket->requester)
        ->get(route('tickets.show', $ticket))
        ->assertInertia(fn (Assert $page) => $page
            ->where('ticket.release_post.title', 'Version 2.4')
            ->where('ticket.release_post.url', route('news.show', $release)));
});

test('only a published post about a new feature can be the release', function () {
    $admin = User::factory()->admin()->create();
    $ticket = Ticket::factory()->featureRequest()->create();
    $draft = NewsPost::factory()->draft()->create(['kind' => NewsKind::Release]);
    $announcement = NewsPost::factory()->create(['kind' => NewsKind::Announcement]);

    $this->actingAs($admin)->put(route('tickets.release.update', $ticket), ['news_post_id' => $draft->id])->assertSessionHasErrors('news_post_id');
    $this->actingAs($admin)->put(route('tickets.release.update', $ticket), ['news_post_id' => $announcement->id])->assertSessionHasErrors('news_post_id');

    expect($ticket->fresh()->news_post_id)->toBeNull();
});

test('the release link can be removed again', function () {
    $admin = User::factory()->admin()->create();
    $release = NewsPost::factory()->create(['kind' => NewsKind::Release]);
    $ticket = Ticket::factory()->featureRequest()->create(['news_post_id' => $release->id]);

    $this->actingAs($admin)
        ->put(route('tickets.release.update', $ticket), ['news_post_id' => null])
        ->assertInertiaFlash('toast.message', 'The release link has been removed.');

    expect($ticket->fresh()->news_post_id)->toBeNull();
});

test('a bug report cannot be linked to a release', function () {
    $ticket = Ticket::factory()->create(['type' => 'bug_report']);
    $release = NewsPost::factory()->create(['kind' => NewsKind::Release]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('tickets.release.update', $ticket), ['news_post_id' => $release->id])
        ->assertForbidden();
});
