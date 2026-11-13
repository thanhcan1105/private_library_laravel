<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Notifications\Messages\MailMessage;

class ResetPasswordNotification extends ResetPassword
{
    public function toMail($notifiable): MailMessage
    {
        $url = route('password.reset', [
            'token' => $this->token,
            'email' => $notifiable->getEmailForPasswordReset(),
        ]);
        $broker = config('auth.defaults.passwords');
        $expire = (int) config("auth.passwords.{$broker}.expire", 60);

        return (new MailMessage)
            ->subject('Đặt lại mật khẩu thư viện')
            ->greeting('Xin chào!')
            ->line('Bạn nhận được thư này vì đã có yêu cầu đặt lại mật khẩu cho tài khoản của mình.')
            ->action('Đặt lại mật khẩu', $url)
            ->line("Liên kết có hiệu lực trong {$expire} phút.")
            ->line('Nếu bạn không yêu cầu đặt lại mật khẩu, hãy bỏ qua thư này.');
    }
}
