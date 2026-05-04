<?php

namespace Database\Factories;

use App\Models\User;
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
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $gender = fake()->randomElement(['Laki-laki', 'Perempuan']);
        $firstName = $gender === 'Laki-laki' ? fake()->firstNameMale() : fake()->firstNameFemale();
        $lastName = fake()->lastName();

        return [
            'first_name' => $firstName,
            'last_name' => $lastName,
            'username' => Str::slug($firstName.'.'.$lastName).fake()->unique()->numerify('###'),
            'photo' => 'default.jpg',
            'email' => fake()->unique()->safeEmail(),
            'phone' => '08'.fake()->numerify('##########'),
            'password' => static::$password ??= Hash::make('password'),
            'code' => Str::random(6),
            'remember_token' => Str::random(10),
            'gender' => $gender,
            'place_of_birth' => fake()->city(),
            'date_of_birth' => fake()->date('Y-m-d', '-18 years'),
            'religion' => fake()->randomElement(['Islam', 'Kristen', 'Katolik', 'Hindu', 'Buddha', 'Khonghucu']),
            'blood_type' => fake()->randomElement(['A', 'B', 'AB', 'O']),
            'citizenship' => fake()->randomElement(['WNI', 'WNA']),
            'height' => fake()->numberBetween(150, 190),
            'weight' => fake()->numberBetween(45, 100),
            'identity_number' => fake()->unique()->numerify('################'),
            'instagram' => '@'.fake()->userName(),
            'facebook' => 'facebook.com/'.fake()->userName(),
            'linkedin' => 'linkedin.com/in/'.fake()->userName(),
            'is_active' => true,
            'fst_setup' => false,
            'tfa_setup' => false,
        ];
    }
}
