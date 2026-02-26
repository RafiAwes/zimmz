<?php

namespace App\Events;

use App\Models\Message;
use Illuminate\Broadcasting\Channel;
use Illuminate\Broadcasting\InteractsWithSockets;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcast;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MessageSent implements ShouldBroadcast
{
    use Dispatchable, InteractsWithSockets, SerializesModels;

    public $message;

    public function __construct(Message $message)
    {
        $this->message = $message;
    }

    public function broadcastOn(): array
    {
        return [

            new Channel('chat'),
            // new PrivateChannel('chat.'.$this->message->receiver_id),
        ];
    }

    public function broadcastWith()
    {
        return [
            'message' => $this->message,
            'sender' => $this->message->sender,
            'time' => $this->message->created_at->toTimeString(),
        ];
    }
}
