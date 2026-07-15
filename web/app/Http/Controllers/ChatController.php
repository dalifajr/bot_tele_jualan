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

        $contact = User::find($contactId);

        return response()->json([
            'success' => true,
            'contact_online' => $contact ? $contact->isOnline() : false,
            'contact_last_active' => $contact ? $contact->last_active_label : 'Offline',
            'messages' => $messages->map(function ($msg) use ($user) {
                return [
                    'id' => $msg->id,
                    'message' => e($msg->message),
                    'attachment_path' => $msg->attachment_path ? asset('storage/' . $msg->attachment_path) : null,
                    'attachment_type' => $msg->attachment_type,
                    'is_read' => $msg->is_read,
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
            'message' => 'required_without:attachment|nullable|string|max:5000',
            'attachment' => 'nullable|file|mimes:jpeg,png,jpg,gif,mp4,mov,avi,webm|max:102400',
        ], [
            'message.required_without' => 'Pesan atau lampiran harus diisi.',
            'attachment.max' => 'Ukuran maksimal lampiran adalah 100MB.',
            'attachment.mimes' => 'Format lampiran yang didukung: foto atau video.',
        ]);

        $attachmentPath = null;
        $attachmentType = null;

        if ($request->hasFile('attachment')) {
            $file = $request->file('attachment');
            $attachmentPath = $file->store('chat_attachments', 'public');
            $mime = $file->getMimeType();
            $attachmentType = str_starts_with($mime, 'video/') ? 'video' : 'image';
        }

        $msg = ChatMessage::create([
            'sender_id' => Auth::id(),
            'receiver_id' => $request->input('receiver_id'),
            'message' => $request->input('message'),
            'attachment_path' => $attachmentPath,
            'attachment_type' => $attachmentType,
            'is_read' => false,
        ]);

        // Notify receiver
        $receiver = User::find($request->input('receiver_id'));
        if ($receiver) {
            $senderName = Auth::user()->full_name ?? Auth::user()->username;
            $receiver->notify(new \App\Notifications\NewChatMessageNotification($msg->message, $senderName, Auth::id()));
            
            // Send telegram notification if connected
            if ($receiver->telegram_id) {
                \App\Services\TelegramService::notifyUserNewChatMessage($receiver, Auth::user(), $msg);
            }
        }

        return response()->json([
            'success' => true,
            'message' => [
                'id' => $msg->id,
                'message' => e($msg->message),
                'attachment_path' => $msg->attachment_path ? asset('storage/' . $msg->attachment_path) : null,
                'attachment_type' => $msg->attachment_type,
                'is_read' => $msg->is_read,
                'is_mine' => true,
                'time' => $msg->created_at->format('H:i'),
            ]
        ]);
    }

    /**
     * Search eligible users to start chat with.
     */
    public function searchUsers(Request $request)
    {
        $search = $request->query('q', '');
        $user = Auth::user();

        if ($user->role === 'admin') {
            // Admin can search all users except themselves
            $users = User::where('id', '!=', $user->id)
                ->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->limit(20)
                ->get(['id', 'username', 'full_name', 'role']);
        } elseif ($user->role === 'seller') {
            // Seller can search admins, and customers who bought their products
            $adminIds = User::where('role', 'admin')->pluck('id')->toArray();
            
            $customerIds = Order::whereHas('items.product', function ($query) use ($user) {
                $query->where('creator_id', $user->id);
            })
            ->pluck('customer_id')
            ->unique()
            ->toArray();
            
            $eligibleIds = array_unique(array_merge($adminIds, $customerIds));

            $users = User::whereIn('id', $eligibleIds)
                ->where('id', '!=', $user->id)
                ->where(function ($query) use ($search) {
                    $query->where('username', 'like', "%{$search}%")
                        ->orWhere('full_name', 'like', "%{$search}%")
                        ->orWhere('email', 'like', "%{$search}%");
                })
                ->limit(20)
                ->get(['id', 'username', 'full_name', 'role']);
        } else {
            return response()->json(['success' => false, 'message' => 'Unauthorized'], 403);
        }

        return response()->json([
            'success' => true,
            'users' => $users->map(function ($u) {
                return [
                    'id' => $u->id,
                    'name' => $u->full_name ?? $u->username,
                    'username' => $u->username,
                    'role' => $u->role
                ];
            })
        ]);
    }
}
