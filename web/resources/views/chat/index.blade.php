@extends('layouts.app')

@section('title', 'Pusat Chat')
@section('page_subtitle', 'Pesan')

@section('content')
<div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden; height: calc(100vh - 180px); min-height: 500px;">
    <div class="row g-0 h-100">
        {{-- Left Contacts List --}}
        <div class="col-md-4 border-end d-flex flex-column h-100" style="background-color: var(--bs-body-bg);">
            <div class="p-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fas fa-comments text-primary me-2"></i>Obrolan Saya</h5>
            </div>
            
            <div class="list-group list-group-flush overflow-y-auto flex-grow-1" style="overscroll-behavior: contain;">
                @forelse($contacts as $contact)
                    <a href="{{ route('chat.index', ['contact_id' => $contact->id]) }}" 
                       class="list-group-item list-group-item-action border-bottom py-3 px-4 d-flex align-items-center gap-3 {{ ($selectedContact && $selectedContact->id === $contact->id) ? 'active bg-light border-start border-primary border-4 text-dark' : '' }}"
                       style="{{ ($selectedContact && $selectedContact->id === $contact->id) ? 'border-left: 4px solid var(--bs-primary) !important;' : '' }}">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold small" style="width: 42px; height: 42px; min-width: 42px;">
                            {{ strtoupper(substr($contact->full_name ?? $contact->username, 0, 1)) }}
                        </div>
                        <div class="flex-grow-1 overflow-hidden">
                            <div class="d-flex justify-content-between align-items-center mb-1">
                                <span class="fw-bold small text-dark">{{ Str::limit($contact->full_name ?? $contact->username, 16) }}</span>
                                @if($contact->last_message_time)
                                    <span class="text-muted" style="font-size: 0.7rem;">{{ $contact->last_message_time->format('H:i') }}</span>
                                @endif
                            </div>
                            <div class="d-flex justify-content-between align-items-center">
                                <small class="text-muted text-truncate d-inline-block w-75">{{ $contact->last_message ?? 'Kirim pesan pertama Anda...' }}</small>
                                @if($contact->unread_count > 0)
                                    <span class="badge bg-danger rounded-pill" style="font-size: 0.7rem;">{{ $contact->unread_count }}</span>
                                @endif
                            </div>
                        </div>
                    </a>
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comment-slash fs-2 mb-2"></i>
                        <p class="small mb-0">Belum ada riwayat pesan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right Chat Box --}}
        <div class="col-md-8 d-flex flex-column h-100 bg-body-tertiary">
            @if($selectedContact)
                {{-- Chat Box Header --}}
                <div class="p-3 bg-white border-bottom d-flex align-items-center gap-3">
                    <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold small" style="width: 40px; height: 40px;">
                        {{ strtoupper(substr($selectedContact->full_name ?? $selectedContact->username, 0, 1)) }}
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">{{ $selectedContact->full_name ?? $selectedContact->username }}</h6>
                        <small class="text-muted small text-capitalize">{{ $selectedContact->role }}</small>
                    </div>
                </div>

                {{-- Message History Display Area --}}
                <div class="flex-grow-1 p-4 overflow-y-auto" id="chatHistory" style="height: 0; overscroll-behavior: contain;">
                    <div class="text-center text-muted small my-3">
                        <i class="fas fa-lock me-1"></i> Pesan terenkripsi secara aman
                    </div>
                    <div id="messageBubbleContainer">
                        {{-- Filled dynamically via Javascript --}}
                    </div>
                </div>

                {{-- Chat Form --}}
                <div class="p-3 bg-white border-top">
                    <form id="chatForm" class="d-flex gap-2">
                        @csrf
                        <input type="hidden" name="receiver_id" id="receiver_id" value="{{ $selectedContact->id }}">
                        <input type="text" name="message" id="messageInput" class="form-control rounded-pill px-4 border" placeholder="Tulis pesan Anda disini..." required autocomplete="off" style="height: 46px;">
                        <button type="submit" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; min-width: 46px;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>
                </div>
            @else
                <div class="flex-grow-1 d-flex flex-column align-items-center justify-content-center text-muted">
                    <i class="far fa-comments fs-1 mb-3 opacity-50"></i>
                    <h6 class="fw-bold">Pilih Obrolan</h6>
                    <p class="small text-muted mb-0">Pilih salah satu kontak di sebelah kiri untuk mulai mengobrol.</p>
                </div>
            @endif
        </div>
    </div>
