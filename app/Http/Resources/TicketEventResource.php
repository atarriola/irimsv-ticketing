<?php

namespace App\Http\Resources;

use App\Enums\TicketEventKind;
use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use App\Models\TicketEvent;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TicketEvent
 */
class TicketEventResource extends JsonResource
{
    /**
     * Transform the resource into an array.
     *
     * @return array{id: int, kind: string, actor: string, actor_is_admin: bool, description: string, happened_at: string, happened_on: string}
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'kind' => $this->kind->value,
            'actor' => $this->user?->name ?? 'The helpdesk',
            'actor_is_admin' => $this->user?->isAdmin() ?? true,
            'description' => $this->description(),
            'happened_at' => $this->created_at->diffForHumans(),
            'happened_on' => $this->created_at->toDayDateTimeString(),
        ];
    }

    /**
     * Say what happened in plain words.
     */
    private function description(): string
    {
        $details = $this->details ?? [];

        return match ($this->kind) {
            TicketEventKind::Created => 'raised the ticket',
            TicketEventKind::StatusChanged => sprintf(
                'moved it from %s to %s',
                TicketStatus::tryFrom($details['from'] ?? '')?->label() ?? ($details['from'] ?? 'unknown'),
                TicketStatus::tryFrom($details['to'] ?? '')?->label() ?? ($details['to'] ?? 'unknown'),
            ),
            TicketEventKind::PriorityChanged => sprintf(
                'changed the priority from %s to %s',
                TicketPriority::tryFrom($details['from'] ?? '')?->label() ?? ($details['from'] ?? 'unknown'),
                TicketPriority::tryFrom($details['to'] ?? '')?->label() ?? ($details['to'] ?? 'unknown'),
            ),
            TicketEventKind::Edited => 'edited the '.$this->fieldList($details['fields'] ?? []),
            TicketEventKind::Rated => sprintf('rated the help %d out of 5', (int) ($details['rating'] ?? 0)),
            TicketEventKind::ReleaseLinked => isset($details['title']) ? "linked the release \"{$details['title']}\"" : 'removed the release link',
            TicketEventKind::Deleted => 'deleted the ticket',
            TicketEventKind::Restored => 'restored the ticket',
        };
    }

    /**
     * Join the edited fields as "subject, description and category".
     *
     * @param  list<string>  $fields
     */
    private function fieldList(array $fields): string
    {
        $names = array_map(fn (string $field): string => match ($field) {
            'category_id' => 'category',
            'is_shared' => 'sharing',
            default => str_replace('_', ' ', $field),
        }, $fields);

        if ($names === []) {
            return 'details';
        }

        if (count($names) === 1) {
            return $names[0];
        }

        $last = array_pop($names);

        return implode(', ', $names).' and '.$last;
    }
}
