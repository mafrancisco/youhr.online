<?php

namespace App\Notifications;

use App\Models\SaaS\Company;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class LicenseKeyGenerated extends Notification
{
    use Queueable;

    public function __construct(
        private Company $company,
        private string $licenseKey,
        private ?string $expiresAt = null,
    ) {}

    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $message = (new MailMessage())
            ->subject('Your license key for ' . $this->company->name)
            ->greeting('Hello ' . ($this->company->owner_google_email ? $this->company->owner_google_email : ''))
            ->line('Your license key has been generated for ' . $this->company->name . '.')
            ->line('License Key: ' . $this->licenseKey)
            ->line('Company: ' . $this->company->name . ' (' . $this->company->slug . ')');

        if ($this->expiresAt) {
            $message->line('Expiration: ' . $this->expiresAt);
        }

        return $message
            ->line('Use this key on the license activation page to unlock your workspace.')
            ->salutation('Regards, ' . config('app.name'));
    }
}
