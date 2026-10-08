<?php

namespace App\Http\Requests\Admin;

use App\Models\SavedReply;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;

class SaveSavedReplyRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $reply = $this->route('saved_reply');

        return $reply === null
            ? $this->user()?->can('create', SavedReply::class) ?? false
            : $this->user()?->can('update', $reply) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:120'],
            'body' => ['required', 'string', 'max:5000'],
        ];
    }
}
