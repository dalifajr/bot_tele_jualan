<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use App\Models\ComplaintCase;

class ComplaintNotification extends Notification
{
    use Queueable;

    protected $complaint;
    protected $type;
    protected $message;

    /**
     * Create a new notification instance.
     * $type can be 'new' or 'status_update'
     */
    public function __construct(ComplaintCase $complaint, $type = 'new', $message = '')
    {
        $this->complaint = $complaint;
        $this->type = $type;
        $this->message = $message;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['database'];
    }

    /**
     * Get the mail representation of the notification.
     */
    public function toMail(object $notifiable): MailMessage
    {
        return (new MailMessage)
            ->line('The introduction to the notification.')
            ->action('Notification Action', url('/'))
            ->line('Thank you for using our application!');
    }

    /**
     * Get the array representation of the notification.
     *
     * @return array<string, mixed>
     */
    public function toArray(object $notifiable): array
    {
        $title = $this->type === 'new' ? 'Komplain Baru' : 'Update Komplain';
        $icon = $this->type === 'new' ? 'fa-exclamation-circle text-danger' : 'fa-info-circle text-info';
        $url = '#';

        if ($this->type === 'new') {
            $url = route('seller.complaints.show', $this->complaint->id);
            if (empty($this->message)) {
                $this->message = "Pelanggan mengajukan komplain pada pesanan {$this->complaint->order_ref_snapshot}";
            }
        } else {
            $url = route('customer.complaints.show', $this->complaint->id);
            if (empty($this->message)) {
                $this->message = "Status komplain {$this->complaint->complaint_ref} diperbarui menjadi: {$this->complaint->status}";
            }
        }

        return [
            'title' => $title,
            'message' => $this->message,
            'icon' => $icon,
            'url' => $url,
            'complaint_id' => $this->complaint->id
        ];
    }
}
