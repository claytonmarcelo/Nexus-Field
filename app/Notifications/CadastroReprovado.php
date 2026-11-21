<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CadastroReprovado extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $protocolo,
        public readonly string $motivo,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $mensagem = (new MailMessage)
            ->subject('Cadastro não aprovado — '.$this->protocolo)
            ->line("O pedido {$this->protocolo} não foi aprovado nesta análise.");

        if ($this->motivo !== '') {
            $mensagem->line('Motivo: '.$this->motivo);
        }

        return $mensagem->line('Se discordar, entre em contato com a plataforma informando o protocolo.');
    }
}
