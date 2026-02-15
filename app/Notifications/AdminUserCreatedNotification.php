<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class AdminUserCreatedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    /**
     * Create a new notification instance.
     */
    public function __construct(
        protected string $temporaryPassword,
        protected ?string $createdByName = null,
        protected bool $isReset = false
    ) {}

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        $organizationName = 'DepEd Southern Leyte Division';
        $loginUrl = route('login', absolute: true);
        $logoUrl = url('/images/deped-southern-leyte-logo.jpg');

        return (new MailMessage)
            ->subject($this->isReset ? $organizationName.' Password Reset' : $organizationName.' Account Created')
            ->view('emails.admin-user-credentials', [
                'organizationName' => $organizationName,
                'recipientName' => $notifiable->name,
                'recipientEmail' => $notifiable->email,
                'temporaryPassword' => $this->temporaryPassword,
                'loginUrl' => $loginUrl,
                'logoUrl' => $logoUrl,
                'createdByName' => $this->createdByName ?? 'System Administrator',
                'isReset' => $this->isReset,
            ]);
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        return [
            'created_by' => $this->createdByName,
            'is_reset' => $this->isReset,
        ];
    }
}
