<?php

use App\Models\NewsAttachment;
use App\Models\NewsPost;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

uses(RefreshDatabase::class);

beforeEach(fn () => Storage::fake(NewsAttachment::DISK));

/**
 * Put a real PNG on the attachments disk and record it against the post.
 */
function storeNewsPhoto(NewsPost $post, string $name = 'poster.png'): NewsAttachment
{
    $attachment = NewsAttachment::factory()->for($post, 'post')->create(['name' => $name]);

    // The fake image's temporary file only lives as long as the object does, so it is kept until it has been copied.
    $image = UploadedFile::fake()->image($name);
    Storage::disk(NewsAttachment::DISK)->put($attachment->path, file_get_contents($image->getPathname()));

    return $attachment;
}

/**
 * Put a stand-in for a video, with known bytes, on the attachments disk and record it against the post.
 */
function storeNewsVideo(NewsPost $post, string $contents = '0123456789abcdefghij'): NewsAttachment
{
    $attachment = NewsAttachment::factory()->video()->for($post, 'post')->create(['name' => 'launch.mp4', 'size' => strlen($contents)]);

    Storage::disk(NewsAttachment::DISK)->put($attachment->path, $contents);

    return $attachment;
}

test('a guest is sent to the login page when opening a file on a post', function () {
    $attachment = storeNewsPhoto(NewsPost::factory()->create());

    $this->get(route('news.attachments.show', [$attachment->news_post_id, $attachment]))->assertRedirect(route('login'));
});

test('a reader can open a photo on a published post', function () {
    $post = NewsPost::factory()->create();
    $attachment = storeNewsPhoto($post, 'poster.png');

    $this->actingAs(User::factory()->create())
        ->get(route('news.attachments.show', [$post, $attachment]))
        ->assertOk()
        ->assertHeader('Content-Type', 'image/png')
        ->assertHeader('Content-Disposition', 'inline; filename=poster.png')
        ->assertHeader('Cache-Control', 'private');
});

test('a video on a post can be played from any point', function () {
    $post = NewsPost::factory()->create();
    $attachment = storeNewsVideo($post, '0123456789abcdefghij');

    $this->actingAs(User::factory()->create())
        ->get(route('news.attachments.show', [$post, $attachment]), ['Range' => 'bytes=4-7'])
        ->assertStatus(206)
        ->assertHeader('Content-Type', 'video/mp4')
        ->assertHeader('Accept-Ranges', 'bytes')
        ->assertHeader('Content-Range', 'bytes 4-7/20')
        ->assertHeader('Content-Length', '4');
});

test('a reader cannot open a file on a draft, but an administrator can', function () {
    $draft = NewsPost::factory()->draft()->create();
    $attachment = storeNewsPhoto($draft);

    $this->actingAs(User::factory()->create())->get(route('news.attachments.show', [$draft, $attachment]))->assertForbidden();

    $this->actingAs(User::factory()->admin()->create())->get(route('news.attachments.show', [$draft, $attachment]))->assertOk();
});

test('a file is only found under its own post', function () {
    $attachment = storeNewsPhoto(NewsPost::factory()->create());
    $otherPost = NewsPost::factory()->create();

    $this->actingAs(User::factory()->create())
        ->get(route('news.attachments.show', [$otherPost, $attachment]))
        ->assertNotFound();
});

test('a file that is gone from the disk is not found', function () {
    $post = NewsPost::factory()->create();
    $attachment = NewsAttachment::factory()->for($post, 'post')->create();

    $this->actingAs(User::factory()->create())
        ->get(route('news.attachments.show', [$post, $attachment]))
        ->assertNotFound();
});

test('an administrator can remove a file from a post, and it goes from the disk too', function () {
    $post = NewsPost::factory()->create();
    $attachment = storeNewsPhoto($post);

    $this->actingAs(User::factory()->admin()->create())
        ->from(route('news.edit', $post))
        ->delete(route('news.attachments.destroy', [$post, $attachment]))
        ->assertRedirect(route('news.edit', $post))
        ->assertInertiaFlash('toast.type', 'success');

    expect(NewsAttachment::count())->toBe(0);
    Storage::disk(NewsAttachment::DISK)->assertMissing($attachment->path);
});

test('a regular user cannot remove a file from a post', function () {
    $post = NewsPost::factory()->create();
    $attachment = storeNewsPhoto($post);

    $this->actingAs(User::factory()->create())
        ->delete(route('news.attachments.destroy', [$post, $attachment]))
        ->assertForbidden();

    expect(NewsAttachment::count())->toBe(1);
    Storage::disk(NewsAttachment::DISK)->assertExists($attachment->path);
});

test('deleting a post removes its files from the disk', function () {
    $post = NewsPost::factory()->create();
    $photo = storeNewsPhoto($post);
    $video = storeNewsVideo($post);

    $this->actingAs(User::factory()->admin()->create())->delete(route('news.destroy', $post));

    expect(NewsAttachment::count())->toBe(0);
    Storage::disk(NewsAttachment::DISK)->assertMissing([$photo->path, $video->path]);
});
