<?php

namespace Database\Factories;

use App\Models\DbCopyRun;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\DbCopyRun>
 */
class DbCopyRunFactory extends Factory
{
    /**
     * The name of the factory's corresponding model.
     *
     * @var class-string<\App\Models\DbCopyRun>
     */
    protected $model = DbCopyRun::class;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'status' => 'queued',
            'source_system_db_connection' => 'mysql-source-system',
            'source_system_db_name' => 'source_system',
            'source_admin_app_connection' => 'mysql-admin-app',
            'source_admin_app_name' => 'admin_app',
            'source_db_connection' => 'mysql-source',
            'dest_db_connections' => ['mysql-dest-a', 'mysql-dest-b'],
            'started_at' => null,
            'finished_at' => null,
            'created_by_user_id' => null,
        ];
    }
}
