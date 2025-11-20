<?php

use App\Http\Controllers\Admin\AuthController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Here is where you can register web routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| contains the "web" middleware group. Now create something great!
|
*/

Route::get('/', function () {
    return view('welcome');
});
Route::get('/email-templates', function () {
    return view('emailTemplates');
});

Route::group(['prefix' => 'admin'], function(){
    Route::get('/reset-password/{token}', [AuthController::class, "getResetPassword"])->name('getResetPassword.admin');
    Route::match(['get', 'post'], '/send-reset-password/{token}', [AuthController::class, "resetPassword"])->name('resetPassword.admin');
});

// Route::view('/','resetPasswordForm');
