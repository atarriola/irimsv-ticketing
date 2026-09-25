<?php

namespace App\Http\Controllers;

use App\Http\Resources\ForumReplyResource;
use App\Models\ForumReply;
use App\Models\ForumThread;
use App\Models\User;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Gate;

class ForumThreadChangeController extends Controller
{
    /**
     * Return what has changed in a thread's conversation since the client last asked.
     *
     * The client sends the highest reply id it has ("after"), the ids of the replies it is showing
     * ("known", comma-separated) and the server time of its previous request ("since"). It receives the
     * replies posted, edited or deleted since then, fresh reaction counts for the replies it shows, the
     * thread's own live state, and the server time to send back as "since" next time.
     */
    public function index(Request $request, ForumThread $thread): JsonResponse
    {
        Gate::authorize('view', $thread);

        $request->validate([
            'after' => ['nullable', 'integer', 'min:0'],
            'since' => ['nullable', 'date'],
            'known' => ['nullable', 'string', 'max:20000', 'regex:/^\d+(,\d+)*$/'],
        ]);

        $syncedAt = now();
        $since = $request->filled('since') ? Carbon::parse($request->input('since')) : null;
        $knownIds = $request->filled('known') ? array_map(intval(...), explode(',', $request->input('known'))) : [];

        $known = $knownIds === []
            ? new Collection
            : $thread->replies()->whereIn('id', $knownIds)->with('reactions')->get();

        $created = $thread->replies()
            ->where('id', '>', $request->integer('after'))
            ->with(['author:'.User::DISPLAY_COLUMNS, 'reactions'])
            ->oldest()
            ->oldest('id')
            ->get();

        $updated = $since === null
            ? new Collection
            : $known->filter(fn (ForumReply $reply): bool => $reply->updated_at->gte($since))->load('author:'.User::DISPLAY_COLUMNS);

        $thread->load('reactions');

        return response()->json([
            'synced_at' => $syncedAt->toIso8601String(),
            'created' => $this->serialise($created, $request),
            'updated' => $this->serialise($updated, $request),
            'deleted' => array_values(array_diff($knownIds, $known->modelKeys())),
            'reactions' => (object) $known->mapWithKeys(fn (ForumReply $reply): array => [$reply->id => $reply->reactionSummary($request->user())])->all(),
            'thread' => [
                'is_pinned' => $thread->is_pinned,
                'is_locked' => $thread->is_locked,
                'reactions' => $thread->reactionSummary($request->user()),
                'can' => ['reply' => $request->user()->can('reply', $thread)],
                ...($since !== null && $thread->updated_at->gte($since) ? ['body' => $thread->body, 'excerpt' => $thread->excerpt] : []),
            ],
            ...$thread->conversationTotals(),
        ]);
    }

    /**
     * Serialise replies one level deep, leaving it to the client to slot each one under its parent.
     *
     * @param  Collection<int, ForumReply>  $replies
     * @return list<array<string, mixed>>
     */
    private function serialise(Collection $replies, Request $request): array
    {
        return $replies
            ->each(fn (ForumReply $reply) => $reply->setRelation('children', $reply->newCollection()))
            ->map(fn (ForumReply $reply): array => ForumReplyResource::make($reply)->resolve($request))
            ->values()
            ->all();
    }
}
