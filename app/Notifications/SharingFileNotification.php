<?php

namespace App\Notifications;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class SharingFileNotification extends Notification
{
//    use Queueable;

    private $path;


    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($path)
    {
        //
        $this->path = $path;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['mail'];
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        return (new MailMessage)
            ->from(Auth::user()->email, Auth::user()->name)
            ->subject("Partage de fichier(s)")
            ->greeting("Salution, Mr. $notifiable->name !!")
            ->line("Voici le(s) fichier(s) que vous aviez demandé(s):")
            ->attach($this->path)
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
}
