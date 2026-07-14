<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class SystemEventNotification extends Notification {
    use Queueable;
    public $eventTitle;
    public $eventMessage;
    public function __construct($eventTitle, $eventMessage) { $this->eventTitle = $eventTitle; $this->eventMessage = $eventMessage; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'system_event',
            'title' => $this->eventTitle,
            'message' => $this->eventMessage,
            'url' => '#',
            'icon' => 'fas fa-cogs text-secondary'
        ];
    }
}