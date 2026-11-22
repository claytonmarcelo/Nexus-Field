<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/** Aprovação para quem já entrou com cadastro de acesso comum. */
class CadastroAprovadoContaExistente extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $protocolo,
        public readonly string $empresa,
    ) {}

    /** @return array<int, string> */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Cadastro aprovado — '.$this->empresa)
            ->greeting('Olá!')
            ->line("O cadastro {$this->protocolo} da empresa {$this->empresa} foi aprovado.")
            ->line('Sua conta administradora já está vinculada à empresa. Entre com a senha que você cadastrou.')
            ->line('Nenhuma senha é enviada por e-mail.');
    }
}
