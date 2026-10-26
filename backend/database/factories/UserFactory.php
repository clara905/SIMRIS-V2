<?php

namespace Database\Factories;

use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<User> */
class UserFactory extends Factory
{
    protected $model = User::class;

    /** @return array<string, mixed> */
    public function definition(): array
    {
        return ['pegawai_id' => null, 'username' => fake()->unique()->userName(), 'email_login' => fake()->unique()->safeEmail(), 'password_hash' => 'password-testing', 'aktif' => true, 'wajib_ganti_password' => false];
    }
}
