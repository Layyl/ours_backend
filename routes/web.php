<?php

use App\Events\WebSocketDemo;
use App\Http\Controllers\testController;
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

Route::get('/', function () {
    broadcast(new WebSocketDemo('asdfasdfasd!!!'));
    return view('welcome');
});

Route::get('test', [testController::class, 'test']);