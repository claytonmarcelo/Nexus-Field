<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CadastroAprovado extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $protocolo,
        public readonly string $empresa,
        public readonly string $link,
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
            ->line('Sua conta administradora está pronta. Defina sua senha pelo link abaixo antes de entrar.')
            ->action('Definir senha e entrar', $this->link)
            ->line('Nenhuma senha é enviada por e-mail.');
    }
}
