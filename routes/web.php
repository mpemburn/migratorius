<?php

use App\Http\Controllers\HomeController;
use App\Http\Controllers\MigrationController;
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

Auth::routes();

Route::get('/', [HomeController::class, 'index'])->name('home');
Route::get('/migrator', [MigrationController::class, 'index'])->name('migrator');
Route::get('/subsites', 'App\Http\Controllers\MigrationController@getSubsites')->name('subsites');
Route::post('/do_migration', 'App\Http\Controllers\MigrationController@migration')->name('migration');
