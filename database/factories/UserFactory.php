<?php

namespace Database\Factories;

use App\Enums\UserRole;
use App\Enums\UserStatus;
use App\Models\User;
use App\Models\Usertype;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state, mirroring an active LRMIS account.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'firstname' => fake()->firstName(),
            'middlename' => null,
            'lastname' => fake()->lastName(),
            'extension_name' => null,
            'gender' => fake()->randomElement(['Male', 'Female']),
            'birthday' => fake()->dateTimeBetween('-60 years', '-20 years'),
            'username' => fake()->unique()->userName(),
            'password' => static::$password ??= Hash::make('password'),
            'email' => fake()->unique()->safeEmail(),
            'contact_number' => fake()->phoneNumber(),
            'photo' => null,
            'usertype_id' => Usertype::factory(),
            'station_id' => (string) Str::uuid(),
            'status' => UserStatus::Active,
            'remember_token' => Str::random(10),
        ];
    }

    /**
     * Indicate that the user administers the helpdesk.
     */
    public function admin(): static
    {
        return $this->afterCreating(fn (User $user) => $user->assignRole(UserRole::Admin));
    }

    /**
     * Indicate that LRMIS has not approved the account yet.
     */
    public function pending(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Pending,
        ]);
    }

    /**
     * Indicate that LRMIS has deactivated the account.
     */
    public function deactivated(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => UserStatus::Deactivated,
        ]);
    }
}
