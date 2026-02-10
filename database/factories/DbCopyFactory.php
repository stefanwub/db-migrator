<?php

namespace Database\Factories;

use App\Models\DbCopy;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DbCopy>
 */
class DbCopyFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\DbCopy>
     */
    protected $model = DbCopy::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'pending',
            'progress' => null,
            'source_connection' => 'mysql',
            'source_db' => 'source_db',
            'dest_connection' => 'mysql',
            'dest_db' => 'dest_db',
            'callback_url' => 'https://example.test/callback',
            'created_by_user_id' => User::factory(),
        ];
    }
}
