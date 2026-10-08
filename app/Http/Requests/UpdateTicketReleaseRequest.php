<?php

namespace App\Http\Requests;

use App\Enums\NewsKind;
use App\Models\NewsPost;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Database\Query\Builder;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateTicketReleaseRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('linkRelease', $this->route('ticket')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * Only a published post about a new feature can be the release that delivered a request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'news_post_id' => [
                'nullable',
                'integer',
                Rule::exists(NewsPost::class, 'id')->where(fn (Builder $query) => $query
                    ->where('kind', NewsKind::Release->value)
                    ->whereNotNull('published_at')),
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
            'news_post_id' => 'release post',
        ];
    }
}
