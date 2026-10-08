<?php

namespace App\Http\Requests;

use App\Enums\TicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketStatusRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * An administrator may move a ticket anywhere; its requester may only close a resolved ticket or reopen it.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return ($this->user()?->can('changeStatus', $ticket) || $this->user()?->can('confirmResolution', $ticket)) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'status' => ['required', Rule::enum(TicketStatus::class)->only($this->allowedStatuses())],
        ];
    }

    /**
     * Get the statuses the user may move this ticket to.
     *
     * @return list<TicketStatus>
     */
    private function allowedStatuses(): array
    {
        $ticket = $this->route('ticket');

        if ($this->user()->can('changeStatus', $ticket)) {
            return TicketStatus::forType($ticket->type);
        }

        return [TicketStatus::Closed, TicketStatus::Open];
    }
}
