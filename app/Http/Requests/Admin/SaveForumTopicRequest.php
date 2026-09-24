<?php

namespace App\Http\Requests\Admin;

use App\Models\ForumTopic;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;

class SaveForumTopicRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        $topic = $this->route('topic');

        return $topic === null
            ? $this->user()?->can('create', ForumTopic::class) ?? false
            : $this->user()?->can('update', $topic) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'name' => [
                'required',
                'string',
                'max:255',
                function (string $attribute, mixed $value, Closure $fail): void {
                    $slug = Str::slug((string) $value);

                    if ($slug === '') {
                        $fail('The name must contain letters or numbers.');

                        return;
                    }

                    if (ForumTopic::where('slug', $slug)->whereKeyNot($this->route('topic')?->getKey())->exists()) {
                        $fail('A topic with this name already exists.');
                    }
                },
            ],
            'description' => ['nullable', 'string', 'max:1000'],
            'position' => ['required', 'integer', 'min:0', 'max:1000'],
        ];
    }

    /**
     * Get the validated attributes together with the slug derived from the name.
     *
     * @return array{name: string, slug: string, description: string|null, position: int}
     */
    public function topicAttributes(): array
    {
        return [
            'name' => $this->validated('name'),
            'slug' => Str::slug($this->validated('name')),
            'description' => $this->validated('description'),
            'position' => (int) $this->validated('position'),
        ];
    }
}
