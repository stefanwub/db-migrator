<?php

namespace Database\Factories;

use App\Models\DbCopy;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DbCopyRow>
 */
class DbCopyRowFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'db_copy_id' => DbCopy::factory(),
            'name' => 'table_'.$this->faker->randomNumber(),
            'dump_file_path' => '/tmp/'.$this->faker->randomNumber().'.sql',
            'status' => 'pending',
            'source_row_count' => $this->faker->numberBetween(0, 10000),
            'dest_row_count' => null,
            'source_size' => $this->faker->numberBetween(0, 10000000),
            'dest_size' => null,
        ];
    }
}
