<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class ConfirmacaoDeCadastro extends Notification
{
    use Queueable;

    public function __construct(
        public readonly string $emailToken,
        public readonly string $accessToken,
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
        $url = route('registration.confirm', ['token' => $this->emailToken, 'acesso' => $this->accessToken]);

        return (new MailMessage)
            ->subject('Confirme o e-mail do cadastro '.$this->protocolo)
            ->greeting('Olá!')
            ->line("Recebemos o pedido de cadastro de {$this->razaoSocial} sob o protocolo {$this->protocolo}.")
            ->line('Confirme seu e-mail para seguir com o envio dos documentos.')
            ->action('Confirmar e-mail', $url)
            ->line('Este link expira em 48 horas. Se você não fez este pedido, ignore esta mensagem.');
    }
}
