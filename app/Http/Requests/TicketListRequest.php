<?php

namespace App\Http\Requests;

use App\Enums\TicketGroup;
use App\Enums\TicketPriority;
use App\Enums\TicketSort;
use App\Enums\TicketStatus;
use App\Enums\TicketType;
use App\Enums\WaitingOn;
use App\Models\Ticket;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

/**
 * What a list, board or export of tickets asked for. Unknown values fall back to the default
 * rather than failing, so a stale link still opens the page.
 */
class TicketListRequest extends FormRequest
{
    /**
     * The longest search term that is looked up.
     */
    private const int SEARCH_LENGTH = 100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('viewAny', Ticket::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [];
    }

    /**
     * Get the group of tickets asked for, bugs and problems unless another was named.
     */
    public function group(): TicketGroup
    {
        return $this->enum('group', TicketGroup::class) ?? TicketGroup::Issues;
    }

    /**
     * Get whether the tickets are shown as a board or a list.
     */
    public function view(): string
    {
        return $this->query('view') === 'list' ? 'list' : 'board';
    }

    /**
     * Get the order a list asked for.
     */
    public function sort(): TicketSort
    {
        return $this->enum('sort', TicketSort::class) ?? TicketSort::Newest;
    }

    /**
     * Get the status a list asked for; a board shows every status as a column.
     */
    public function status(): ?TicketStatus
    {
        return $this->enum('status', TicketStatus::class);
    }

    /**
     * Get the filters that narrow the tickets, normalised and safe to pass to the query.
     *
     * @return array{types: list<TicketType>, status: TicketStatus|null, priority: TicketPriority|null, category: int|null, waiting: WaitingOn|null, mine: bool, trashed: bool, q: string}
     */
    public function filters(bool $withStatus = true): array
    {
        return [
            'types' => $this->group()->types(),
            'status' => $withStatus ? $this->status() : null,
            'priority' => $this->enum('priority', TicketPriority::class),
            'category' => $this->integer('category') > 0 ? $this->integer('category') : null,
            'waiting' => $this->enum('waiting', WaitingOn::class),
            'mine' => $this->boolean('mine'),
            // Deleted tickets are only for administrators, and only listed when asked for.
            'trashed' => $this->boolean('trashed') && $this->user()->isAdmin(),
            'q' => Str::limit(trim((string) $this->string('q')), self::SEARCH_LENGTH, ''),
        ];
    }

    /**
     * Get the filters as the page echoes them back, so the controls show what is applied.
     *
     * @return array{q: string, status: string|null, priority: string|null, category: int|null, waiting: string|null, mine: bool, trashed: bool, sort: string}
     */
    public function filtersForPage(): array
    {
        $filters = $this->filters();

        return [
            'q' => $filters['q'],
            'status' => $filters['status']?->value,
            'priority' => $filters['priority']?->value,
            'category' => $filters['category'],
            'waiting' => $filters['waiting']?->value,
            'mine' => $filters['mine'],
            'trashed' => $filters['trashed'],
            'sort' => $this->sort()->value,
        ];
    }
}
