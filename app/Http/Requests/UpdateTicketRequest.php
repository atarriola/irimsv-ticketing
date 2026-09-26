<?php

namespace App\Http\Requests;

use App\Models\TicketAttachment;
use Illuminate\Validation\Validator;

class UpdateTicketRequest extends StoreTicketRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('ticket')) ?? false;
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * The images already on the ticket count towards its limit, not only the ones sent now.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $added = count($this->file('attachments', []));

                if ($added === 0 || $validator->errors()->has('attachments')) {
                    return;
                }

                $existing = $this->route('ticket')->attachments()->count();

                if ($existing + $added > TicketAttachment::MAX_PER_TICKET) {
                    $validator->errors()->add('attachments', sprintf(
                        'A ticket can hold up to %d images, and this one already has %d.',
                        TicketAttachment::MAX_PER_TICKET,
                        $existing,
                    ));
                }
            },
        ];
    }
}
