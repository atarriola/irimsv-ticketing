<?php

use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use App\Models\Usertype;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening the forum', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/forum', '/forum/threads/1']);

test('the feed shows every thread with its author, their position, topic and reply count', function () {
    $topic = ForumTopic::factory()->create(['name' => 'Help', 'slug' => 'help']);
    $librarian = Usertype::factory()->create(['type_name' => 'Regional Librarian']);
    $author = User::factory()->for($librarian)->admin()->create(['firstname' => 'System', 'lastname' => 'Admin']);
    $thread = ForumThread::factory()->for($topic, 'topic')->for($author, 'author')->create(['body' => 'The portal is back online.']);
    ForumReply::factory(2)->for($thread, 'thread')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Forum/Index')
            ->where('currentTopic', null)
            ->has('topics', 1)
            ->has('types', 2)
            ->where('can.create', true)
            ->has('threads.data', 1)
            ->where('threads.data.0.id', $thread->id)
            ->where('threads.data.0.body', 'The portal is back online.')
            ->where('threads.data.0.author', 'System Admin')
            ->where('threads.data.0.author_position', 'Regional Librarian')
            ->where('threads.data.0.author_is_admin', true)
            ->where('threads.data.0.topic.slug', 'help')
            ->where('threads.data.0.replies_count', 2));
});

test('the feed shows pinned threads first and then the most recently active', function () {
    $quiet = ForumThread::factory()->create(['last_activity_at' => now()->subDays(3)]);
    $active = ForumThread::factory()->create(['last_activity_at' => now()->subHour()]);
    $pinned = ForumThread::factory()->pinned()->create(['last_activity_at' => now()->subDays(9)]);

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('threads.data.0.id', $pinned->id)
            ->where('threads.data.1.id', $active->id)
            ->where('threads.data.2.id', $quiet->id));
});

test('the feed can be narrowed to one topic', function () {
    $topic = ForumTopic::factory()->create(['slug' => 'ideas']);
    $thread = ForumThread::factory()->for($topic, 'topic')->create();
    ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index', ['topic' => 'ideas']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentTopic', 'ideas')
            ->has('threads.data', 1)
            ->where('threads.data.0.id', $thread->id));
});

test('an unknown topic shows the whole feed', function () {
    ForumThread::factory(2)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index', ['topic' => 'does-not-exist']))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentTopic', null)
            ->has('threads.data', 2));
});

test('the feed loads fifteen threads at a time', function () {
    ForumThread::factory(16)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page->has('threads.data', 15));

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index', ['page' => 2]))
        ->assertInertia(fn (Assert $page) => $page->has('threads.data', 1));
});
