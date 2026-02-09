<?php

use App\Models\DbCopy;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Laravel\Fortify\Features;

Route::get('/', function () {
    return Inertia::render('Welcome', [
        'canRegister' => Features::enabled(Features::registration()),
    ]);
})->name('home');

Route::get('dashboard', function () {
    return redirect()->route('db-copies.index');
})->middleware(['auth', 'verified'])->name('dashboard');

Route::get('db-copies', function (Request $request) {
    $dbCopies = DbCopy::query()
        ->where('created_by_user_id', $request->user()?->id)
        ->latest()
        ->paginate(20);

    return Inertia::render('DbCopies/Index', [
        'dbCopies' => $dbCopies,
    ]);
})->middleware(['auth', 'verified'])->name('db-copies.index');

require __DIR__.'/settings.php';
