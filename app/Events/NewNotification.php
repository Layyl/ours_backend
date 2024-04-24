<?php

namespace App\Events;

use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PresenceChannel;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class NewNotification implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;
    public $notification;
    public $user_id;
    public $notificationType;
    public $referralID;
    public $ri;
    public $referralHistoryID;
    public $sent_to;
    public $sent_date;
    public $sent_time;
    public $safru;
    
    /**
     * Create a new event instance.
     */
    public function __construct($notification, $user_id, $notificationType, $referralID, $ri, $referralHistoryID, $sent_to, $sent_date, $sent_time,$safru)
    {
        $this->notification = $notification;
        $this->user_id = $user_id;
        $this->notificationType = $notificationType;
        $this->referralID = $referralID;
        $this->ri = $ri;
        $this->referralHistoryID = $referralHistoryID;
        $this->sent_to = $sent_to;
        $this->sent_date = $sent_date;
        $this->sent_time = $sent_time;
        $this->safru = $safru;
    }

    /**
     * Get the channels the event should broadcast on.
     *
     * @return array<int, \Illuminate\Broadcasting\Channel>
     */

    public function broadcastOn(): array
    {
        return [
            new Channel('notification'),
        ];
    }
}
