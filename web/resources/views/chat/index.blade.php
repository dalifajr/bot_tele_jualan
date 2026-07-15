@extends('layouts.app')

@section('title', 'Pusat Chat')
@section('page_subtitle', 'Pesan')

@section('content')
<div class="card border-0 shadow-sm" style="border-radius: 16px; overflow: hidden; height: calc(100vh - 180px); min-height: 500px;">
    <div class="row g-0 h-100">
        {{-- Left Contacts List --}}
        <div class="col-md-4 border-end d-flex flex-column h-100 {{ $selectedContact ? 'd-none d-md-flex' : '' }}" style="background-color: var(--bs-body-bg);">
            <div class="p-3 border-bottom">
                <h5 class="fw-bold mb-0"><i class="fas fa-comments text-primary me-2"></i>Obrolan Saya</h5>
            </div>
            
            <div class="list-group list-group-flush overflow-y-auto flex-grow-1" style="overscroll-behavior: contain;">
                @forelse($contacts as $contact)
                    @if($contact->unread_count > 0 && (!$selectedContact || $selectedContact->id !== $contact->id))
                        {{-- UNREAD STYLE --}}
                        <a href="{{ route('chat.index', ['contact_id' => $contact->id]) }}" 
                           class="list-group-item list-group-item-action border-bottom py-3 px-4 d-flex align-items-center gap-3 bg-primary-subtle"
                           style="border-left: 0px solid transparent;">
                            <div class="position-relative">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold small" style="width: 42px; height: 42px; min-width: 42px;">
                                    {{ strtoupper(substr($contact->full_name ?? $contact->username, 0, 1)) }}
                                </div>
                                @if($contact->isOnline())
                                    <span class="position-absolute bottom-0 end-0 bg-success border border-white border-2 rounded-circle" style="width: 12px; height: 12px;" title="Online"></span>
                                @endif
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small text-primary">{{ Str::limit($contact->full_name ?? $contact->username, 16) }}</span>
                                    <span class="badge bg-danger ms-2" style="font-size: 0.65rem;">BARU</span>
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-dark fw-bold text-truncate d-inline-block w-75">{{ $contact->last_message ?: 'Kirim pesan pertama Anda...' }}</small>
                                    @if($contact->last_message_time)
                                        <span class="text-muted" style="font-size: 0.7rem;">{{ $contact->last_message_time->format('H:i') }}</span>
                                    @endif
                                </div>
                            </div>
                        </a>
                    @else
                        {{-- NORMAL / ACTIVE STYLE --}}
                        <a href="{{ route('chat.index', ['contact_id' => $contact->id]) }}" 
                           class="list-group-item list-group-item-action border-bottom py-3 px-4 d-flex align-items-center gap-3 {{ ($selectedContact && $selectedContact->id === $contact->id) ? 'active bg-light border-start border-primary border-4 text-dark' : '' }}"
                           style="{{ ($selectedContact && $selectedContact->id === $contact->id) ? 'border-left: 4px solid var(--bs-primary) !important;' : '' }}">
                            <div class="position-relative">
                                <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold small" style="width: 42px; height: 42px; min-width: 42px;">
                                    {{ strtoupper(substr($contact->full_name ?? $contact->username, 0, 1)) }}
                                </div>
                                @if($contact->isOnline())
                                    <span class="position-absolute bottom-0 end-0 bg-success border border-white border-2 rounded-circle" style="width: 12px; height: 12px;" title="Online"></span>
                                @endif
                            </div>
                            <div class="flex-grow-1 overflow-hidden">
                                <div class="d-flex justify-content-between align-items-center mb-1">
                                    <span class="fw-bold small text-dark">{{ Str::limit($contact->full_name ?? $contact->username, 16) }}</span>
                                    @if($contact->last_message_time)
                                        <span class="text-muted" style="font-size: 0.7rem;">{{ $contact->last_message_time->format('H:i') }}</span>
                                    @endif
                                </div>
                                <div class="d-flex justify-content-between align-items-center">
                                    <small class="text-muted text-truncate d-inline-block w-100">{{ $contact->last_message ?: 'Kirim pesan pertama Anda...' }}</small>
                                </div>
                            </div>
                        </a>
                    @endif
                @empty
                    <div class="text-center py-5 text-muted">
                        <i class="fas fa-comment-slash fs-2 mb-2"></i>
                        <p class="small mb-0">Belum ada riwayat pesan.</p>
                    </div>
                @endforelse
            </div>
        </div>

        {{-- Right Chat Box --}}
        <div class="col-md-8 d-flex flex-column h-100 bg-body-tertiary {{ !$selectedContact ? 'd-none d-md-flex' : '' }}">
            @if($selectedContact)
                {{-- Chat Box Header --}}
                <div class="p-3 bg-white border-bottom d-flex align-items-center gap-3">
                    <a href="{{ route('chat.index', ['view' => 'list']) }}" class="btn btn-light d-md-none rounded-circle d-flex align-items-center justify-content-center p-0" style="width: 40px; height: 40px; min-width: 40px;">
                        <i class="fas fa-arrow-left text-dark"></i>
                    </a>
                    <div class="position-relative">
                        <div class="rounded-circle bg-primary text-white d-flex align-items-center justify-content-center fw-bold small" style="width: 40px; height: 40px; min-width: 40px;">
                            {{ strtoupper(substr($selectedContact->full_name ?? $selectedContact->username, 0, 1)) }}
                        </div>
                        <span id="headerOnlineDot" class="position-absolute bottom-0 end-0 bg-success border border-white border-2 rounded-circle {{ $selectedContact->isOnline() ? '' : 'd-none' }}" style="width: 11px; height: 11px;"></span>
                    </div>
                    <div>
                        <h6 class="fw-bold mb-0 text-dark">{{ $selectedContact->full_name ?? $selectedContact->username }}</h6>
                        <small id="headerStatusText" class="text-muted small {{ $selectedContact->isOnline() ? 'text-success fw-bold' : '' }}">
                            {{ $selectedContact->isOnline() ? 'Online' : $selectedContact->last_active_label }}
                        </small>
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
                <div class="p-3 bg-white border-top position-relative">
                    <div id="uploadProgressContainer" class="progress position-absolute top-0 start-0 w-100 d-none" style="height: 4px; border-radius: 0;">
                        <div id="uploadProgressBar" class="progress-bar progress-bar-striped progress-bar-animated bg-primary" role="progressbar" style="width: 0%;"></div>
                    </div>

                    <form id="chatForm" class="d-flex gap-2 align-items-center no-loader">
                        @csrf
                        <input type="hidden" name="receiver_id" id="receiver_id" value="{{ $selectedContact->id }}">
                        
                        <input type="file" name="attachment" id="attachmentInput" class="d-none" accept="image/*,video/*">
                        <button type="button" class="btn btn-light rounded-circle border text-muted d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; min-width: 46px;" onclick="document.getElementById('attachmentInput').click()">
                            <i class="fas fa-paperclip"></i>
                        </button>

                        <input type="text" name="message" id="messageInput" class="form-control rounded-pill px-4 border" placeholder="Tulis pesan Anda disini..." autocomplete="off" style="height: 46px;">
                        
                        <button type="submit" id="sendBtn" class="btn btn-primary rounded-circle d-flex align-items-center justify-content-center" style="width: 46px; height: 46px; min-width: 46px;">
                            <i class="fas fa-paper-plane"></i>
                        </button>
                    </form>

                    <div id="filePreviewContainer" class="d-none mt-2 ps-5 ms-3">
                        <div class="d-inline-flex align-items-center bg-light border rounded-pill px-3 py-1">
                            <i class="fas fa-file-alt text-muted me-2"></i>
                            <span id="filePreviewName" class="small text-truncate" style="max-width: 150px;"></span>
                            <button type="button" class="btn-close ms-2" style="font-size: 0.6rem;" onclick="clearAttachment()"></button>
                        </div>
                    </div>
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
        const attachmentInput = document.getElementById('attachmentInput');
        const filePreviewContainer = document.getElementById('filePreviewContainer');
        const filePreviewName = document.getElementById('filePreviewName');
        const uploadProgressContainer = document.getElementById('uploadProgressContainer');
        const uploadProgressBar = document.getElementById('uploadProgressBar');
        const sendBtn = document.getElementById('sendBtn');
        const receiverId = "{{ $selectedContact->id }}";
        
        let lastMessageCount = 0;

        // Make clearAttachment globally available for inline onclick
        window.clearAttachment = function() {
            attachmentInput.value = '';
            filePreviewContainer.classList.add('d-none');
            filePreviewName.textContent = '';
        };

        attachmentInput.addEventListener('change', function() {
            if (this.files && this.files[0]) {
                const file = this.files[0];
                const maxSize = 100 * 1024 * 1024; // 100MB
                if (file.size > maxSize) {
                    Swal.fire({
                        icon: 'error',
                        title: 'File Terlalu Besar',
                        text: 'Ukuran maksimal file adalah 100MB.'
                    });
                    clearAttachment();
                    return;
                }
                filePreviewName.textContent = file.name;
                filePreviewContainer.classList.remove('d-none');
            }
        });

        const scrollToBottom = () => {
            chatHistory.scrollTop = chatHistory.scrollHeight;
        };

        const renderAttachment = (path, type) => {
            if (!path) return '';
            if (type === 'video') {
                return `<div class="mb-2"><video src="${path}" controls class="w-100 rounded-3" style="max-height: 250px; background: #000;"></video></div>`;
            } else {
                return `<div class="mb-2"><a href="${path}" target="_blank"><img src="${path}" class="w-100 rounded-3" style="max-height: 250px; object-fit: cover;"></a></div>`;
            }
        };

        // Render message bubbles
        const renderMessages = (messages) => {
            let html = '';
            messages.forEach(msg => {
                const attachmentHtml = renderAttachment(msg.attachment_path, msg.attachment_type);
                const messageHtml = msg.message ? `<p class="mb-1 text-break" style="font-size: 0.92rem;">${msg.message}</p>` : '';
                
                if (msg.is_mine) {
                    const tickColor = msg.is_read ? 'text-info' : 'text-white-50';
                    html += `
                        <div class="d-flex justify-content-end mb-3">
                            <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 70%; border-bottom-right-radius: 0 !important;">
                                ${attachmentHtml}
                                ${messageHtml}
                                <div class="text-end text-white-50 mt-1 d-flex justify-content-end align-items-center gap-1" style="font-size: 0.7rem;">
                                    <span>${msg.time}</span>
                                    <i class="fas fa-check-double ${tickColor}" style="font-size: 0.8rem;"></i>
                                </div>
                            </div>
                        </div>
                    `;
                } else {
                    html += `
                        <div class="d-flex justify-content-start mb-3">
                            <div class="bg-white text-dark p-3 rounded-4 shadow-sm border border-light-subtle" style="max-width: 70%; border-bottom-left-radius: 0 !important;">
                                ${attachmentHtml}
                                ${messageHtml}
                                <div class="text-muted mt-1" style="font-size: 0.7rem;">${msg.time}</div>
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
                        
                        // Update online status in header dynamically
                        const headerOnlineDot = document.getElementById('headerOnlineDot');
                        const headerStatusText = document.getElementById('headerStatusText');
                        if (headerOnlineDot && headerStatusText) {
                            if (data.contact_online) {
                                headerOnlineDot.classList.remove('d-none');
                                headerStatusText.textContent = 'Online';
                                headerStatusText.classList.add('text-success', 'fw-bold');
                                headerStatusText.classList.remove('text-muted');
                            } else {
                                headerOnlineDot.classList.add('d-none');
                                headerStatusText.textContent = data.contact_last_active;
                                headerStatusText.classList.remove('text-success', 'fw-bold');
                                headerStatusText.classList.add('text-muted');
                            }
                        }

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
            const file = attachmentInput.files[0];
            
            if (!messageText && !file) return;

            const formData = new FormData();
            formData.append('receiver_id', receiverId);
            if (messageText) formData.append('message', messageText);
            if (file) formData.append('attachment', file);
            formData.append('_token', "{{ csrf_token() }}");

            // Optimistic UI
            const timeNow = new Date().toLocaleTimeString('id-ID', { hour: '2-digit', minute: '2-digit' });
            let optimisticAttachment = '';
            if (file) {
                if (file.type.startsWith('video/')) {
                    optimisticAttachment = `<div class="mb-2"><div class="w-100 rounded-3 bg-dark d-flex align-items-center justify-content-center text-white-50" style="height: 150px;"><i class="fas fa-video fa-2x"></i></div></div>`;
                } else {
                    optimisticAttachment = `<div class="mb-2"><div class="w-100 rounded-3 bg-secondary d-flex align-items-center justify-content-center text-white-50" style="height: 150px;"><i class="fas fa-image fa-2x"></i></div></div>`;
                }
            }
            const optimisticMessage = messageText ? `<p class="mb-1 text-break" style="font-size: 0.92rem;">${escapeHtml(messageText)}</p>` : '';

            container.innerHTML += `
                <div class="d-flex justify-content-end mb-3">
                    <div class="bg-primary text-white p-3 rounded-4 shadow-sm" style="max-width: 70%; border-bottom-right-radius: 0 !important; opacity: 0.7;">
                        ${optimisticAttachment}
                        ${optimisticMessage}
                        <div class="text-end text-white-50 mt-1 d-flex justify-content-end align-items-center gap-1" style="font-size: 0.7rem;">
                            <span>${timeNow}</span>
                            <i class="fas fa-clock"></i>
                        </div>
                    </div>
                </div>
            `;
            scrollToBottom();
            
            // Clear inputs immediately
            messageInput.value = '';
            clearAttachment();
            sendBtn.disabled = true;

            const isLargeFile = file && file.size > (2 * 1024 * 1024); // > 2MB
            if (isLargeFile) {
                uploadProgressContainer.classList.remove('d-none');
                uploadProgressBar.style.width = '0%';
            }

            const xhr = new XMLHttpRequest();
            xhr.open('POST', '/chat/send', true);
            xhr.setRequestHeader('Accept', 'application/json');

            xhr.upload.onprogress = function(event) {
                if (event.lengthComputable && isLargeFile) {
                    const percentComplete = Math.round((event.loaded / event.total) * 100);
                    uploadProgressBar.style.width = percentComplete + '%';
                }
            };

            xhr.onload = function() {
                sendBtn.disabled = false;
                if (isLargeFile) {
                    uploadProgressContainer.classList.add('d-none');
                    uploadProgressBar.style.width = '0%';
                }
                
                if (xhr.status >= 200 && xhr.status < 300) {
                    const data = JSON.parse(xhr.responseText);
                    if (data.success) {
                        loadMessages();
                    }
                } else {
                    handleSendError();
                }
            };

            xhr.onerror = function() {
                sendBtn.disabled = false;
                if (isLargeFile) uploadProgressContainer.classList.add('d-none');
                handleSendError();
            };

            xhr.send(formData);
        });

        function handleSendError() {
            Swal.fire({
                icon: 'error',
                title: 'Pesan Gagal Terkirim',
                text: 'Silakan periksa koneksi internet Anda.',
                toast: true,
                position: 'top-end',
                showConfirmButton: false,
                timer: 3000
            });
            // Reload to clear optimistic UI
            loadMessages();
        }

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
