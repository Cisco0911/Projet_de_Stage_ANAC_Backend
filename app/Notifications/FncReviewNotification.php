<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class FncReviewNotification extends Notification
{
//    use Queueable;

    /**
     * Create a new notification instance.
     *
     * @return void
     */


    public $fncId;


    public function __construct($fncId)
    {
        $this->fncId = $fncId;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['broadcast'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return BroadcastMessage
     */

    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'fncId' => $this->fncId,
        ]);
    }

    public function broadcastType()
    {
        return 'FncReviewNotification';
    }


//    public function toMail($notifiable)
//    {
//        return (new MailMessage)
//                    ->line('The introduction to the notification.')
//                    ->action('Notification Action', url('/'))
//                    ->line('Thank you for using our application!');
//    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toArray($notifiable)
    {
        return [
            //
        ];
    }

    public function withDelay($notifiable)
    {
        return [
            // 'mail' => now()->addMinutes(5),
            // 'sms' => now()->addMinutes(10),
            'broadcast' => now(),
        ];
    }
}
