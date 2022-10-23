<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Support\Facades\Auth;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;

class RemovalEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;


    
    public $node_type;
    public $node;
    public $user;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($node_type, $node, $user)
    {
        //

        $this->node_type = $node_type;
        $this->node = $node;
        $this->user = $user;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        return new PrivateChannel('validating.'.Auth::user()->validator_id);
    }

    public function broadcastWhen()
    {
        return Auth::user()->validator_id != null;
    }
}
