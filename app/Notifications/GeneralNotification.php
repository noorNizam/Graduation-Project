<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;
use NotificationChannels\Fcm\FcmChannel;
use NotificationChannels\Fcm\FcmMessage;
use NotificationChannels\Fcm\Resources\Notification as FcmNotification;

class GeneralNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public int $tries = 6;

    public array $backoff = [5, 20, 60, 300, 900];

    public function __construct(
        public string $title,
        public string $body,
        public array $data = []
    ) {}

    public function via($notifiable)
    {
        return [FcmChannel::class];
    }

    public function toFcm($notifiable)
    {
        return FcmMessage::create()
            ->notification(
                FcmNotification::create()
                    ->title($this->title)
                    ->body($this->body)
            )
            ->data(array_map(fn ($v) => (string) $v, $this->data));
    }
}
