<?php

namespace App\Http\Controllers;

use App\Models\NewsAttachment;
use App\Models\NewsPost;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\HeaderUtils;

class NewsAttachmentController extends Controller
{
    /**
     * Send a photo or video that goes with a post to someone allowed to read that post.
     */
    public function show(NewsPost $post, NewsAttachment $attachment): BinaryFileResponse
    {
        Gate::authorize('view', $post);

        $disk = Storage::disk(NewsAttachment::DISK);

        abort_unless($disk->exists($attachment->path), 404);

        // A file response answers range requests, which video players rely on to seek and to resume.
        return response()
            ->file($disk->path($attachment->path), [
                'Content-Type' => $attachment->mime_type,
                'Content-Disposition' => HeaderUtils::makeDisposition(
                    HeaderUtils::DISPOSITION_INLINE,
                    $attachment->name,
                    str_replace('%', '', Str::ascii($attachment->name)),
                ),
            ])
            ->setPrivate();
    }

    /**
     * Take a photo or video off a post, deleting its file.
     */
    public function destroy(NewsPost $post, NewsAttachment $attachment): RedirectResponse
    {
        Gate::authorize('update', $post);

        $attachment->delete();

        Inertia::flash('toast', ['type' => 'success', 'message' => 'The file has been removed.']);

        return back();
    }
}
