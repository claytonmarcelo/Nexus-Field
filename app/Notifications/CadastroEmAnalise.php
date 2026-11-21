<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CadastroEmAnalise extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $protocolo,
        public readonly string $razaoSocial,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Novo cadastro em análise: '.$this->protocolo)
            ->line("A empresa {$this->razaoSocial} enviou documentos e aguarda análise.")
            ->line('Protocolo: '.$this->protocolo);
    }
}
