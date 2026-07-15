<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Str;

class NewChatMessageNotification extends Notification {
    use Queueable;
    public $messageText;
    public $senderName;
    public $senderId;

    public function __construct($messageText, $senderName, $senderId) {
        $this->messageText = $messageText; 
        $this->senderName = $senderName;
        $this->senderId = $senderId;
    }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        $msgText = $this->messageText ?: 'Mengirimkan lampiran/media';
        return [
            'type' => 'pesan_baru',
            'title' => 'Pesan Baru',
            'message' => 'Dari ' . $this->senderName . ': ' . Str::limit($msgText, 30),
            'url' => url('/chat?contact_id=' . $this->senderId),
            'icon' => 'fas fa-envelope text-info'
        ];
    }
}