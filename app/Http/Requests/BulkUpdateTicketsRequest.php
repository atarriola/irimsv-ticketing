<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class BulkUpdateTicketsRequest extends FormRequest
{
    /**
     * The most tickets one request may change.
     */
    public const int MAX_TICKETS = 100;

    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->isAdmin() ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'ids' => ['required', 'array', 'min:1', 'max:'.self::MAX_TICKETS],
            'ids.*' => ['required', 'integer'],
            'status' => ['nullable', 'required_without:priority', Rule::enum(TicketStatus::class)],
            'priority' => ['nullable', 'required_without:status', Rule::enum(TicketPriority::class)],
        ];
    }

    /**
     * Get custom attributes for validator errors.
     *
     * @return array<string, string>
     */
    public function attributes(): array
    {
        return [
            'ids' => 'tickets',
        ];
    }

    /**
     * Get the error messages for the defined validation rules.
     *
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'ids.required' => 'Select at least one ticket.',
            'ids.max' => 'You can change up to :max tickets at a time.',
            'status.required_without' => 'Choose a status or a priority to apply.',
            'priority.required_without' => 'Choose a status or a priority to apply.',
        ];
    }
}
