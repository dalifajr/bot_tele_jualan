<?php
namespace App\Notifications;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
class FailedLoginNotification extends Notification {
    use Queueable;
    public $ipAddress;
    public function __construct($ipAddress) { $this->ipAddress = $ipAddress; }
    public function via($notifiable) { return ['database']; }
    public function toArray($notifiable) {
        return [
            'type' => 'login_gagal',
            'title' => 'Percobaan Login Gagal',
            'message' => 'Ada percobaan login gagal dari IP: ' . $this->ipAddress,
            'ip_address' => $this->ipAddress,
            'url' => url('/admin/logins'),
            'icon' => 'fas fa-shield-alt text-danger'
        ];
    }
}