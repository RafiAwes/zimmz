<?php

namespace App\Http\Controllers\Api;

use App\Events\MessageSent;
use App\Http\Controllers\Controller; // Don't forget to import your Event!
use App\Models\Message;
use App\Traits\ApiResponseTraits;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

class MessageController extends Controller
{
    use ApiResponseTraits;

    // 1. Send a Message
    public function sendMessage(Request $request)
    {
        $sender = Auth::guard('api')->user();

        $request->validate([
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required|string',
        ]);

        try {
            // Save to Database first (Persistence)
            $message = Message::create([
                'sender_id' => $sender->id,
                'receiver_id' => $request->receiver_id,
                'message' => $request->message,
                'is_read' => false,
            ]);

            // Broadcast to Socket.io (Real-time)
            // This fires the event we created earlier, pushing data to Redis -> Node.js
            broadcast(new MessageSent($message))->toOthers();

            return $this->successResponse($message, 'Message sent successfully.', 201);

        } catch (\Exception $e) {
            return $this->errorResponse('Failed to send message', 500, $e->getMessage());
        }
    }

    // 2. Get Conversation History
    public function getMessages($userId)
    {
        $myId = Auth::guard('api')->id();

        // Robust Logic: Fetch messages where (Sender is ME and Receiver is YOU)
        // OR (Sender is YOU and Receiver is ME)
        $messages = Message::where(function ($q) use ($myId, $userId) {
            $q->where('sender_id', $myId)
                ->where('receiver_id', $userId);
        })->orWhere(function ($q) use ($myId, $userId) {
            $q->where('sender_id', $userId)
                ->where('receiver_id', $myId);
        })
            ->orderBy('created_at', 'asc') // Oldest first for chat UI
            ->get();

        return $this->successResponse($messages, 'Conversation retrieved.', 200);
    }

    // 3. Mark Messages as Read (Optional but Recommended)
    public function markAsRead($senderId)
    {
        $myId = Auth::guard('api')->id();

        Message::where('sender_id', $senderId)
            ->where('receiver_id', $myId)
            ->where('is_read', false)
            ->update(['is_read' => true]);
        $data = Message::where('receiver_id', $senderId)
            ->where('receiver_id', $myId)
            ->get();

        return $this->successResponse($data, 'Messages marked as read.', 200);
    }
}
