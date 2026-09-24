<?php

use App\Models\ForumThread;
use App\Models\ForumTopic;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

test('a regular user cannot manage forum topics', function (string $method, string $url) {
    ForumTopic::factory()->create(['name' => 'General', 'slug' => 'general']);

    $this->actingAs(User::factory()->create())->{$method}($url, ['name' => 'Hijacked', 'position' => 0])->assertForbidden();

    expect(ForumTopic::pluck('name')->all())->toBe(['General']);
})->with([
    'list' => ['get', '/admin/forum-topics'],
    'form' => ['get', '/admin/forum-topics/create'],
    'creation' => ['post', '/admin/forum-topics'],
    'update' => ['put', '/admin/forum-topics/general'],
    'deletion' => ['delete', '/admin/forum-topics/general'],
]);

test('an admin sees the topics in order with their thread counts', function () {
    ForumTopic::factory()->create(['name' => 'Second', 'position' => 2]);
    $first = ForumTopic::factory()->create(['name' => 'First', 'position' => 1]);
    ForumThread::factory(2)->for($first, 'topic')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('admin.forum-topics.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('Admin/Taxonomy/Index')
            ->where('resource.hasPosition', true)
            ->where('items.0.name', 'First')
            ->where('items.0.count', 2)
            ->where('items.1.name', 'Second'));
});

test('an admin can create a topic', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.forum-topics.store'), ['name' => 'Release Notes', 'description' => 'What changed.', 'position' => 5])
        ->assertRedirect(route('admin.forum-topics.index'));

    $topic = ForumTopic::sole();

    expect($topic->slug)->toBe('release-notes');
    expect($topic->position)->toBe(5);
});

test('creating a topic validates its details', function () {
    ForumTopic::factory()->create(['name' => 'General', 'slug' => 'general']);

    $this->actingAs(User::factory()->admin()->create())
        ->post(route('admin.forum-topics.store'), ['name' => 'General', 'position' => -1])
        ->assertSessionHasErrors([
            'name' => 'A topic with this name already exists.',
            'position' => 'The position field must be at least 0.',
        ]);

    expect(ForumTopic::count())->toBe(1);
});

test('an admin can rename and reorder a topic', function () {
    $topic = ForumTopic::factory()->create(['name' => 'General', 'slug' => 'general', 'position' => 0]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('admin.forum-topics.update', $topic), ['name' => 'Lounge', 'position' => 3])
        ->assertRedirect(route('admin.forum-topics.index'));

    expect($topic->fresh()->slug)->toBe('lounge');
    expect($topic->fresh()->position)->toBe(3);
});

test('an empty topic can be deleted', function () {
    $topic = ForumTopic::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.forum-topics.destroy', $topic))
        ->assertInertiaFlash('toast.type', 'success');

    expect(ForumTopic::count())->toBe(0);
});

test('a topic that still has threads is kept and the admin is told why', function () {
    $topic = ForumTopic::factory()->create(['name' => 'General']);
    ForumThread::factory()->for($topic, 'topic')->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('admin.forum-topics.destroy', $topic))
        ->assertRedirect(route('admin.forum-topics.index'))
        ->assertInertiaFlash('toast.type', 'error')
        ->assertInertiaFlash('toast.message', 'The General topic still has threads. Move or delete them first.');

    expect(ForumTopic::count())->toBe(1);
});
