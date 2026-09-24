<?php

namespace App\Http\Requests;

use App\Enums\ForumThreadType;
use App\Models\ForumThread;
use App\Models\ForumTopic;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreForumThreadRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('create', ForumThread::class) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'forum_topic_id' => ['required', 'integer', Rule::exists(ForumTopic::class, 'id')],
            'type' => ['required', Rule::enum(ForumThreadType::class)],
            'body' => ['required', 'string', 'max:5000'],
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
            'forum_topic_id' => 'topic',
            'body' => 'message',
        ];
    }
}
