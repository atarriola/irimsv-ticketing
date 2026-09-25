<?php

use App\Http\Middleware\HandleInertiaRequests;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a guest is sent to the login page when opening the forum', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/forum', '/forum/threads/1']);

test('the feed shows every thread with its author, their position and photo, topic and reply count', function () {
    config()->set('filesystems.disks.public.url', 'https://lrmis.test/storage');
    $topic = ForumTopic::factory()->create(['name' => 'Help', 'slug' => 'help']);
    $author = User::factory()->admin()->create(['firstname' => 'System', 'lastname' => 'Admin', 'photo' => 'system_admin.jpg']);
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
            ->where('threads.data.0.author_position', 'Administrator')
            ->where('threads.data.0.author_photo_url', 'https://lrmis.test/storage/user_pic/system_admin.jpg')
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

test('the feed tells the client the newest thread it shows', function () {
    ForumThread::factory()->create();
    $newest = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('latestThreadId', $newest->id)
            ->missing('newThreadsCount'));
});

test('an open feed can ask how many threads were posted after the newest one it shows, within its topic', function () {
    $topic = ForumTopic::factory()->create(['slug' => 'ideas']);
    $seen = ForumThread::factory()->for($topic, 'topic')->create();
    ForumThread::factory()->for($topic, 'topic')->create();
    ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index', ['topic' => 'ideas', 'seen' => $seen->id]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/forum')),
            'X-Inertia-Partial-Component' => 'Forum/Index',
            'X-Inertia-Partial-Data' => 'newThreadsCount',
        ])
        ->assertOk()
        ->assertJsonPath('props.newThreadsCount', 1)
        ->assertJsonMissingPath('props.threads');
});

test('an empty feed learns about the first thread posted', function () {
    ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index', ['seen' => 0]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/forum')),
            'X-Inertia-Partial-Component' => 'Forum/Index',
            'X-Inertia-Partial-Data' => 'newThreadsCount',
        ])
        ->assertOk()
        ->assertJsonPath('props.newThreadsCount', 1);
});

test('the feed gives each thread a version that changes with edits and replies', function () {
    $this->freezeSecond();
    $thread = ForumThread::factory()->create(['updated_at' => now()->subDay(), 'last_activity_at' => now()->subHour()]);

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index'))
        ->assertInertia(fn (Assert $page) => $page->where('threads.data.0.version', now()->subHour()->timestamp));
});

test('an open feed can ask for the activity of the threads it shows', function () {
    $this->freezeSecond();
    $thread = ForumThread::factory()->create(['updated_at' => now()->subDay(), 'last_activity_at' => now()]);
    ForumReply::factory(2)->for($thread, 'thread')->create();
    $unasked = ForumThread::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('forum.index', ['threads' => [$thread->id]]), [
            'X-Inertia' => 'true',
            'X-Inertia-Version' => app(HandleInertiaRequests::class)->version(Request::create('/forum')),
            'X-Inertia-Partial-Component' => 'Forum/Index',
            'X-Inertia-Partial-Data' => 'threadActivity',
        ])
        ->assertOk()
        ->assertJsonPath("props.threadActivity.{$thread->id}.replies_count", 2)
        ->assertJsonPath("props.threadActivity.{$thread->id}.version", now()->timestamp)
        ->assertJsonMissingPath("props.threadActivity.{$unasked->id}");
});
