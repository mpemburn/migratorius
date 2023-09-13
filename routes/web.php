<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MigrationController;
use App\Models\DbMigration;
use App\Services\SubsiteService;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider and all of them will
| be assigned to the "web" middleware group. Make something great!
|
*/
Route::get('/dev', function () {
    $service = new \App\Services\MigrateTablesService();
    \App\Services\DatabaseService::setDb('sites_clarku');
    $service->buildTables('sites_clarku', 32);
    // Do what thou wilt
});

Auth::routes();

// TODO: straighten out the routes
Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/home', [HomeController::class, 'index'])->name('home');
Route::get('/migrator', [MigrationController::class, 'index'])->name('migrator');
Route::get('/subsites', 'App\Http\Controllers\MigrationController@getSubsites')->name('subsites');
Route::get('/undoable', 'App\Http\Controllers\MigrationController@getUndoableSubsites')->name('undoable');
Route::post('/do_migration', 'App\Http\Controllers\MigrationController@migration')->name('migration');
Route::post('/remove', 'App\Http\Controllers\MigrationController@removeSubsite')->name('remove');
