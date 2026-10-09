<?php

use App\Http\Controllers\Agenda\AgendaController;
use App\Http\Controllers\Auth\LoginController;
use App\Http\Controllers\Auth\PasswordResetController;
use App\Http\Controllers\Catalog\ProductController;
use App\Http\Controllers\Catalog\ServiceCategoryController;
use App\Http\Controllers\Catalog\ServiceController;
use App\Http\Controllers\Clients\AddressController;
use App\Http\Controllers\Clients\ClientContactController;
use App\Http\Controllers\Clients\ClientController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\Orders\CheckinController;
use App\Http\Controllers\Orders\OrderAssignmentController;
use App\Http\Controllers\Orders\OrderController;
use App\Http\Controllers\Orders\OrderItemController;
use App\Http\Controllers\Stock\MovementController;
use App\Http\Controllers\Technicians\SpecialtyController;
use App\Http\Controllers\Technicians\TeamController;
use App\Http\Controllers\Technicians\TechnicianAddressController;
use App\Http\Controllers\Technicians\TechnicianController;
use App\Http\Controllers\Tickets\TicketCommentController;
use App\Http\Controllers\Tickets\TicketController;
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

    Route::prefix('ordens')->name('orders.')->group(function () {
        // Literal antes de parametrizado: `ordens/nova` é a tela de cadastro, não
        // a ficha da ordem de código "nova".
        Route::middleware('permission:orders.view')->group(function () {
            Route::get('/', [OrderController::class, 'index'])->name('index');

            Route::get('exportar', [OrderController::class, 'export'])
                ->middleware('permission:orders.export')
                ->name('export');
        });

        Route::middleware('permission:orders.create')->group(function () {
            Route::get('nova', [OrderController::class, 'create'])->name('create');
            Route::post('/', [OrderController::class, 'store'])->name('store');
        });

        // Quem move a ordem no campo (execute) também ajusta os dados dela
        // (update); aprovar e cancelar, porém, é decisão de outro nível e fica
        // dentro do controlador, não na rota.
        Route::middleware('permission:orders.update,orders.execute')->group(function () {
            Route::get('{ordem}/editar', [OrderController::class, 'edit'])->name('edit');
            Route::put('{ordem}', [OrderController::class, 'update'])->name('update');
            Route::put('{ordem}/estado', [OrderController::class, 'mudarStatus'])->name('status');

            Route::post('{ordem}/itens', [OrderItemController::class, 'store'])->name('items.store');
            Route::put('{ordem}/itens/{item}', [OrderItemController::class, 'update'])->name('items.update');
            Route::delete('{ordem}/itens/{item}', [OrderItemController::class, 'destroy'])->name('items.destroy');

            Route::post('{ordem}/tecnicos', [OrderAssignmentController::class, 'store'])->name('assignments.store');
            Route::delete('{ordem}/tecnicos/{tecnico}', [OrderAssignmentController::class, 'destroy'])->name('assignments.destroy');

            // Registrar presença é conduzir a execução: `orders.execute`. Quem é o
            // técnico da passagem, porém, não vem do formulário — vem da ficha de
            // quem está logado, e a resposta para quem não tem ficha de técnico é
            // dada dentro do controlador, com a permissão de aprovação no meio.
            Route::post('{ordem}/chegada', [CheckinController::class, 'store'])->name('checkin');
        });

        Route::delete('{ordem}', [OrderController::class, 'destroy'])
            ->middleware('permission:orders.delete')
            ->name('destroy');

        Route::get('{ordem}', [OrderController::class, 'show'])
            ->middleware('permission:orders.view')
            ->name('show');
    });

    /*
     * As passagens do campo têm tela própria porque a pergunta do escritório não é
     * "o que está escrito nesta ordem?", é "quem esteve onde, quando e por quanto
     * tempo?". A chegada continua aninhada na ordem (`ordens/{ordem}/chegada`),
     * porque é ela que responde pelo endereço medido; a saída é do registro, porque
     * uma ordem pode ter dois técnicos em campo ao mesmo tempo.
     */
    Route::prefix('visitas')->name('checkins.')->group(function () {
        Route::get('/', [CheckinController::class, 'index'])
            ->middleware('permission:orders.view')
            ->name('index');

        Route::get('exportar', [CheckinController::class, 'export'])
            ->middleware('permission:orders.export')
            ->name('export');

        // Fechar a passagem é ato de quem conduziu a execução — ou de quem responde
        // pela escala, que é o degrau acima. O supervisor aprova a ordem mas não
        // conduz o próprio campo, e ainda assim precisa poder encerrar uma visita
        // que ficou aberta num celular sem bateria.
        Route::patch('{visita}/saida', [CheckinController::class, 'checkout'])
            ->middleware('permission:orders.execute,orders.approve')
            ->name('checkout');
    });

    /*
     * O estoque se escreve por movimentação, não por campo de quantidade: a tela
     * que mostra o saldo é a de produtos (fase 11) e esta é o livro-caixa que o
     * formou. Registrar é `stock.move`; ajustar inventário é `stock.adjust`, o
     * degrau de quem responde pelo saldo da empresa — dentro do controlador cada
     * tipo responde pela sua, porque a rota não sabe qual tipo foi escolhido.
     */
    Route::prefix('estoque')->name('movements.')->group(function () {
        Route::get('/', [MovementController::class, 'index'])
            ->middleware('permission:stock.view')
            ->name('index');

        Route::get('exportar', [MovementController::class, 'export'])
            ->middleware('permission:stock.export')
            ->name('export');

        Route::middleware('permission:stock.move,stock.adjust')->group(function () {
            Route::get('registrar', [MovementController::class, 'create'])->name('create');
            Route::post('/', [MovementController::class, 'store'])->name('store');
        });
    });

    Route::prefix('chamados')->name('tickets.')->group(function () {
        // Literal antes de parametrizado: `chamados/novo` é a tela de abertura, não
        // a ficha do chamado de código "novo".
        Route::middleware('permission:tickets.view')->group(function () {
            Route::get('/', [TicketController::class, 'index'])->name('index');

            Route::get('exportar', [TicketController::class, 'export'])
                ->middleware('permission:tickets.export')
                ->name('export');

            // Responder é continuar a conversa que se tem o direito de ler: todos os
            // papéis do módulo podem, inclusive a conta de cliente.
            Route::post('{chamado}/notas', [TicketCommentController::class, 'store'])->name('comments.store');
        });

        Route::middleware('permission:tickets.create')->group(function () {
            Route::get('novo', [TicketController::class, 'create'])->name('create');
            Route::post('/', [TicketController::class, 'store'])->name('store');
        });

        Route::middleware('permission:tickets.update')->group(function () {
            Route::get('{chamado}/editar', [TicketController::class, 'edit'])->name('edit');
            Route::put('{chamado}', [TicketController::class, 'update'])->name('update');
        });

        // `tickets.execute` é quem conduz a máquina de estados do chamado: escritório e
        // campo. A conta de cliente lê a carteira e responde, mas não move estado — por
        // isso a rota não aceita `tickets.view`. Dentro do controlador, resolver e fechar
        // ainda pedem `tickets.close`, que é decisão de encerramento.
        Route::put('{chamado}/estado', [TicketController::class, 'mudarStatus'])
            ->middleware('permission:tickets.execute')
            ->name('status');

        Route::get('{chamado}', [TicketController::class, 'show'])
            ->middleware('permission:tickets.view')
            ->name('show');
    });

    Route::prefix('agenda')->name('agenda.')->group(function () {
        // Literal antes de parametrizado: `agenda/nova` é a tela de marcação, não a
        // ficha do compromisso de código "nova". `eventos` é o JSON do calendário.
        Route::middleware('permission:agenda.view')->group(function () {
            Route::get('/', [AgendaController::class, 'index'])->name('index');
            Route::get('eventos', [AgendaController::class, 'feed'])->name('feed');
        });

        Route::middleware('permission:agenda.create')->group(function () {
            Route::get('nova', [AgendaController::class, 'create'])->name('create');
            Route::post('/', [AgendaController::class, 'store'])->name('store');
        });

        // Mover a janela no quadro é editar a agenda: `agenda.update` segura o
        // arraste, o PATCH e a mudança de estado, e a técnica vê sem poder mover.
        Route::middleware('permission:agenda.update')->group(function () {
            Route::get('{compromisso}/editar', [AgendaController::class, 'edit'])->name('edit');
            Route::put('{compromisso}', [AgendaController::class, 'update'])->name('update');
            Route::put('{compromisso}/estado', [AgendaController::class, 'mudarStatus'])->name('status');
            Route::patch('{compromisso}/janela', [AgendaController::class, 'reagendar'])->name('reschedule');
        });

        Route::delete('{compromisso}', [AgendaController::class, 'destroy'])
            ->middleware('permission:agenda.delete')
            ->name('destroy');

        Route::get('{compromisso}', [AgendaController::class, 'show'])
            ->middleware('permission:agenda.view')
            ->name('show');
    });

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

    Route::prefix('servicos')->name('services.')->group(function () {
        Route::get('/', [ServiceController::class, 'index'])
            ->middleware('permission:services.view')
            ->name('index');

        Route::middleware('permission:services.create')->group(function () {
            Route::get('novo', [ServiceController::class, 'create'])->name('create');
            Route::post('/', [ServiceController::class, 'store'])->name('store');
        });

        Route::middleware('permission:services.update')->group(function () {
            Route::get('{servico}/editar', [ServiceController::class, 'edit'])->name('edit');
            Route::put('{servico}', [ServiceController::class, 'update'])->name('update');
        });

        Route::middleware('permission:services.delete')->group(function () {
            Route::delete('{servico}', [ServiceController::class, 'destroy'])->name('destroy');
            Route::patch('{servico}/restaurar', [ServiceController::class, 'restore'])->name('restore');
        });

        Route::get('{servico}', [ServiceController::class, 'show'])
            ->middleware('permission:services.view')
            ->name('show');
    });

    Route::prefix('produtos')->name('products.')->group(function () {
        Route::get('/', [ProductController::class, 'index'])
            ->middleware('permission:products.view')
            ->name('index');

        Route::middleware('permission:products.create')->group(function () {
            Route::get('novo', [ProductController::class, 'create'])->name('create');
            Route::post('/', [ProductController::class, 'store'])->name('store');
        });

        Route::middleware('permission:products.update')->group(function () {
            Route::get('{produto}/editar', [ProductController::class, 'edit'])->name('edit');
            Route::put('{produto}', [ProductController::class, 'update'])->name('update');
        });

        Route::middleware('permission:products.delete')->group(function () {
            Route::delete('{produto}', [ProductController::class, 'destroy'])->name('destroy');
            Route::patch('{produto}/restaurar', [ProductController::class, 'restore'])->name('restore');
        });

        Route::get('{produto}', [ProductController::class, 'show'])
            ->middleware('permission:products.view')
            ->name('show');
    });

    // A categoria agrupa serviço, então quem cuida do catálogo de serviços cuida dela.
    Route::prefix('categorias-de-servico')->name('categories.')->group(function () {
        Route::get('/', [ServiceCategoryController::class, 'index'])
            ->middleware('permission:services.view')
            ->name('index');

        Route::post('/', [ServiceCategoryController::class, 'store'])
            ->middleware('permission:services.create')
            ->name('store');

        Route::put('{categoria}', [ServiceCategoryController::class, 'update'])
            ->middleware('permission:services.update')
            ->name('update');

        Route::delete('{categoria}', [ServiceCategoryController::class, 'destroy'])
            ->middleware('permission:services.delete')
            ->name('destroy');
    });
});
