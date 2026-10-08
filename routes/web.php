<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Clients\AddressController;
use App\Http\Controllers\Clients\ClientContactController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\WelcomeController;
use Illuminate\Support\Facades\Route;

Route::get('/', [WelcomeController::class, 'index'])->name('welcome');

Route::middleware('guest')->group(function () {
    Route::get('login', [LoginController::class, 'create'])->name('login');
    Route::post('login', [LoginController::class, 'store'])->middleware('throttle:10,1');

    Route::get('forgot-password', [PasswordResetController::class, 'request'])->name('password.request');
    Route::post('forgot-password', [PasswordResetController::class, 'email'])->name('password.email')->middleware('throttle:5,1');
    Route::get('reset-password/{token}', [PasswordResetController::class, 'reset'])->name('password.reset');
    Route::post('reset-password', [PasswordResetController::class, 'update'])->name('password.update')->middleware('throttle:10,1');
});

Route::post('logout', [LoginController::class, 'destroy'])->name('logout')->middleware('auth');

Route::middleware(['auth', 'company'])->group(function () {
    Route::get('dashboard', DashboardController::class)
        ->middleware('permission:dashboard.view')
        ->name('dashboard');

    Route::prefix('clientes')->name('clients.')->group(function () {
        // Literal antes de parametrizado: `clientes/novo` precisa ser lido como
        // "tela de cadastro", não como a ficha do cliente de código "novo".
        Route::middleware('permission:clients.view')->group(function () {
            Route::get('/', [ClientController::class, 'index'])->name('index');
            Route::get('exportar', [ClientController::class, 'export'])
                ->middleware('permission:clients.export')
                ->name('export');
        });

        Route::middleware('permission:clients.create')->group(function () {
            Route::get('novo', [ClientController::class, 'create'])->name('create');
            Route::post('/', [ClientController::class, 'store'])->name('store');
        });

        Route::middleware('permission:clients.update')->group(function () {
            Route::get('{cliente}/editar', [ClientController::class, 'edit'])->name('edit');
            Route::put('{cliente}', [ClientController::class, 'update'])->name('update');
            Route::post('{cliente}/contatos', [ClientContactController::class, 'store'])->name('contacts.store');
            Route::put('{cliente}/contatos/{contato}', [ClientContactController::class, 'update'])->name('contacts.update');
            Route::post('{cliente}/enderecos', [AddressController::class, 'store'])->name('addresses.store');
            Route::put('{cliente}/enderecos/{endereco}', [AddressController::class, 'update'])->name('addresses.update');
        });

        Route::middleware('permission:clients.delete')->group(function () {
            Route::delete('{cliente}', [ClientController::class, 'destroy'])->name('destroy');
            Route::patch('{cliente}/restaurar', [ClientController::class, 'restore'])->name('restore');
            Route::delete('{cliente}/contatos/{contato}', [ClientContactController::class, 'destroy'])->name('contacts.destroy');
            Route::delete('{cliente}/enderecos/{endereco}', [AddressController::class, 'destroy'])->name('addresses.destroy');
        });

        Route::get('{cliente}', [ClientController::class, 'show'])
            ->middleware('permission:clients.view')
            ->name('show');
    });
});
