<?php

namespace App\Http\Requests;

use App\Enums\NewsKind;
use App\Models\NewsPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;

class SaveNewsPostRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $post = $this->route('post');

        return $post === null
            ? $this->user()?->can('create', NewsPost::class) ?? false
            : $this->user()?->can('update', $post) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'kind' => ['required', Rule::enum(NewsKind::class)],
            'title' => ['required', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:20000'],
            'is_published' => ['nullable', 'boolean'],
        ];
    }

    /**
     * Get the validated attributes, with the publication time kept, set or cleared to match the publish flag.
     *
     * @return array{kind: string, title: string, body: string, published_at: Carbon|null}
     */
    public function postAttributes(): array
    {
        return [
            'kind' => $this->validated('kind'),
            'title' => $this->validated('title'),
            'body' => $this->validated('body'),
            // A post that is already out keeps its original date, one published now is dated now, and a draft has none.
            'published_at' => $this->boolean('is_published') ? ($this->route('post')?->published_at ?? now()) : null,
        ];
    }
}
