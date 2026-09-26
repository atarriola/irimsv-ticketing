<?php

use App\Enums\NewsKind;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Inertia\Testing\AssertableInertia as Assert;

uses(RefreshDatabase::class);

/**
 * Build a valid news post payload, optionally overriding fields.
 *
 * @param  array<string, mixed>  $overrides
 * @return array<string, mixed>
 */
function newsPayload(array $overrides = []): array
{
    return [
        'kind' => 'release',
        'title' => 'Ticket screenshots are here',
        'body' => 'You can now attach up to five images when raising a ticket.',
        'is_published' => '1',
        ...$overrides,
    ];
}

test('a guest is sent to the login page when opening the news', function (string $url) {
    $this->get($url)->assertRedirect(route('login'));
})->with(['/news', '/news/1']);

test('the news lists published posts newest first with their kind, author and date', function () {
    $author = User::factory()->admin()->create(['firstname' => 'System', 'lastname' => 'Admin']);
    $older = NewsPost::factory()->for($author, 'author')->create(['title' => 'Older', 'published_at' => now()->subWeek()]);
    $newer = NewsPost::factory()->for($author, 'author')->create(['title' => 'Newer', 'kind' => NewsKind::Release, 'body' => 'Big news.', 'published_at' => now()->subDay()]);

    $this->actingAs(User::factory()->create())
        ->get(route('news.index'))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('News/Index')
            ->has('kinds', 6)
            ->where('currentKind', null)
            ->where('can.create', false)
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $newer->id)
            ->where('posts.data.0.title', 'Newer')
            ->where('posts.data.0.kind', 'release')
            ->where('posts.data.0.kind_label', 'New feature')
            ->where('posts.data.0.excerpt', 'Big news.')
            ->where('posts.data.0.author', 'System Admin')
            ->where('posts.data.0.author_is_admin', true)
            ->where('posts.data.0.is_published', true)
            ->where('posts.data.0.published_on', now()->subDay()->toFormattedDateString())
            ->where('posts.data.1.id', $older->id));
});

test('drafts are hidden from readers and listed first for administrators', function () {
    $published = NewsPost::factory()->create();
    $draft = NewsPost::factory()->draft()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('news.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $published->id));

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('news.index'))
        ->assertInertia(fn (Assert $page) => $page
            ->where('can.create', true)
            ->has('posts.data', 2)
            ->where('posts.data.0.id', $draft->id)
            ->where('posts.data.0.is_published', false)
            ->where('posts.data.0.published_on', null)
            ->where('posts.data.1.id', $published->id));
});

test('the news can be narrowed to one kind', function () {
    $event = NewsPost::factory()->create(['kind' => NewsKind::Event]);
    NewsPost::factory()->create(['kind' => NewsKind::Plan]);

    $this->actingAs(User::factory()->create())
        ->get(route('news.index', ['kind' => 'event']))
        ->assertInertia(fn (Assert $page) => $page
            ->where('currentKind', 'event')
            ->has('posts.data', 1)
            ->where('posts.data.0.id', $event->id));
});

test('the news shows ten posts per page', function () {
    NewsPost::factory(11)->create();

    $this->actingAs(User::factory()->create())
        ->get(route('news.index'))
        ->assertInertia(fn (Assert $page) => $page->has('posts.data', 10)->where('posts.last_page', 2));
});

test('a reader can open a published post', function () {
    $post = NewsPost::factory()->create(['title' => 'Maintenance on Friday', 'body' => "Line one.\nLine two."]);

    $this->actingAs(User::factory()->create())
        ->get(route('news.show', $post))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('News/Show')
            ->where('post.title', 'Maintenance on Friday')
            ->where('post.body', "Line one.\nLine two.")
            ->where('can.update', false)
            ->where('can.delete', false));
});

test('a reader cannot open a draft, but an administrator can', function () {
    $draft = NewsPost::factory()->draft()->create();

    $this->actingAs(User::factory()->create())->get(route('news.show', $draft))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('news.show', $draft))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->where('post.is_published', false)
            ->where('can.update', true)
            ->where('can.delete', true));
});

test('a regular user cannot write, edit or delete news', function (string $method, string $routeName) {
    $post = NewsPost::factory()->create(['title' => 'Untouched']);

    $this->actingAs(User::factory()->create())
        ->{$method}(route($routeName, $post), newsPayload(['title' => 'Hijacked']))
        ->assertForbidden();

    expect(NewsPost::pluck('title')->all())->toBe(['Untouched']);
})->with([
    'form' => ['get', 'news.create'],
    'creation' => ['post', 'news.store'],
    'edit form' => ['get', 'news.edit'],
    'update' => ['put', 'news.update'],
    'deletion' => ['delete', 'news.destroy'],
]);

