<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewChatMessage implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $message;
    public $user_id;
    public $username;
    public $referralID;
    public $sent_date;
    public $sent_time;
    
    /**
     * Create a new event instance.
     */
    public function __construct($message, $user_id, $username, $referralID, $sent_date, $sent_time)
    {
        $this->message = $message;
        $this->user_id = $user_id;
        $this->username = $username;
        $this->referralID = $referralID;
        $this->sent_date = $sent_date;
        $this->sent_time = $sent_time;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */
    public function broadcastOn(): array
    {
        return [
            new Channel('chat'),
        ];
    }
}
