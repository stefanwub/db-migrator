<?php

use App\Jobs\CopyMysqlWithMydumperDynamicCreds;
use App\Models\DbCopy;
use App\Models\User;
use App\Services\DbCopyWebhookNotifier;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;
use Laravel\Sanctum\Sanctum;

test('authentication is required for creating a DB copy', function (): void {
    $response = $this->postJson('/api/db-copies', []);

    $response->assertUnauthorized();
});

test('create request validates payload and dispatches job', function (): void {
    Queue::fake();

    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $payload = [
        'source' => [
            'connection' => 'mysql',
            'database' => 'source_db1',
        ],
        'destination' => [
            'connection' => 'mysql',
            'database' => 'dest_db1',
        ],
        'threads' => 8,
        'recreateDestination' => true,
        'callback_url' => 'https://example.com/webhook',
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/db-copies', $payload);

    $response->assertCreated();
    $response->assertJsonStructure([
        'id',
        'status',
    ]);

    $id = $response->json('id');

    /** @var DbCopy $dbCopy */
    $dbCopy = DbCopy::query()->findOrFail($id);

    expect($dbCopy->status)->toBe('queued');
    expect($dbCopy->source_connection)->toBe('mysql');
    expect($dbCopy->source_db)->toBe('source_db1');
    expect($dbCopy->dest_connection)->toBe('mysql');
    expect($dbCopy->dest_db)->toBe('dest_db1');
    expect($dbCopy->created_by_user_id)->toBe($user->id);

    Queue::assertPushed(CopyMysqlWithMydumperDynamicCreds::class, function (CopyMysqlWithMydumperDynamicCreds $job) use ($dbCopy): bool {
        return $job->dbCopyId === $dbCopy->id
            && $job->sourceConnection === 'mysql'
            && $job->destinationConnection === 'mysql';
    });
});

test('create request rejects invalid database names', function (): void {
    $user = User::factory()->create();
    $token = $user->createToken('test')->plainTextToken;

    $payload = [
        'source' => [
            'connection' => 'mysql',
            'database' => 'invalid-db-name',
        ],
        'destination' => [
            'connection' => 'mysql',
            'database' => 'dest_db1',
        ],
        'threads' => 8,
        'recreateDestination' => true,
        'callback_url' => 'https://example.com/webhook',
    ];

    $response = $this->withHeaders([
        'Authorization' => 'Bearer '.$token,
    ])->postJson('/api/db-copies', $payload);

    $response->assertUnprocessable();
    $response->assertJsonValidationErrors([
        'source.database',
    ]);
});

test('users can only view their own DB copies', function (): void {
    $owner = User::factory()->create();
    $otherUser = User::factory()->create();

    $dbCopy = DbCopy::query()->create([
        'id' => (string) Str::uuid(),
        'status' => 'queued',
        'source_connection' => 'mysql',
        'source_db' => 'source_db1',
        'dest_connection' => 'mysql',
        'dest_db' => 'dest_db1',
        'callback_url' => 'https://example.com/webhook',
        'created_by_user_id' => $owner->id,
    ]);

    // Owner can view
    Sanctum::actingAs($owner);

    $this->getJson("/api/db-copies/{$dbCopy->id}")
        ->assertOk()
        ->assertJson([
            'id' => $dbCopy->id,
        ]);

    // Other user is forbidden
    Sanctum::actingAs($otherUser);

    $this->getJson("/api/db-copies/{$dbCopy->id}")
        ->assertForbidden();
});

test('webhook is called with signed payload on status changes', function (): void {
    Http::fake();

    config()->set('services.db_copy_webhook.secret', 'test-secret');

    $user = User::factory()->create();

    $dbCopy = DbCopy::query()->create([
        'id' => (string) Str::uuid(),
        'status' => 'queued',
        'source_connection' => 'mysql',
        'source_db' => 'source_db1',
        'dest_connection' => 'mysql',
        'dest_db' => 'dest_db1',
        'callback_url' => 'https://example.com/webhook',
        'created_by_user_id' => $user->id,
    ]);

    // Use a test-specific subclass that skips the external commands.
    $job = new class($dbCopy->id, 'mysql', 'source_db1', 'mysql', 'dest_db1', 4, true) extends CopyMysqlWithMydumperDynamicCreds
    {
        protected function prepareDestinationDatabase(): void {}

        protected function runMydumperAndMyloader(): void {}
    };

    $notifier = app(DbCopyWebhookNotifier::class);

    $job->handle($notifier);

    Http::assertSentCount(2);

    Http::assertSent(function ($request) use ($dbCopy): bool {
        $data = $request->data();

        $expectedSignature = hash_hmac(
            'sha256',
            json_encode($data, JSON_UNESCAPED_SLASHES),
            'test-secret',
        );

        return $request->url() === $dbCopy->callback_url
            && $request->hasHeader('X-Signature', $expectedSignature)
            && $data['id'] === $dbCopy->id;
    });
});

test('stripDumpFileHeaders removes target header lines from sql files', function (): void {
    $dumpDir = storage_path('app/test-dump-strip-'.uniqid());
    File::ensureDirectoryExists($dumpDir);

    $sqlPath = $dumpDir.'/table1.sql';
    $content = <<<'SQL'
/*!40101 SET NAMES binary*/;
/*!40014 SET FOREIGN_KEY_CHECKS=0*/;
SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;
/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;
CREATE TABLE `table1` (
  `id` int NOT NULL,
  PRIMARY KEY (`id`)
);
SQL;
    File::put($sqlPath, $content);

    $job = new CopyMysqlWithMydumperDynamicCreds(
        dbCopyId: 'test-id',
        sourceConnection: 'mysql',
        sourceDatabase: 'source',
        destinationConnection: 'mysql',
        destinationDatabase: 'dest',
    );

    $method = (new \ReflectionClass($job))->getMethod('stripDumpFileHeaders');
    $method->setAccessible(true);
    $method->invoke($job, $dumpDir);

    $result = File::get($sqlPath);

    expect($result)->not->toContain('/*!40101 SET NAMES binary*/;');
    expect($result)->not->toContain('/*!40014 SET FOREIGN_KEY_CHECKS=0*/;');
    expect($result)->not->toContain('SET @OLD_CHARACTER_SET_CLIENT=@@CHARACTER_SET_CLIENT;');
    expect($result)->not->toContain('/*!40101 SET @OLD_CHARACTER_SET_RESULTS=@@CHARACTER_SET_RESULTS */;');
    expect($result)->toContain('CREATE TABLE `table1`');

    File::deleteDirectory($dumpDir);
});