test('an administrator can publish a post', function () {
    $this->freezeSecond();
    $admin = User::factory()->admin()->create();

    $response = $this->actingAs($admin)->post(route('news.store'), newsPayload());

    $post = NewsPost::sole();

    $response
        ->assertRedirect(route('news.show', $post))
        ->assertInertiaFlash('toast.message', 'The post has been published.');

    expect($post->user_id)->toBe($admin->id);
    expect($post->kind)->toBe(NewsKind::Release);
    expect($post->title)->toBe('Ticket screenshots are here');
    expect($post->body)->toBe('You can now attach up to five images when raising a ticket.');
    expect($post->published_at)->toEqual(now());
});

test('a post is kept as a draft when the publish box is :description', function (array $overrides) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('news.store'), [...array_diff_key(newsPayload(), ['is_published' => true]), ...$overrides])
        ->assertInertiaFlash('toast.message', 'The draft has been saved.');

    expect(NewsPost::sole()->published_at)->toBeNull();
})->with([
    'unticked' => [[]],
    'sent as false' => [['is_published' => '0']],
]);

test('writing a post requires a kind, title and body', function () {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('news.store'), [])
        ->assertSessionHasErrors([
            'kind' => 'The kind field is required.',
            'title' => 'The title field is required.',
            'body' => 'The body field is required.',
        ]);

    expect(NewsPost::count())->toBe(0);
});

test('writing a post rejects :field with an invalid value', function (string $field, mixed $value, string $message) {
    $this->actingAs(User::factory()->admin()->create())
        ->post(route('news.store'), newsPayload([$field => $value]))
        ->assertSessionHasErrors([$field => $message]);

    expect(NewsPost::count())->toBe(0);
})->with([
    'an unknown kind' => ['kind', 'gossip', 'The selected kind is invalid.'],
    'an overlong title' => ['title', str_repeat('a', 256), 'The title field must not be greater than 255 characters.'],
    'a publish flag that is not yes or no' => ['is_published', 'maybe', 'The is published field must be true or false.'],
]);

test('the edit form is filled with the post', function () {
    $post = NewsPost::factory()->draft()->create(['kind' => NewsKind::Plan, 'title' => 'Roadmap', 'body' => 'Coming up.']);

    $this->actingAs(User::factory()->admin()->create())
        ->get(route('news.edit', $post))
        ->assertOk()
        ->assertInertia(fn (Assert $page) => $page
            ->component('News/Form')
            ->has('kinds', 6)
            ->where('post.id', $post->id)
            ->where('post.kind', 'plan')
            ->where('post.title', 'Roadmap')
            ->where('post.body', 'Coming up.')
            ->where('post.is_published', false));
});

test('an administrator can edit a post, and it keeps its original publication date', function () {
    $this->freezeSecond();
    $publishedAt = now()->subWeek();
    $post = NewsPost::factory()->create(['kind' => NewsKind::Event, 'published_at' => $publishedAt]);

    $this->actingAs(User::factory()->admin()->create())
        ->put(route('news.update', $post), newsPayload(['kind' => 'plan', 'title' => 'Renamed']))
        ->assertRedirect(route('news.show', $post))
        ->assertInertiaFlash('toast.message', 'The post has been updated.');

    $post->refresh();

    expect($post->kind)->toBe(NewsKind::Plan);
    expect($post->title)->toBe('Renamed');
    expect($post->published_at)->toEqual($publishedAt);
});

test('an administrator can take a post down and publish it again', function () {
    $this->freezeSecond();
    $admin = User::factory()->admin()->create();
    $post = NewsPost::factory()->create(['published_at' => now()->subWeek()]);

    $this->actingAs($admin)->put(route('news.update', $post), newsPayload(['is_published' => '0']));

    expect($post->fresh()->published_at)->toBeNull();

    $this->actingAs($admin)->put(route('news.update', $post), newsPayload());

    expect($post->fresh()->published_at)->toEqual(now());
});

test('an administrator can delete a post', function () {
    $post = NewsPost::factory()->create();

    $this->actingAs(User::factory()->admin()->create())
        ->delete(route('news.destroy', $post))
        ->assertRedirect(route('news.index'))
        ->assertInertiaFlash('toast.type', 'success');

    expect(NewsPost::count())->toBe(0);
});
