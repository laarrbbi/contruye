<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword implements ShouldQueue
{
    use Queueable;

    /**
     * The SPA URL the user must visit to choose a new password.
     *
     * @param  mixed  $notifiable
     */
    public function resetUrl($notifiable): string
    {
        return rtrim(config('app.frontend_url'), '/')
            .'/reset-password?token='.$this->token
            .'&email='.urlencode($notifiable->getEmailForPasswordReset());
    }

    public function toMail($notifiable): MailMessage
    {
        return (new MailMessage)
            ->subject(__('Reset your password'))
            ->view('mail.reset-password', [
                'user' => $notifiable,
                'url' => $this->resetUrl($notifiable),
                'expireMinutes' => config('auth.passwords.'.config('auth.defaults.passwords').'.expire'),
            ]);
    }
}
