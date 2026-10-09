<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
 * A varredura que não nasce de clique: ordem com fim previsto já passado, conta
 * batendo na data e o compromisso de amanhã. Uma vez por dia no relógio do
 * servidor, e o `withoutOverlapping` não deixa duas varreduras empilhadas se a
 * anterior atrasar. No desenvolvimento, `php artisan schedule:work` faz o papel
 * do cron — em produção é o cron quem chama, e nada aqui depende do navegador.
 */
Schedule::command('nf:notificacoes:diaria')
    ->dailyAt('07:00')
    ->withoutOverlapping()
    ->name('notificacoes-diarias');
