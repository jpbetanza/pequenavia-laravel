<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

/**
 * E-mail que convida a secretaria a continuar a configuração da agenda, com o próximo passo.
 */
class ContinuarPrimeirosPassos extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly string $proximoPasso) {}

    /**
     * @return list<string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject('Sua agenda no PequenaVia está quase pronta')
            ->greeting('Olá!')
            ->line('Você começou a configurar a agenda da paróquia e falta pouco.')
            ->line($this->proximoPasso)
            ->action('Continuar', route('inicio'))
            ->line('Se preferir fazer depois, é só ignorar este e-mail.');
    }
}
