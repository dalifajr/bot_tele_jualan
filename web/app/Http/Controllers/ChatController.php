<?php

namespace App\Http\Controllers;

use App\Models\ChatMessage;
use App\Models\User;
use App\Models\Order;
use App\Models\OrderItem;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;

class ChatController extends Controller
{
    /**
     * Display the chat dashboard.
     */
    public function index(Request $request)
    {
        $user = Auth::user();
        $selectedContactId = $request->query('contact_id');
        $selectedContact = null;

        // Get list of recent contacts
        // A contact is someone who has sent/received messages from/to Auth::id()
        $sentMessageUserIds = ChatMessage::where('sender_id', $user->id)
            ->pluck('receiver_id')
            ->toArray();

        $receivedMessageUserIds = ChatMessage::where('receiver_id', $user->id)
            ->pluck('sender_id')
            ->toArray();

        $contactIds = array_unique(array_merge($sentMessageUserIds, $receivedMessageUserIds));

        // If a specific contact_id was requested but not in history, append it
        if ($selectedContactId && !in_array($selectedContactId, $contactIds)) {
            $contactIds[] = $selectedContactId;
        }

        // Fetch user profiles for these contacts
        $contacts = User::whereIn('id', $contactIds)
            ->where('id', '!=', $user->id)
            ->get()
            ->map(function ($contact) use ($user) {
                // Get last message
                $lastMsg = ChatMessage::where(function ($query) use ($user, $contact) {
                        $query->where('sender_id', $user->id)->where('receiver_id', $contact->id);
                    })
                    ->orWhere(function ($query) use ($user, $contact) {
                        $query->where('sender_id', $contact->id)->where('receiver_id', $user->id);
                    })
                    ->orderBy('created_at', 'desc')
                    ->first();

                // Get unread count
                $unreadCount = ChatMessage::where('sender_id', $contact->id)
                    ->where('receiver_id', $user->id)
                    ->where('is_read', false)
                    ->count();

                $contact->last_message = $lastMsg ? $lastMsg->message : null;
                $contact->last_message_time = $lastMsg ? $lastMsg->created_at : null;
                $contact->unread_count = $unreadCount;
                return $contact;
            })
            ->sortByDesc('last_message_time');

        if ($selectedContactId) {
            $selectedContact = User::find($selectedContactId);
        } elseif ($contacts->isNotEmpty()) {
            $selectedContact = $contacts->first();
        }

        return view('chat.index', compact('contacts', 'selectedContact'));
    }

    /**
     * Fetch message history between logged-in user and specified contact.
     */
    public function fetchMessages($contactId)
    {
        $user = Auth::user();
        
        // Mark received messages from this contact as read
        ChatMessage::where('sender_id', $contactId)
            ->where('receiver_id', $user->id)
            ->where('is_read', false)
            ->update(['is_read' => true]);

        $messages = ChatMessage::where(function ($query) use ($user, $contactId) {
                $query->where('sender_id', $user->id)->where('receiver_id', $contactId);
            })
            ->orWhere(function ($query) use ($user, $contactId) {
                $query->where('sender_id', $contactId)->where('receiver_id', $user->id);
            })
            ->orderBy('created_at', 'asc')
            ->get();

        return response()->json([
            'success' => true,
            'messages' => $messages->map(function ($msg) use ($user) {
                return [
                    'id' => $msg->id,
                    'message' => e($msg->message),
                    'is_mine' => $msg->sender_id === $user->id,
                    'time' => $msg->created_at->format('H:i'),
                ];
            })
        ]);
    }

    /**
     * Send a new chat message.
     */
    public function sendMessage(Request $request)
    {
        $request->validate([
            'receiver_id' => 'required|integer|exists:users,id',
            'message' => 'required|string|max:5000',
        ]);

        $msg = ChatMessage::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->input('receiver_id'),
            'message' => $request->input('message'),
            'is_read' => false,
        ]);

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $msg->id,
                'message' => e($msg->message),
                'is_mine' => true,
                'time' => $msg->created_at->format('H:i'),
            ]
        ]);
    }
}
