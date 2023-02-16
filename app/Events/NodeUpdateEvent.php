<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Support\Facades\Auth;

class NodeUpdateEvent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

//    public $afterCommit = true;


//    public $node_type;
    public $node;
    public $operation;
    public $services;

    /**
     * Create a new event instance.
     *
     * @return void
     */
    public function __construct($services, $node, $operation)
    {
        //

//        $this->node_type = $node_type;
        $this->node = $node;
        $this->operation = $operation;
        $this->services = $services;

    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return \Illuminate\Broadcasting\Channel|array
     */
    public function broadcastOn()
    {
        $channels = [];

        foreach ($this->services as $service)
        {
//            throw new \Exception( $service->id );
            array_push($channels, new PrivateChannel("nodeUpdate.{$service->id}"));
        }

        return $channels;
    }
}
