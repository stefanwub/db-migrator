<?php

use App\Models\DbCopy;
use App\Models\DbCopyRun;
use App\Models\User;
use Inertia\Testing\AssertableInertia as Assert;

test('authenticated users can view latest db copy runs on index', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $ownRun = DbCopyRun::factory()->create([
        'created_by_user_id' => $user->id,
    ]);
    DbCopy::factory()->create([
        'created_by_user_id' => $user->id,
        'db_copy_run_id' => $ownRun->id,
    ]);

    $otherRun = DbCopyRun::factory()->create([
        'created_by_user_id' => $otherUser->id,
    ]);
    DbCopy::factory()->create([
        'created_by_user_id' => $otherUser->id,
        'db_copy_run_id' => $otherRun->id,
    ]);

    $response = $this->actingAs($user)->get(route('db-copy-runs.index'));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('DbCopyRuns/Index')
        ->has('availableConnections')
        ->has('dbCopyRuns.data', 1)
        ->where('dbCopyRuns.data.0.id', $ownRun->id)
        ->has('dbCopyRuns.data.0.status')
        ->has('dbCopyRuns.data.0.duration_human')
    );
});

test('run detail page loads for owner and shows copies associated to that run for the authenticated user', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $run = DbCopyRun::factory()->create([
        'created_by_user_id' => $user->id,
    ]);

    $ownCopy = DbCopy::factory()->create([
        'created_by_user_id' => $user->id,
        'db_copy_run_id' => $run->id,
    ]);

    DbCopy::factory()->create([
        'created_by_user_id' => $otherUser->id,
        'db_copy_run_id' => $run->id,
    ]);

    $response = $this->actingAs($user)->get(route('db-copy-runs.show', $run));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('DbCopyRuns/Show')
        ->where('dbCopyRun.id', $run->id)
        ->has('dbCopyRun.status')
        ->has('dbCopyRun.duration_human')
        ->has('copies', 1)
        ->where('copies.0.id', $ownCopy->id)
    );
});

test('users cannot view run detail for another users run', function (): void {
    $user = User::factory()->create();
    $otherUser = User::factory()->create();

    $run = DbCopyRun::factory()->create([
        'created_by_user_id' => $otherUser->id,
    ]);

    DbCopy::factory()->create([
        'created_by_user_id' => $otherUser->id,
        'db_copy_run_id' => $run->id,
    ]);

    $response = $this->actingAs($user)->get(route('db-copy-runs.show', $run));

    $response->assertForbidden();
});

test('run detail page can be viewed before any copies are created', function (): void {
    $user = User::factory()->create();

    $run = DbCopyRun::factory()->create([
        'created_by_user_id' => $user->id,
    ]);

    $response = $this->actingAs($user)->get(route('db-copy-runs.show', $run));

    $response->assertOk();
    $response->assertInertia(fn (Assert $page) => $page
        ->component('DbCopyRuns/Show')
        ->where('dbCopyRun.id', $run->id)
        ->has('copies', 0)
    );
});
