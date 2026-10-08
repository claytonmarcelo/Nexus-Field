<?php

use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Clients\AddressController;
use App\Http\Controllers\Clients\ClientContactController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Technicians\SpecialtyController;
use App\Http\Controllers\Technicians\TeamController;
use App\Http\Controllers\Technicians\TechnicianAddressController;
use App\Http\Controllers\Technicians\TechnicianController;
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

    Route::prefix('tecnicos')->name('technicians.')->group(function () {
        Route::middleware('permission:technicians.view')->group(function () {
            Route::get('/', [TechnicianController::class, 'index'])->name('index');
        });

        Route::middleware('permission:technicians.create')->group(function () {
            Route::get('novo', [TechnicianController::class, 'create'])->name('create');
            Route::post('/', [TechnicianController::class, 'store'])->name('store');
        });

        Route::middleware('permission:technicians.update')->group(function () {
            Route::get('{tecnico}/editar', [TechnicianController::class, 'edit'])->name('edit');
            Route::put('{tecnico}', [TechnicianController::class, 'update'])->name('update');
            Route::post('{tecnico}/enderecos', [TechnicianAddressController::class, 'store'])->name('addresses.store');
            Route::put('{tecnico}/enderecos/{endereco}', [TechnicianAddressController::class, 'update'])->name('addresses.update');
        });

        Route::middleware('permission:technicians.delete')->group(function () {
            Route::delete('{tecnico}', [TechnicianController::class, 'destroy'])->name('destroy');
            Route::delete('{tecnico}/enderecos/{endereco}', [TechnicianAddressController::class, 'destroy'])->name('addresses.destroy');
            Route::patch('{tecnico}/restaurar', [TechnicianController::class, 'restore'])->name('restore');
        });

        Route::get('{tecnico}', [TechnicianController::class, 'show'])
            ->middleware('permission:technicians.view')
            ->name('show');
    });

    Route::prefix('equipes')->name('teams.')->group(function () {
        Route::get('/', [TeamController::class, 'index'])
            ->middleware('permission:teams.view')
            ->name('index');

        Route::middleware('permission:teams.create')->group(function () {
            Route::get('nova', [TeamController::class, 'create'])->name('create');
            Route::post('/', [TeamController::class, 'store'])->name('store');
        });

        Route::middleware('permission:teams.update')->group(function () {
            Route::get('{equipe}/editar', [TeamController::class, 'edit'])->name('edit');
            Route::put('{equipe}', [TeamController::class, 'update'])->name('update');
            Route::post('{equipe}/membros', [TeamController::class, 'adicionarMembro'])->name('members.store');
            Route::delete('{equipe}/membros/{tecnico}', [TeamController::class, 'liberarMembro'])->name('members.destroy');
        });

        Route::middleware('permission:teams.delete')->group(function () {
            Route::delete('{equipe}', [TeamController::class, 'destroy'])->name('destroy');
        });

        Route::get('{equipe}', [TeamController::class, 'show'])
            ->middleware('permission:teams.view')
            ->name('show');
    });

    // A especialidade é competência de técnico: quem enxerga a escala mexe aqui.
    Route::prefix('especialidades')->name('specialties.')->group(function () {
        Route::get('/', [SpecialtyController::class, 'index'])
            ->middleware('permission:technicians.view')
            ->name('index');

        Route::post('/', [SpecialtyController::class, 'store'])
            ->middleware('permission:technicians.create')
            ->name('store');

        Route::put('{especialidade}', [SpecialtyController::class, 'update'])
            ->middleware('permission:technicians.update')
            ->name('update');

        Route::delete('{especialidade}', [SpecialtyController::class, 'destroy'])
            ->middleware('permission:technicians.delete')
            ->name('destroy');
    });
});
