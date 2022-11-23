<?php

namespace App\Notifications;

use App\Http\Controllers\NonConformiteController;
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


    private $fncId;
    private $anticipated;
    private $object;
    private $msg;



    public function __construct($fncId, $anticipated = false)
    {
        $this->fncId = $fncId;
        $this->anticipated = $anticipated;
        $this->object = "Rappel de Revision";

        $fnc = NonConformiteController::find($this->fncId);

        $this->msg = $this->anticipated ?
            "Il reste 15 jours pour la revision de la ".$fnc->name :
            "Revision de la ".$fnc->name." aujourd'hui"
        ;
    }

    public function getFncId()
    {
        return $this->fncId;
    }

    public function isAnticipated()
    {
        return $this->anticipated;
    }

    public function getMsg()
    {
        return $this->msg;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
        return ['broadcast', 'database'];
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
            'msg' => $this->object."\n".$this->msg
        ]);
    }

    public function broadcastType()
    {
        return 'FncReviewNotification';
    }

    /**
     * Get the array representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function toDatabase($notifiable)
    {
        return [
            'fncId' => $this->fncId,
            'object' => $this->object,
            'msg' => $this->msg,
        ];
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
