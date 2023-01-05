<?php

namespace App\Notifications;

use App\Models\checkList;
use App\Models\DossierPreuve;
use App\Models\Nc;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\BroadcastMessage;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;
use Illuminate\Support\Facades\Auth;

class AskPermission extends Notification
{
//    use Queueable;

    public $node_id;
    public $model;
    public $node_name;
    private $node_path;
    public $operation;
    public $from_id;
    public $full_name;

    /**
     * Create a new notification instance.
     *
     * @return void
     */
    public function __construct($node, $operation)
    {
        //
        $this->node_id = $node->id;
        $this->model = get_class($node);
        if ($node instanceof checkList) $this->node_name = "CheckList de ".$node->audit->name;
        elseif ($node instanceof DossierPreuve) $this->node_name = "Dossier preuve de ".$node->audit->name;
        elseif ($node instanceof Nc) $this->node_name = "Dossier Nc de ".$node->audit->name;
        else $this->node_name = $node->name;
        $this->node_path = $node->path->value;

        $this->operation = $operation;

        $sender = Auth::user();
        $this->from_id = $sender->id;
        $this->full_name = $sender->name[0].'.'.$sender->second_name;
    }

    /**
     * Get the notification's delivery channels.
     *
     * @param  mixed  $notifiable
     * @return array
     */
    public function via($notifiable)
    {
//        return ['mail'];
        return ['database', 'broadcast', 'mail'];
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
            'from_id' => $this->from_id,
            'name' => $this->full_name,
        ]);
    }

    public function broadcastType()
    {
        return 'AskPermission';
    }

    /**
     * Get the mail representation of the notification.
     *
     * @param  mixed  $notifiable
     * @return \Illuminate\Notifications\Messages\MailMessage
     */
    public function toMail($notifiable)
    {
        $action = $this->operation == "deletion" ? "supprimer" : "modifier";
        return (new MailMessage)
                    ->subject("Notification de demande de permission")
                    ->greeting("Salution, Mr. $notifiable->name !!")
                    ->line("L'inspecteur $this->full_name demande permission pour $action: $this->node_path")
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

    public function toDatabase(mixed $notifiable)
    {
        return [
            'node_id' => $this->node_id,
            'model' => $this->model,
            'node_name' => $this->node_name,
            'operation' => $this->operation,
            'from_id' => $this->from_id,
            'full_name' => $this->full_name
        ];
    }

}
