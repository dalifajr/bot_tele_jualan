<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class NewChatMessageNotification extends Notification {
    use Queueable;
    public $messageText;
    public $senderName;
    public function __construct($messageText, $senderName) { $this->messageText = $messageText; $this->senderName = $senderName; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'pesan_baru',
            'title' => 'Pesan Baru',
            'message' => 'Dari ' . $this->senderName . ': ' . Str::limit($this->messageText, 30),
            'url' => url('/admin/chats'),
            'icon' => 'fas fa-envelope text-info'
        ];
    }
}