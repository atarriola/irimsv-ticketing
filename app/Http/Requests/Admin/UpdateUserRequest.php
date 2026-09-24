<?php

namespace App\Http\Requests\Admin;

use App\Enums\UserRole;
use App\Models\User;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class UpdateUserRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('user')) ?? false;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        /** @var User $account */
        $account = $this->route('user');

        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'string', 'lowercase', 'email', 'max:255', Rule::unique(User::class)->ignore($account)],
            'role' => [
                'required',
                Rule::enum(UserRole::class),
                function (string $attribute, mixed $value, Closure $fail) use ($account): void {
                    if ($value !== $account->role->value && $this->user()->cannot('changeRole', $account)) {
                        $fail('You cannot change your own role.');
                    }
                },
            ],
            'password' => ['nullable', 'confirmed', Password::defaults()],
        ];
    }
}
