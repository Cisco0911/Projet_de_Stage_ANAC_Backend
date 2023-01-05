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
        return ['broadcast', 'mail'];
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

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
//        $action = $this->operation == "deletion" ? "supprimer" : "modifier";

        $attachment = json_decode($this->attachment);
        $lines = [];

        foreach ($attachment as $key => $value)
        {
            array_push($lines, "$key: $value");
        }

        return (new MailMessage)
            ->subject("Information: $this->object")
            ->greeting("Salution, Mr. $notifiable->name !!")
            ->line($this->msg)
            ->line("par "."Mr. ".$this->user_from->name[0].".".$this->user_from->second_name)
            ->line("Information supplémentaire:")
            ->line($lines)
            ->action("Accéder à l'application", env("FRONTEND_URL"))
            ->line("Cordialement,")
            ->salutation("GESTIONNAIRE DE FICHIER ANAC.");
    }


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
