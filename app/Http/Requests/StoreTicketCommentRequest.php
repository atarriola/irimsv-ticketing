<?php

namespace App\Http\Requests;

use App\Models\TicketAttachment;
use App\Models\TicketComment;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class StoreTicketCommentRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     *
     * An internal note needs more than the right to comment: only administrators leave them.
     */
    public function authorize(): bool
    {
        $ticket = $this->route('ticket');

        return $this->user()?->can($this->boolean('is_internal') ? 'addInternalNote' : 'comment', $ticket) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'body' => ['required', 'string', 'max:5000'],
            'is_internal' => ['nullable', 'boolean'],
            'attachments' => ['nullable', 'array', 'max:'.TicketComment::MAX_IMAGES],
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
            'body' => 'message',
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
            'attachments.max' => 'You can attach up to :max images to a message.',
            'attachments.*.image' => 'Each attachment must be a JPG, PNG, GIF or WebP image.',
            'attachments.*.mimes' => 'Each attachment must be a JPG, PNG, GIF or WebP image.',
            'attachments.*.max' => 'Each image must be '.(TicketAttachment::MAX_KILOBYTES / 1024).' MB or smaller.',
        ];
    }
}
