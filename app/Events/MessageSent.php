<?php

namespace App\Events;

use App\Models\Chat;
use Illuminate\Broadcasting\Channel;
use Illuminate\Queue\SerializesModels;
use Illuminate\Broadcasting\PrivateChannel;
use Illuminate\Contracts\Broadcasting\ShouldBroadcastNow;
use Illuminate\Broadcasting\InteractsWithSockets;

class MessageSent implements ShouldBroadcastNow
{
    use InteractsWithSockets, SerializesModels;

    public $chat;
    public function __construct(Chat $chat)
    {
        $this->chat = $chat->load('sender'); // تحميل العلاقة
    }

    public function broadcastOn()
    {
        return new PrivateChannel('chat.' . $this->chat->item_offer_id);
    }
    public function broadcastWith()
    {
        return [
            'id' => $this->chat->id,
            'message' => $this->chat->message,
            'sender' => $this->chat->sender,
            'created_at' => $this->chat->created_at,
            'message_type' => $this->chat->message_type,
            'file' => $this->chat->file,
            'audio' => $this->chat->audio
        ];
    }
    public function broadcastAs()
    {
        return 'message.sent';
    }
}