</div>
@endsection

@push('scripts')
@if($selectedContact)
<script>
    document.addEventListener('DOMContentLoaded', function() {
        const chatHistory = document.getElementById('chatHistory');
        const container = document.getElementById('messageBubbleContainer');
        const chatForm = document.getElementById('chatForm');
        const messageInput = document.getElementById('messageInput');
        const receiverId = "{{ $selectedContact->id }}";
        
        let lastMessageCount = 0;

        const scrollToBottom = () => {
            chatHistory.scrollTop = chatHistory.scrollHeight;
        };

        // Render message bubbles
        const renderMessages = (messages) => {
            let html = '';
            messages.forEach(msg => {
                if (msg.is_mine) {
                    html += `
                        <div class="d-flex justify-content-end mb-3">
                            <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 70%; border-bottom-right-radius: 0 !important;">
                                <p class="mb-1 text-break" style="font-size: 0.92rem;">${msg.message}</p>
                                <div class="text-end text-white-50" style="font-size: 0.7rem;">${msg.time}</div>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="d-flex justify-content-start mb-3">
                            <div class="bg-white text-dark p-3 rounded-4 shadow-sm border border-light-subtle" style="max-width: 70%; border-bottom-left-radius: 0 !important;">
                                <p class="mb-1 text-break" style="font-size: 0.92rem;">${msg.message}</p>
                                <div class="text-muted" style="font-size: 0.7rem;">${msg.time}</div>
                            </div>
                        </div>
                    `;
                }
            });
            container.innerHTML = html;
        };

        // Fetch messages via API
        const loadMessages = () => {
            fetch(`/chat/messages/${receiverId}`)
                .then(res => res.json())
                .then(data => {
                    if (data.success) {
                        const count = data.messages.length;
                        renderMessages(data.messages);
                        
                        // If new messages arrived, scroll down
                        if (count > lastMessageCount) {
                            scrollToBottom();
                            lastMessageCount = count;
                        }
                    }
                })
                .catch(err => console.error("Error loading chat messages:", err));
        };

        // Initial Load
        loadMessages();

        // AJAX Polling (every 3 seconds)
        setInterval(loadMessages, 3000);

        // Submit form via AJAX
        chatForm.addEventListener('submit', function(e) {
            e.preventDefault();
            const messageText = messageInput.value.trim();
            if (!messageText) return;

            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            formData.append('message', messageText);
            formData.append('_token', "{{ csrf_token() }}");

            // Instant local append (optimistic UI)
            const timeNow = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            container.innerHTML += `
                <div class="d-flex justify-content-end mb-3">
                    <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 70%; border-bottom-right-radius: 0 !important; opacity: 0.7;">
                        <p class="mb-1 text-break" style="font-size: 0.92rem;">${escapeHtml(messageText)}</p>
                        <div class="text-end text-white-50" style="font-size: 0.7rem;">${timeNow} <i class="fas fa-spinner fa-spin ms-1"></i></div>
                    </div>
                </div>
            `;
            scrollToBottom();
            messageInput.value = '';

            fetch('/chat/send', {
                method: 'POST',
                body: formData,
                headers: {
                    'Accept': 'application/json'
                }
            })
            .then(res => res.json())
            .then(data => {
                if (data.success) {
                    loadMessages();
                }
            })
            .catch(err => {
                console.error("Failed to send message:", err);
                Swal.fire({
                    icon: 'error',
                    title: 'Pesan Gagal Terkirim',
                    text: 'Silakan periksa koneksi internet Anda.',
                    toast: true,
                    position: 'top-end',
                    showConfirmButton: false,
                    timer: 3000
                });
            });
        });

        function escapeHtml(text) {
            return text
                .replace(/&/g, "&amp;")
                .replace(/</g, "&lt;")
                .replace(/>/g, "&gt;")
                .replace(/"/g, "&quot;")
                .replace(/'/g, "&#039;");
        }
    });
</script>
@endif
@endpush
