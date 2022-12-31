<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Messages\BroadcastMessage;

class InfoNotification extends Notification
{

    /**
     * Create a new notification instance.
     *
     * @return void
     */


    // public $node_type;
    public $object;
    public $msg;
    public $attachment;
    public $user_from;


    public function __construct($object, $msg, $attachment, $user)
    {
        //
        // $this->afterCommit();
        // $this->node_type = $node_type;
        $this->object = $object;
        $this->msg = $msg;
        $this->attachment = $attachment;
        $this->user_from = $user;
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
            'object' => $this->object,
            'msg' => $this->msg,
            'attachment' => $this->attachment,
            'user_from' => "M. ".$this->user_from->name[0].".".$this->user_from->second_name,
        ]);
    }

    public function broadcastType()
    {
        return 'Information';
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
//    public function toArray($notifiable)
//    {
//        return [
//            //
//        ];
//    }

    public function withDelay($notifiable)
    {
        return [
            // 'mail' => now()->addMinutes(5),
            // 'sms' => now()->addMinutes(10),
            'broadcast' => now(),
        ];
    }
}
