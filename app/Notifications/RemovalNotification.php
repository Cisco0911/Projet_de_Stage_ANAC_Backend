<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class RemovalNotification extends Notification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */


    public $node_type;
    public $node;
    public $user;


    public function __construct($node_type, $node, $user)
    {
        //
        // $this->afterCommit();
        $this->node_type = $node_type;
        $this->node = $node;
        $this->user = $user;
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
     * @return \Illuminate\Notifications\Messages\MailMessage
     */


    public function toBroadcast($notifiable)
    {
        return new BroadcastMessage([
            'node_type' => $this->node_type,
            'node' => $this->node,
            'user' => $this->user,
        ]);
    }

    public function broadcastType()
    {
        return 'NodeRemovalNotification';
    }

    // public function toMail($notifiable)
    // {
    //     return (new MailMessage)
    //                 ->line('The introduction to the notification.')
    //                 ->action('Notification Action', url('/'))
    //                 ->line('Thank you for using our application!');
    // }


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
