<?php

namespace App\Http\Requests;

use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class UpdateAccountRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->user()) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'firstname' => ['required', 'string', 'max:255'],
            'middlename' => ['nullable', 'string', 'max:255'],
            'lastname' => ['required', 'string', 'max:255'],
            'extension_name' => ['nullable', 'string', 'max:255'],
            'email' => ['required', 'string', 'email', 'max:255', Rule::unique(User::class)->ignore($this->user())],
            'contact_number' => ['nullable', 'string', 'max:255'],
        ];
    }

    /**
     * Get the validated details, with a missing contact number stored as an
     * empty string because the LRMIS column cannot be null.
     *
     * @return array{firstname: string, middlename: string|null, lastname: string, extension_name: string|null, email: string, contact_number: string}
     */
    public function accountAttributes(): array
    {
        return [
            ...$this->safe()->only(['firstname', 'middlename', 'lastname', 'extension_name', 'email']),
            'contact_number' => $this->validated('contact_number') ?? '',
        ];
    }
}
