<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class RemovalResponse extends Notification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */


    // public $node_type;
    public $msg;
    public $userFrom;


    public function __construct($msg, $user)
    {
        //
        // $this->afterCommit();
        // $this->node_type = $node_type;
        $this->msg = $msg;
        $this->userFrom = $user;
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
            // 'node_type' => $this->node_type,
            'msg' => $this->msg,
            'userFrom' => $this->userFrom,
        ]);
    }

    public function broadcastType()
    {
        return 'NodeRemovalResponse';
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
