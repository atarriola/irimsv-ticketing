<?php

namespace App\Http\Requests;

use App\Enums\TicketPriority;
use App\Enums\TicketType;
use App\Models\Category;
use App\Models\Ticket;
use App\Models\TicketAttachment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreTicketRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', Ticket::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'type' => ['required', Rule::enum(TicketType::class)],
            'priority' => ['required', Rule::enum(TicketPriority::class)],
            'category_id' => ['nullable', 'integer', Rule::exists(Category::class, 'id')],
            'subject' => ['required', 'string', 'max:255'],
            'description' => ['required', 'string', 'max:5000'],
            'attachments' => ['nullable', 'array', 'max:'.TicketAttachment::MAX_PER_TICKET],
            'attachments.*' => ['required', 'image', 'mimes:jpg,jpeg,png,gif,webp', 'max:'.TicketAttachment::MAX_KILOBYTES],
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
            'category_id' => 'category',
            'attachments' => 'images',
            'attachments.*' => 'image',
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
            'attachments.max' => 'You can attach up to :max images.',
            'attachments.*.image' => 'Each attachment must be a JPG, PNG, GIF or WebP image.',
            'attachments.*.mimes' => 'Each attachment must be a JPG, PNG, GIF or WebP image.',
            'attachments.*.max' => 'Each image must be '.(TicketAttachment::MAX_KILOBYTES / 1024).' MB or smaller.',
        ];
    }
}
