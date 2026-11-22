<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class BroadcastAnnouncementNotification extends Notification
{
    use Queueable;

    public $title;
    public $message;
    public $mediaPath;
    public $mediaType;
    public ?int $broadcastJobId;

    public function __construct(
        string $message,
        ?string $title = null,
        ?string $mediaPath = null,
        ?string $mediaType = null,
        ?int $broadcastJobId = null
    ) {
        $this->message = $message;
        $this->title = $title ?: 'Pengumuman Penting dari Admin';
        $this->mediaPath = $mediaPath;
        $this->mediaType = $mediaType;
        $this->broadcastJobId = $broadcastJobId;
    }

    public function via($notifiable): array
    {
        return ['database'];
    }

    public function toArray($notifiable): array
    {
        return [
            'type' => 'broadcast_announcement',
            'broadcast_job_id' => $this->broadcastJobId,
            'title' => $this->title,
            'message' => $this->message,
            'media_path' => $this->mediaPath,
            'media_type' => $this->mediaType,
            'url' => route('notifications.index'),
            'icon' => 'fas fa-bullhorn text-warning',
        ];
    }
}
