<?php

namespace App\Http\Requests;

use App\Enums\NewsKind;
use App\Models\NewsAttachment;
use App\Models\NewsPost;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Validator;

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
            'attachments' => ['nullable', 'array', 'max:'.NewsAttachment::MAX_PER_POST],
            'attachments.*' => [
                'bail',
                'required',
                'file',
                'mimes:'.implode(',', [...NewsAttachment::IMAGE_EXTENSIONS, ...NewsAttachment::VIDEO_EXTENSIONS]),
                $this->sizeLimitForKind(),
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
            'attachments' => 'files',
            'attachments.*' => 'file',
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
            'attachments.max' => 'You can attach up to :max files.',
            'attachments.*.file' => 'Each attachment must be a JPG, PNG, GIF or WebP image, or an MP4 or WebM video.',
            'attachments.*.mimes' => 'Each attachment must be a JPG, PNG, GIF or WebP image, or an MP4 or WebM video.',
        ];
    }

    /**
     * Get the "after" validation callables for the request.
     *
     * The files already on the post count towards its limit, not only the ones sent now.
     *
     * @return list<callable(Validator): void>
     */
    public function after(): array
    {
        return [
            function (Validator $validator): void {
                $post = $this->route('post');

                if ($post === null || $validator->errors()->has('attachments')) {
                    return;
                }

                $added = count($this->file('attachments', []));

                if ($added === 0) {
                    return;
                }

                $existing = $post->attachments()->count();

                if ($existing + $added > NewsAttachment::MAX_PER_POST) {
                    $validator->errors()->add('attachments', sprintf(
                        'A post can hold up to %d files, and this one already has %d.',
                        NewsAttachment::MAX_PER_POST,
                        $existing,
                    ));
                }
            },
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

    /**
     * Get the rule that holds each file to the size allowed for its kind, since a video may be far larger than an image.
     */
    private function sizeLimitForKind(): Closure
    {
        return function (string $attribute, mixed $value, Closure $fail): void {
            if (! $value instanceof UploadedFile) {
                return;
            }

            $isVideo = str_starts_with((string) $value->getMimeType(), 'video/');
            $limit = $isVideo ? NewsAttachment::MAX_VIDEO_KILOBYTES : NewsAttachment::MAX_IMAGE_KILOBYTES;

            if ($value->getSize() > $limit * 1024) {
                $fail(sprintf('Each %s must be %d MB or smaller.', $isVideo ? 'video' : 'image', $limit / 1024));
            }
        };
    }
}
