<?php

namespace App\Notifications;

use Illuminate\Auth\Notifications\VerifyEmail;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Support\Facades\Crypt;
class CustomVerifyEmailNotification extends VerifyEmail
{
    protected $user;

    public function __construct($user)
    {
        $this->user = $user;
    }

    public function toMail($notifiable)
    {
        $verificationUrl = $this->verificationUrl($notifiable);

        return (new MailMessage)
            ->subject('JBLMGH-OURS | Verify Email')
            ->view('verify', ['url' => $verificationUrl, 'user' => $this->user]);
    }

    protected function verificationUrl($notifiable)
    {
        $params = [
            'id' => $notifiable->getKey(),
            'hash' => sha1($notifiable->getEmailForVerification()),
            'em' => Crypt::encryptString($notifiable->getEmailForVerification()),
            'token' => $this->createToken($notifiable),
        ];

        $url = env('FRONT_APP') . '/verify-email?';

        foreach ($params as $key => $param) {
            $url .= "{$key}={$param}&";
        }

        return $url;
    }

    protected function createToken($notifiable)
    {
        return app('auth.password.broker')->createToken($notifiable);
    }
}
