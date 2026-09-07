<?php

use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Auth;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\MatrixController;

/*
|--------------------------------------------------------------------------
| Web Routes
|--------------------------------------------------------------------------
|
| Semua rute web terdaftar di sini. Rute pemantauan radiasi dan dashboard
| dilindungi oleh middleware auth sehingga mewajibkan login terlebih dahulu.
|
*/

// Root URL: Redirect to Login page if guest, or to Matrix if already authenticated
Route::get('/', function () {
    if (Auth::check()) {
        return redirect()->route('matrix');
    }
    return redirect()->route('login');
});

// Guest routes (accessible only before login)
Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'showLogin'])->name('login');
    Route::post('/login', [AuthController::class, 'login']);
    Route::get('/register', [AuthController::class, 'showRegister'])->name('register');
    Route::post('/register', [AuthController::class, 'register']);
});

// Protected routes (Requires authentication)
Route::middleware('auth')->group(function () {
    Route::post('/logout', [AuthController::class, 'logout'])->name('logout');
    
    // Matrix Scanner & Dashboard routes
    Route::get('/matrix', [MatrixController::class, 'index'])->name('matrix');
    Route::get('/dashboard', function() { return redirect()->route('matrix'); })->name('dashboard');
    Route::get('/api/matrix/history', [MatrixController::class, 'getHistory'])->name('matrix.history');
    Route::post('/api/matrix/save-session', [MatrixController::class, 'saveSession'])->name('matrix.save_session');
    Route::get('/api/matrix/export-csv', [MatrixController::class, 'downloadCsv'])->name('matrix.export_csv');
    
    // User Profile
    Route::get('/profile', [ProfileController::class, 'show'])->name('profile');
    Route::post('/profile', [ProfileController::class, 'update'])->name('profile.update');
});

