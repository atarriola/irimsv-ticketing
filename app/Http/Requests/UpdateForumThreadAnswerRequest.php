<?php

namespace App\Http\Requests;

use App\Models\ForumReply;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateForumThreadAnswerRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('acceptAnswer', $this->route('thread')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only a comment in this very thread can be its answer; no comment at all clears it.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'reply_id' => [
                'nullable',
                'integer',
                Rule::exists(ForumReply::class, 'id')->where(fn (Builder $query) => $query->where('forum_thread_id', $this->route('thread')->id)),
            ],
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
            'reply_id' => 'comment',
        ];
    }
}
