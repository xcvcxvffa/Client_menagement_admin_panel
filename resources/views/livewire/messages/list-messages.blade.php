<?php

use App\Models\Message;
use App\Models\User;
use App\Models\TeamMember;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Storage;
use Carbon\Carbon;
use Livewire\WithFileUploads;
use function Livewire\Volt\{state, mount, computed, uses};

uses([WithFileUploads::class]);

state([
    'searchQuery'        => '',
    'contacts'           => [],
    'selectedContactId'  => null,
    'selectedContactType'=> null,
    'messages'           => [],
    'newMessage'         => '',
    'attachments'        => [],
    'replyToMessageId'   => null,
    'replyToMessageData' => null,
    'isContactTyping'         => false,
    'isFirstLoad'             => true,
    'isForwardModalOpen'      => false,
    'forwardMessageId'        => null,
    'forwardSearchQuery'      => '',
    'forwardSelectedContacts' => [],
]);

// ── Silence toJSON errors from browser extensions/Alpine ──
$toJSON = function () {
    return [];
};

// ── Load only Team members ─────────────────────────────────────────
$loadContacts = function () {
    $user = Auth::user();
    $businessId = $user->current_business_id;

    // Mark current user as online for 15 seconds
    Cache::put('user-is-online-' . $user->id, true, now()->addSeconds(15));

    $this->contacts = User::whereIn(
        'id',
        TeamMember::where('business_id', $businessId)->pluck('user_id')
    )
    ->where('id', '!=', $user->id)
    ->get()
    ->map(fn($u) => $this->formatContact($u, User::class, 'Team', $user, $businessId))
    ->sortByDesc(fn($c) => $c['last_message_at'] ?? '1970-01-01')
    ->values()
    ->toArray();
};

$formatContact = function ($contactModel, $type, $typeLabel, $currentUser, $businessId) {
    $unread = Message::where('business_id', $businessId)
        ->where('sender_type', $type)->where('sender_id', $contactModel->id)
        ->where('receiver_type', User::class)->where('receiver_id', $currentUser->id)
        ->whereNull('read_at')->count();

    $lastMessage = Message::where('business_id', $businessId)
        ->where(fn($q) => $q
            ->where(fn($q2) => $q2->where('sender_type', $type)->where('sender_id', $contactModel->id)
                ->where('receiver_type', User::class)->where('receiver_id', $currentUser->id))
            ->orWhere(fn($q2) => $q2->where('sender_type', User::class)->where('sender_id', $currentUser->id)
                ->where('receiver_type', $type)->where('receiver_id', $contactModel->id))
        )
        ->orderByDesc('created_at')->first();

    return [
        'id'              => $contactModel->id,
        'name'            => $contactModel->name,
        'avatar_path'     => $contactModel->avatar_path ?? null,
        'type'            => $type,
        'type_label'      => $typeLabel,
        'is_online'       => Cache::has('user-is-online-' . $contactModel->id),
        'unread'          => $unread,
        'last_message_at' => $lastMessage?->created_at,
        'last_message'    => $lastMessage
            ? ($lastMessage->content ?: ($lastMessage->attachment_name ? '📎 ' . $lastMessage->attachment_name : null))
            : null,
    ];
};

$filteredContacts = computed(function () {
    return collect($this->contacts)
        ->filter(fn($c) => empty($this->searchQuery) || stripos($c['name'], $this->searchQuery) !== false)
        ->toArray();
});

mount(function () {
    $this->loadContacts();
});

$selectContact = function ($id, $type) {
    $this->selectedContactId   = $id;
    $this->selectedContactType = $type;
    $this->isFirstLoad         = true;
    $this->loadMessages();
    $this->markAsRead();
};

$loadMessages = function () {
    if (!$this->selectedContactId) return;
    $user = Auth::user();
    $businessId = $user->current_business_id;
    $old = count($this->messages);

    $this->messages = Message::with('replyTo')->where('business_id', $businessId)
        ->where(fn($q) => $q
            ->where(fn($q2) => $q2->where('sender_type', User::class)->where('sender_id', $user->id)
                ->where('receiver_type', $this->selectedContactType)->where('receiver_id', $this->selectedContactId)
                ->where('deleted_by_sender', false))
            ->orWhere(fn($q2) => $q2->where('sender_type', $this->selectedContactType)->where('sender_id', $this->selectedContactId)
                ->where('receiver_type', User::class)->where('receiver_id', $user->id)
                ->where('deleted_by_receiver', false))
        )
        ->orderBy('created_at')
        ->get();

    if (count($this->messages) > $old) {
        if (!$this->isFirstLoad) {
            $last = $this->messages->last();
            if ($last && ($last->sender_id != Auth::id() || $last->sender_type != User::class)) {
                $this->dispatch('new-message-received');
            }
        }
        $this->dispatch('message-added');
    }
    // Automatically mark messages as read since we are viewing this chat
    $this->markAsRead();
    
    $this->isFirstLoad = false;
    $this->checkTyping();
};

$groupedMessages = computed(function () {
    $groups = [];
    foreach ($this->messages as $msg) {
        $d = Carbon::parse($msg->created_at);
        $key = $d->isToday() ? 'Today' : ($d->isYesterday() ? 'Yesterday' : $d->format('F j, Y'));
        $groups[$key][] = $msg;
    }
    return $groups;
});

$markAsRead = function () {
    if (!$this->selectedContactId) return;
    $user = Auth::user();
    Message::where('business_id', $user->current_business_id)
        ->where('sender_type', $this->selectedContactType)
        ->where('sender_id', $this->selectedContactId)
        ->where('receiver_type', User::class)
        ->where('receiver_id', $user->id)
        ->whereNull('read_at')
        ->update(['read_at' => now()]);
    $this->loadContacts();
};

$markTyping = function () {
    if ($this->selectedContactId) {
        Cache::put('typing_' . Auth::id() . '_to_' . $this->selectedContactId, true, 8);
    }
};

$deleteMessage = function ($id) {
    $msg = Message::where('id', $id)->where('business_id', Auth::user()->current_business_id)->first();
    if ($msg) {
        if ($msg->sender_id == Auth::id() && $msg->sender_type == User::class) {
            $msg->update(['deleted_by_sender' => true]);
        } elseif ($msg->receiver_id == Auth::id() && $msg->receiver_type == User::class) {
            $msg->update(['deleted_by_receiver' => true]);
        }
        $this->loadMessages();
    }
};

$togglePin = function ($id) {
    $msg = Message::where('id', $id)->where('business_id', Auth::user()->current_business_id)->first();
    if ($msg) {
        $msg->update(['is_pinned' => !$msg->is_pinned]);
        $this->loadMessages();
        $this->dispatch('toast', message: $msg->is_pinned ? 'Message pinned' : 'Message unpinned', type: 'success');
    }
};

$deleteMessages = function ($ids) {
    if (empty($ids)) return;
    $msgs = Message::whereIn('id', $ids)
        ->where('business_id', Auth::user()->current_business_id)
        ->get();
    
    foreach ($msgs as $msg) {
        if ($msg->sender_id == Auth::id() && $msg->sender_type == User::class) {
            $msg->update(['deleted_by_sender' => true]);
        } elseif ($msg->receiver_id == Auth::id() && $msg->receiver_type == User::class) {
            $msg->update(['deleted_by_receiver' => true]);
        }
    }
    $this->loadMessages();
};

$checkTyping = function () {
    if ($this->selectedContactId) {
        $this->isContactTyping = Cache::has('typing_' . $this->selectedContactId . '_to_' . Auth::id());
    }
};

$setReply = function ($id) {
    $msg = Message::find($id);
    if ($msg) {
        $this->replyToMessageId = $id;
        $senderName = 'Team Member';
        if ($msg->sender_id == Auth::id()) {
            $senderName = 'You';
        } else {
            $contact = collect($this->contacts)->firstWhere('id', $msg->sender_id);
            if ($contact) $senderName = $contact['name'];
        }
        $this->replyToMessageData = [
            'sender' => $senderName,
            'content' => $msg->content,
            'has_attachment' => !empty($msg->attachment_path)
        ];
    }
};

$cancelReply = function () {
    $this->replyToMessageId = null;
    $this->replyToMessageData = null;
};

$sendMessage = function () {
    if (!$this->selectedContactId) return;
    if (empty(trim($this->newMessage)) && empty($this->attachments)) return;

    $user = Auth::user();
    $baseData = [
        'business_id'   => $user->current_business_id,
        'sender_type'   => User::class,
        'sender_id'     => $user->id,
        'receiver_type' => $this->selectedContactType,
        'receiver_id'   => $this->selectedContactId,
        'reply_to_id'   => $this->replyToMessageId,
    ];

    if (!empty($this->attachments)) {
        foreach ($this->attachments as $index => $attachment) {
            $data = $baseData;
            $data['attachment_path'] = $attachment->store('chat-attachments', 'public');
            $data['attachment_name'] = $attachment->getClientOriginalName();
            
            if ($index === 0 && !empty(trim($this->newMessage))) {
                $data['content'] = trim($this->newMessage);
            } else {
                $data['content'] = '';
            }
            Message::create($data);
        }
    } else {
        $data = $baseData;
        $data['content'] = trim($this->newMessage);
        Message::create($data);
    }

    $this->newMessage = '';
    $this->attachments = [];
    $this->cancelReply();
    $this->loadMessages();
    $this->loadContacts();
    Cache::forget('typing_' . Auth::id() . '_to_' . $this->selectedContactId);
};

$clearChat = function () {
    if (!$this->selectedContactId) return;
    $user = Auth::user();
    
    Message::where('business_id', $user->current_business_id)
        ->where('sender_type', User::class)->where('sender_id', $user->id)
        ->where('receiver_type', $this->selectedContactType)->where('receiver_id', $this->selectedContactId)
        ->update(['deleted_by_sender' => true]);
        
    Message::where('business_id', $user->current_business_id)
        ->where('sender_type', $this->selectedContactType)->where('sender_id', $this->selectedContactId)
        ->where('receiver_type', User::class)->where('receiver_id', $user->id)
        ->update(['deleted_by_receiver' => true]);

    $this->loadMessages();
    $this->loadContacts();
    $this->dispatch('toast', message: 'Chat cleared successfully', type: 'success');
};

$openForwardModal = function ($id) {
    $this->forwardMessageId = $id;
    $this->forwardSearchQuery = '';
    $this->forwardSelectedContacts = [];
    $this->isForwardModalOpen = true;
};

$closeForwardModal = function () {
    $this->isForwardModalOpen = false;
    $this->forwardMessageId = null;
    $this->forwardSearchQuery = '';
    $this->forwardSelectedContacts = [];
};

$toggleForwardContact = function ($id) {
    if (in_array($id, $this->forwardSelectedContacts)) {
        $this->forwardSelectedContacts = array_diff($this->forwardSelectedContacts, [$id]);
    } else {
        $this->forwardSelectedContacts[] = $id;
    }
};

$forwardMessage = function () {
    if (!$this->forwardMessageId || empty($this->forwardSelectedContacts)) return;
    
    $originalMsg = Message::where('business_id', Auth::user()->current_business_id)->find($this->forwardMessageId);
    if (!$originalMsg) return;
    
    $user = Auth::user();
    
    foreach ($this->forwardSelectedContacts as $contactId) {
        $data = [
            'business_id'     => $user->current_business_id,
            'sender_type'     => User::class,
            'sender_id'       => $user->id,
            'receiver_type'   => User::class,
            'receiver_id'     => $contactId,
            'reply_to_id'     => null,
            'content'         => $originalMsg->content,
        ];
        
        if ($originalMsg->attachment_path) {
            $data['attachment_path'] = $originalMsg->attachment_path;
            $data['attachment_name'] = $originalMsg->attachment_name;
        }
        
        Message::create($data);
    }
    
    $this->closeForwardModal();
    $this->loadMessages();
    $this->loadContacts();
    $this->dispatch('toast', message: 'Message forwarded successfully', type: 'success');
};

?>

<div class="h-full flex flex-col overflow-hidden" style="font-family:'Plus Jakarta Sans',sans-serif;">
    <div wire:loading.delay.long class="flex-1">
        <x-skeleton-loader type="messages" />
    </div>
    <div wire:loading.remove.delay.long class="h-full flex overflow-hidden" style="font-family:'Plus Jakarta Sans',sans-serif;"
         x-data="{}"
         @keydown.escape.window="$store.lb.close()">

<style>
    /* Scrollbar */
    .chat-scroll::-webkit-scrollbar { width: 4px; }
    .chat-scroll::-webkit-scrollbar-track { background: transparent; }
    .chat-scroll::-webkit-scrollbar-thumb { background: #fed7aa; border-radius: 99px; }
    .chat-scroll::-webkit-scrollbar-thumb:hover { background: #fb923c; }

    /* Bubbles */
    .bubble-mine {
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        color: #fff;
        border-radius: 18px 18px 4px 18px;
        display: inline-block;
        max-width: 100%;
        word-break: break-word;
        white-space: pre-wrap;
    }
    .bubble-theirs {
        background: #fff;
        color: #1f2937;
        border: 1px solid #f3f4f6;
        border-radius: 18px 18px 18px 4px;
        box-shadow: 0 1px 4px rgba(0,0,0,0.07);
        display: inline-block;
        max-width: 100%;
        word-break: break-word;
        white-space: pre-wrap;
    }

    /* Sidebar contact row */
    .contact-row { transition: background .12s ease; }
    .contact-row:hover:not(.contact-active) { background: #fff7ed; }
    .contact-active { background: #fff7ed; border: 1.5px solid #fb923c; }

    /* Composer */
    .composer-box {
        border: 1.5px solid #e5e7eb;
        border-radius: 999px;
        background: #fff;
        transition: border-color .18s, box-shadow .18s;
    }
    .composer-box:focus-within {
        border-color: #fb923c;
        box-shadow: 0 0 0 3px rgba(251,146,60,.15);
    }

    /* Send button – round circle with solid arrow */
    .btn-send {
        width: 46px;
        height: 46px;
        border-radius: 50%;
        background: linear-gradient(135deg, #f97316 0%, #ea580c 100%);
        color: #fff;
        border: none;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        box-shadow: 0 3px 12px rgba(234,88,12,.45);
        transition: opacity .15s, transform .12s;
        flex-shrink: 0;
        padding: 0;
    }
    .btn-send:hover { opacity: .88; transform: scale(1.07); }
    .btn-send:disabled { opacity: .4; cursor: not-allowed; transform: none; }
    .btn-send svg { display: block; margin: auto; }

    /* Attach dropdown */
    .attach-menu {
        position: absolute;
        bottom: 56px;
        left: 0;
        background: #fff;
        border-radius: 16px;
        box-shadow: 0 8px 32px rgba(0,0,0,0.14);
        border: 1px solid #f3f4f6;
        padding: 6px;
        min-width: 175px;
        z-index: 50;
    }
    .attach-menu-item {
        display: flex;
        align-items: center;
        gap: 12px;
        padding: 10px 14px;
        font-size: 13.5px;
        font-weight: 600;
        color: #374151;
        cursor: pointer;
        transition: background .1s;
        border: none;
        background: none;
        width: 100%;
        text-align: left;
        border-radius: 10px;
    }
    .attach-menu-item:hover { background: #fff7ed; color: #ea580c; }
    .attach-icon-wrap {
        width: 34px;
        height: 34px;
        border-radius: 9px;
        display: flex;
        align-items: center;
        justify-content: center;
        flex-shrink: 0;
    }

    /* Drag and drop zone */
    .drop-zone-active {
        outline: 3px dashed #f97316 !important;
        outline-offset: -4px;
        background: rgba(249,115,22,0.04) !important;
    }

    /* Typing dots */
    @keyframes dot-bounce {
        0%, 80%, 100% { transform: translateY(0); opacity:.5; }
        40% { transform: translateY(-6px); opacity:1; }
    }
    .typing-dot { animation: dot-bounce 1.2s ease-in-out infinite; }
    .typing-dot:nth-child(2) { animation-delay:.16s; }
    .typing-dot:nth-child(3) { animation-delay:.32s; }

    /* Attachment chip */
    .attach-chip {
        display: inline-flex;
        align-items: center;
        gap: 6px;
        background: #fff7ed;
        border: 1px solid #fed7aa;
        border-radius: 8px;
        padding: 4px 10px;
        font-size: 12px;
        color: #c2410c;
        font-weight: 600;
    }

    /* Image in chat */
    .chat-img {
        max-width: 220px;
        border-radius: 14px;
        cursor: pointer;
        transition: transform .15s, box-shadow .15s;
        box-shadow: 0 1px 6px rgba(0,0,0,0.12);
    }
    .chat-img:hover { transform: scale(1.02); box-shadow: 0 4px 16px rgba(0,0,0,0.18); }

    /* Lightbox */
    .lightbox-overlay {
        position: fixed;
        inset: 0;
        background: rgba(0,0,0,.88);
        z-index: 9999;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
    }
    .lightbox-img {
        max-width: 80vw;
        max-height: 78vh;
        border-radius: 12px;
        object-fit: contain;
        box-shadow: 0 8px 48px rgba(0,0,0,.6);
    }
    .lightbox-nav {
        position: absolute;
        top: 50%;
        transform: translateY(-50%);
        width: 44px;
        height: 44px;
        border-radius: 50%;
        background: rgba(255,255,255,.15);
        backdrop-filter: blur(4px);
        border: 1px solid rgba(255,255,255,.25);
        color: #fff;
        display: flex;
        align-items: center;
        justify-content: center;
        cursor: pointer;
        transition: background .15s;
    }
    .lightbox-nav:hover { background: rgba(255,255,255,.28); }
</style>

{{-- Alpine.store for lightbox – persists across Livewire wire:poll re-renders --}}
<script>
    document.addEventListener('alpine:init', () => {
        Alpine.store('lb', {
            open: false,
            src: '',
            senderName: '',
            senderAvatar: '',
            images: [],
            idx: 0,
            caption: '',
            show(src, images, idx, senderName, senderAvatar, caption) {
                this.src = src;
                this.images = Array.isArray(images) ? images : [src];
                this.idx = typeof idx === 'number' ? idx : 0;
                this.senderName = senderName || '';
                this.senderAvatar = senderAvatar || '';
                this.caption = caption || '';
                this.open = true;
            },
            prev() {
                if (!this.images.length) return;
                this.idx = (this.idx - 1 + this.images.length) % this.images.length;
                this.src = this.images[this.idx];
            },
            next() {
                if (!this.images.length) return;
                this.idx = (this.idx + 1) % this.images.length;
                this.src = this.images[this.idx];
            },
            close() { this.open = false; }
        });
    });
</script>

<audio id="chat-notif-snd" src="data:audio/wav;base64,UklGRqIBAABXQVZFZm10IBAAAAABAAEAQB8AAEAfAAABAAgAZGF0YYIBAACAgICAgICAgICAgICAgICAgICAgICAgICAgICAhIWMh4qNj4+Pj4+OjIuKh4aFg4KBgIGAgICAgICAgICAgICAgICAgICAgICAgICAgICAhIWMj5GSnJ2fn5+enZqYlZOOjIqIh4WEhIODg4ODg4ODg4KCgoKCgoKCgoKCgoKCgoqOk5mboKKlpqampqWkopyZlZCOi4mHhIODgoKCgoKCgoKCgoKCgoKCgoKCgoKCgoqOk5qdoKSnqqytrq6tq6inpZ2YlZCPjYqJh4aFhYWEhISDhYWFhYWFhYWFhISEhISGjZKZnaCjp6qusbKzsrGwq6ilo52Zl5OQj42KiYeGhoWFhIWFhYWFhYWFhYWFhYWGh4qOk5qcnaCjpaeoqaioqKWjnZmXk5CPjYqJh4aGhYWFhIWFhYWFhYWFhYWFhYWFhoeKjpOanJ2go6WnqKmoqKilox==" preload="auto"></audio>

{{-- ═════════ LIGHTBOX ═════════ --}}
<div class="lightbox-overlay" x-show="$store.lb.open" style="display:none;"
     @click.self="$store.lb.close()"
     x-transition:enter="transition ease-out duration-200"
     x-transition:enter-start="opacity-0"
     x-transition:enter-end="opacity-100"
     x-transition:leave="transition ease-in duration-150"
     x-transition:leave-start="opacity-100"
     x-transition:leave-end="opacity-0">

    {{-- Top bar --}}
    <div class="absolute top-0 left-0 right-0 h-14 flex items-center justify-between px-5 z-20" style="background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);">
        <div class="flex items-center gap-3">
            <img :src="$store.lb.senderAvatar" :alt="$store.lb.senderName"
                 class="w-9 h-9 rounded-full object-cover border-2 border-white/30"
                 x-show="$store.lb.senderAvatar !== ''"/>
            <div class="w-9 h-9 rounded-full bg-orange-500 flex items-center justify-center text-white text-[13px] font-bold border-2 border-white/30"
                 x-show="$store.lb.senderAvatar === ''"
                 x-text="$store.lb.senderName ? $store.lb.senderName.charAt(0).toUpperCase() : 'Y'"></div>
            <div>
                <p class="text-white text-[13.5px] font-semibold leading-none" x-text="$store.lb.senderName || 'You'"></p>
                <p class="text-white/55 text-[11px] mt-0.5" x-text="$store.lb.caption"></p>
            </div>
        </div>
        <div class="flex items-center gap-2">
            <a :href="$store.lb.src" download class="w-9 h-9 flex items-center justify-center rounded-full text-white hover:bg-white/20 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            </a>
            <button @click="$store.lb.close()" class="w-9 h-9 flex items-center justify-center rounded-full text-white hover:bg-white/20 transition">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M6 18L18 6M6 6l12 12"/></svg>
            </button>
        </div>
    </div>

    {{-- Main image --}}
    <div class="flex-1 flex items-center justify-center w-full" style="padding:70px 80px 110px;">
        <img :src="$store.lb.src" class="lightbox-img" @click.stop/>
    </div>

    {{-- Prev arrow --}}
    <button class="lightbox-nav" style="left:20px;top:50%;transform:translateY(-50%);" @click.stop="$store.lb.prev()" x-show="$store.lb.images.length > 1">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 19l-7-7 7-7"/></svg>
    </button>

    {{-- Next arrow --}}
    <button class="lightbox-nav" style="right:20px;top:50%;transform:translateY(-50%);" @click.stop="$store.lb.next()" x-show="$store.lb.images.length > 1">
        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
    </button>

    {{-- Thumbnail strip --}}
    <div class="absolute bottom-0 left-0 right-0 py-3 flex gap-2 justify-center overflow-x-auto px-8"
         style="background:rgba(0,0,0,0.55);backdrop-filter:blur(4px);"
         x-show="$store.lb.images.length > 0">
        <template x-for="(img, i) in $store.lb.images" :key="i">
            <img :src="img"
                 @click.stop="$store.lb.idx = i; $store.lb.src = img"
                 class="h-14 w-14 rounded-lg object-cover cursor-pointer transition-all flex-shrink-0"
                 :style="i === $store.lb.idx ? 'border:2px solid #f97316;opacity:1;' : 'border:2px solid rgba(255,255,255,0.2);opacity:0.55;'"
                 @mouseover="$event.target.style.opacity='0.9'"
                 @mouseout="i !== $store.lb.idx ? $event.target.style.opacity='0.55' : null"/>
        </template>
    </div>
</div>

{{-- ═════════ LEFT SIDEBAR ═════════ --}}
<div class="w-full md:w-[280px] flex-col shrink-0 border-r border-gray-100 bg-white {{ $selectedContactId ? 'hidden md:flex' : 'flex' }}" wire:poll.2s="loadContacts">

    <div class="px-5 pt-5 pb-3 flex items-center justify-between">
        <h2 class="text-[17px] font-bold text-gray-900 tracking-tight">Messages</h2>
    </div>

    <div class="px-4 pb-3">
        <label class="flex items-center gap-2 px-3 py-2 bg-gray-50 border border-gray-200 rounded-xl cursor-text focus-within:border-orange-300 focus-within:ring-2 focus-within:ring-orange-100 transition-all">
            <svg class="w-4 h-4 text-gray-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
            <input wire:model.live="searchQuery" type="text" placeholder="Search team..."
                   class="flex-1 bg-transparent border-0 focus:ring-0 text-[13px] text-gray-800 placeholder-gray-400 p-0"/>
        </label>
    </div>

    <div class="border-b border-gray-100 mx-4 mb-1"></div>

    <div class="flex-1 overflow-y-auto px-3 py-2 pb-4 chat-scroll">
        @forelse($this->filteredContacts as $contact)
            @php $sel = $selectedContactId == $contact['id'] && $selectedContactType == $contact['type']; @endphp

            <button wire:key="contact-{{ $contact['type'] }}-{{ $contact['id'] }}"
                    wire:click="selectContact('{{ $contact['id'] }}', '{{ str_replace('\\', '\\\\', $contact['type']) }}')"
                    class="contact-row w-full text-left px-3 py-3 rounded-xl flex items-center gap-3 mb-1 {{ $sel ? 'contact-active' : '' }}">

                <div class="relative shrink-0">
                    <img src="{{ !empty($contact['avatar_path']) ? Storage::url($contact['avatar_path']) : 'https://ui-avatars.com/api/?name='.urlencode($contact['name']).'&background=random&color=fff&size=80' }}"
                         class="w-11 h-11 rounded-full object-cover"/>
                    @if($contact['is_online'])
                        <span class="absolute bottom-0 right-0 w-3 h-3 bg-green-500 border-2 border-white rounded-full"></span>
                    @endif
                    @if($contact['unread'] > 0)
                        <span class="absolute -top-0.5 -right-0.5 w-[18px] h-[18px] bg-orange-500 text-white text-[9px] font-bold rounded-full flex items-center justify-center border-2 border-white">{{ $contact['unread'] }}</span>
                    @endif
                </div>

                <div class="flex-1 min-w-0">
                    <div class="flex items-center justify-between mb-0.5">
                        <span class="text-[13.5px] font-semibold truncate {{ $sel ? 'text-orange-600' : 'text-gray-900' }}">{{ $contact['name'] }}</span>
                        <span class="text-[11px] text-gray-400 ml-2 shrink-0">{{ $contact['last_message_at'] ? Carbon::parse($contact['last_message_at'])->shortAbsoluteDiffForHumans() : '' }}</span>
                    </div>
                    <p class="text-[12.5px] truncate {{ $contact['unread'] > 0 ? 'text-orange-500 font-medium' : 'text-gray-400' }}">
                        {{ $contact['last_message'] ?? 'Click to start chatting...' }}
                    </p>
                </div>
            </button>
        @empty
            <div class="flex flex-col items-center justify-center py-16 text-center">
                <div class="w-12 h-12 rounded-2xl bg-orange-50 flex items-center justify-center mb-3">
                    <svg class="w-6 h-6 text-orange-300" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/></svg>
                </div>
                <p class="text-[13px] text-gray-400 font-medium">No team members found</p>
            </div>
        @endforelse
    </div>
</div>

{{-- ═════════ MAIN CHAT ═════════ --}}
<div class="flex-1 flex-col h-full min-w-0 bg-[#FAFAF8] relative {{ !$selectedContactId ? 'hidden md:flex' : 'flex' }}" x-data="{}">

    @if($selectedContactId)
        @php
            $selectedContact = collect($this->contacts)->first(
                fn($c) => $c['id'] == $this->selectedContactId && $c['type'] == $this->selectedContactType
            );

            // Collect all image URLs in this conversation for lightbox navigation
            $allImages = $this->messages
                ->filter(fn($m) => !empty($m->attachment_path) && in_array(strtolower(pathinfo($m->attachment_name ?? '', PATHINFO_EXTENSION)), ['jpg','jpeg','png','gif','webp']))
                ->map(fn($m) => Storage::url($m->attachment_path))
                ->values()
                ->toArray();
        @endphp

        {{-- ── Header ── --}}
        <div class="h-[68px] px-4 md:px-6 flex items-center justify-between bg-white border-b border-gray-100 shrink-0" style="box-shadow:0 1px 4px rgba(0,0,0,0.06);">
            <div class="flex items-center gap-2 md:gap-3">
                <button wire:click="$set('selectedContactId', null)" class="md:hidden w-8 h-8 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-500 transition-colors">
                    <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 19l-7-7 7-7"/></svg>
                </button>
                <div class="relative">
                    <img src="{{ !empty($selectedContact['avatar_path']) ? Storage::url($selectedContact['avatar_path']) : 'https://ui-avatars.com/api/?name='.urlencode($selectedContact['name']).'&background=random&color=fff&size=80' }}"
                         class="w-10 h-10 rounded-full object-cover"/>
                    @if($selectedContact['is_online'])
                        <span class="absolute bottom-0 right-0 w-[11px] h-[11px] bg-green-500 border-2 border-white rounded-full"></span>
                    @endif
                </div>
                <div>
                    <h3 class="text-[15px] font-bold text-gray-900 leading-none mb-1.5">{{ $selectedContact['name'] }}</h3>
                    <div class="flex items-center gap-1.5">
                        <span class="text-[11.5px] font-semibold text-orange-500 leading-none">{{ $selectedContact['type_label'] }}</span>
                        <span class="text-gray-300 leading-none">•</span>
                        @if($selectedContact['is_online'])
                            <span class="text-[11.5px] font-medium text-green-500 leading-none">Online</span>
                        @else
                            <span class="text-[11.5px] font-medium text-gray-400 leading-none">Offline</span>
                        @endif
                    </div>
                </div>
            </div>

            <div x-data="{ open: false }" class="relative">
                <button @click="open=!open" @click.away="open=false"
                        class="w-9 h-9 flex items-center justify-center rounded-full hover:bg-orange-50 text-gray-400 hover:text-orange-500 transition-colors">
                    <svg class="w-5 h-5" fill="currentColor" viewBox="0 0 24 24">
                        <circle cx="12" cy="5" r="1.6"/><circle cx="12" cy="12" r="1.6"/><circle cx="12" cy="19" r="1.6"/>
                    </svg>
                </button>
                <div x-show="open" style="display:none"
                     class="absolute right-0 top-11 w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-50 text-[13px] font-medium text-gray-700"
                     x-transition:enter="transition ease-out duration-100"
                     x-transition:enter-start="opacity-0 scale-95"
                     x-transition:enter-end="opacity-100 scale-100">
                    <button wire:click="clearChat" @click="open=false"
                            class="w-full text-left px-4 py-2.5 hover:bg-orange-50 hover:text-orange-600 flex items-center gap-2 transition-colors">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                        Clear chat
                    </button>
                </div>
            </div>
        </div>

        {{-- ── Messages (drag & drop support) ── --}}
        <div class="flex-1 overflow-y-auto px-4 md:px-10 py-5 chat-scroll flex flex-col transition-all"
             wire:poll.2s="loadMessages"
             id="chat-messages"
             x-data="{
                autoScroll: true,
                init() {
                    this.$nextTick(() => this.scrollToBottom());
                    
                    this.$el.addEventListener('scroll', () => {
                        let distanceToBottom = this.$el.scrollHeight - this.$el.scrollTop - this.$el.clientHeight;
                        this.autoScroll = distanceToBottom < 100;
                    });
                    
                    let observer = new MutationObserver(() => {
                        if (this.autoScroll) {
                            this.scrollToBottom();
                        }
                    });
                    observer.observe(this.$el, { childList: true, subtree: true });
                },
                scrollToBottom() {
                    this.$el.scrollTop = this.$el.scrollHeight;
                },
                playNotif() {
                    let a = document.getElementById('chat-notif-snd');
                    if(a){ a.currentTime=0; a.play().catch(()=>{}); }
                },
                onDragOver(e) { e.preventDefault(); this.$el.classList.add('drop-zone-active'); },
                onDragLeave(e) { e.preventDefault(); this.$el.classList.remove('drop-zone-active'); },
                onDrop(e) {
                    e.preventDefault();
                    this.$el.classList.remove('drop-zone-active');
                    let dt = new DataTransfer();
                    for(let i=0; i<e.dataTransfer.files.length; i++){
                        dt.items.add(e.dataTransfer.files[i]);
                    }
                    if (dt.files.length > 0) {
                        let input = document.getElementById('dnd-file-input');
                        if (input) {
                            input.files = dt.files;
                            input.dispatchEvent(new Event('change'));
                        }
                    }
                },
                onPaste(e) {
                    let items = (e.clipboardData || e.originalEvent.clipboardData).items;
                    let dt = new DataTransfer();
                    let hasFile = false;
                    for (let index in items) {
                        let item = items[index];
                        if (item.kind === 'file') {
                            dt.items.add(item.getAsFile());
                            hasFile = true;
                        }
                    }
                    if (hasFile) {
                        let input = document.getElementById('dnd-file-input');
                        if (input) {
                            input.files = dt.files;
                            input.dispatchEvent(new Event('change'));
                        }
                    }
                },
                scrollToMessage(id) {
                    this.autoScroll = false;
                    let el = document.getElementById(id);
                    if (el) {
                        el.scrollIntoView({ behavior: 'smooth', block: 'center' });
                        el.classList.add('bg-orange-100', 'transition-colors', 'duration-500');
                        setTimeout(() => {
                            el.classList.remove('bg-orange-100');
                        }, 2000);
                    }
                }
             }"
             x-init="scrollToBottom()"
             @message-added.window="scrollToBottom()"
             @new-message-received.window="playNotif()"
             @dragover="onDragOver($event)"
             @dragleave="onDragLeave($event)"
             @drop="onDrop($event)"
             @paste.window="onPaste($event)">

            @forelse($this->groupedMessages as $date => $msgs)

                <div class="flex items-center gap-3 my-5">
                    <div class="flex-1 h-px bg-gray-200"></div>
                    <span class="text-[11px] text-gray-400 font-semibold whitespace-nowrap px-1 select-none">{{ $date }}</span>
                    <div class="flex-1 h-px bg-gray-200"></div>
                </div>

                @foreach($msgs as $message)
                    @php
                        $isMine = $message->sender_id == Auth::id() && $message->sender_type == App\Models\User::class;
                        $ext = strtolower(pathinfo($message->attachment_name ?? '', PATHINFO_EXTENSION));
                        $isImage = in_array($ext, ['jpg','jpeg','png','gif','webp']);
                        $isVideo = in_array($ext, ['mp4','webm','mov','avi']);
                        $imgUrl  = $isImage && $message->attachment_path ? Storage::url($message->attachment_path) : null;
                    @endphp

                    <div id="message-{{ $message->id }}" wire:key="msg-{{ $message->id }}" class="flex {{ $isMine ? 'justify-end' : 'justify-start' }} mb-2.5 items-center gap-2 group p-1 rounded-xl"
                         @dblclick="$wire.setReply({{ $message->id }})">

                        <div style="max-width:58%;" class="flex flex-col {{ $isMine ? 'items-end' : 'items-start' }}">
                            
                            @if(!$isMine)
                                <p class="text-[11px] text-gray-400 font-medium mb-1 px-1">{{ $selectedContact['name'] }}</p>
                            @endif

                            @if($message->is_pinned)
                                <div class="text-[11px] text-orange-500 font-semibold mb-1 flex items-center gap-1 px-1">
                                    <svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                    Pinned
                                </div>
                            @endif

                            <div class="relative group/bubble" x-data="{ menuOpen: false }" :class="menuOpen ? 'z-50' : 'z-0'">
                                {{-- Hover Menu Dropdown (WhatsApp style) --}}
                                <div class="absolute top-1 {{ $isMine ? '-left-8' : '-right-8' }} transition-opacity z-20"
                                     :class="(selectionMode ? 'hidden' : '') + ' ' + (menuOpen ? 'opacity-100' : 'opacity-0 group-hover/bubble:opacity-100')">
                                    <button @click="menuOpen = !menuOpen" @click.away="menuOpen = false" 
                                            class="w-7 h-7 bg-white rounded-full flex items-center justify-center text-gray-400 hover:text-gray-600 shadow-sm border border-gray-100 transition-colors">
                                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M19 9l-7 7-7-7"/></svg>
                                    </button>
                                    <div x-show="menuOpen" style="display:none;" class="absolute top-8 {{ $isMine ? 'right-0' : 'left-0' }} w-44 bg-white rounded-xl shadow-lg border border-gray-100 py-1.5 z-50 text-[13.5px] font-medium text-gray-700">
                                        <button @click="$wire.setReply({{ $message->id }}); menuOpen = false" class="group w-[calc(100%-12px)] mx-1.5 my-0.5 text-left px-3 py-2 rounded-lg hover:bg-orange-50 flex items-center gap-3 transition-colors hover:text-orange-800">
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h10a8 8 0 018 8v2M3 10l6 6m-6-6l6-6"/></svg>
                                            Reply
                                        </button>
                                        <button @click="navigator.clipboard.writeText(`{{ addslashes($message->content) }}`); menuOpen = false; $dispatch('toast', { message: 'Message copied', type: 'success' })" class="group w-[calc(100%-12px)] mx-1.5 my-0.5 text-left px-3 py-2 rounded-lg hover:bg-orange-50 flex items-center gap-3 transition-colors hover:text-orange-800">
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 16H6a2 2 0 01-2-2V6a2 2 0 012-2h8a2 2 0 012 2v2m-6 12h8a2 2 0 002-2v-8a2 2 0 00-2-2h-8a2 2 0 00-2 2v8a2 2 0 002 2z"/></svg>
                                            Copy
                                        </button>
                                        <button @click="$wire.openForwardModal({{ $message->id }}); menuOpen = false" class="group w-[calc(100%-12px)] mx-1.5 my-0.5 text-left px-3 py-2 rounded-lg hover:bg-orange-50 flex items-center gap-3 transition-colors hover:text-orange-800">
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-orange-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M14 5l7 7m0 0l-7 7m7-7H3"/></svg>
                                            Forward
                                        </button>
                                        <button wire:click="togglePin({{ $message->id }})" @click="menuOpen = false" class="group w-[calc(100%-12px)] mx-1.5 my-0.5 text-left px-3 py-2 rounded-lg hover:bg-orange-50 flex items-center gap-3 transition-colors hover:text-orange-800">
                                            <svg class="w-4 h-4 {{ $message->is_pinned ? 'text-orange-500' : 'text-gray-400 group-hover:text-orange-500' }}" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 5a2 2 0 012-2h10a2 2 0 012 2v16l-7-3.5L5 21V5z"/></svg>
                                            {{ $message->is_pinned ? 'Unpin' : 'Pin' }}
                                        </button>
                                        <button wire:click="deleteMessage({{ $message->id }})" class="group w-[calc(100%-12px)] mx-1.5 my-0.5 text-left px-3 py-2 rounded-lg hover:bg-red-50 flex items-center gap-3 transition-colors hover:text-red-600">
                                            <svg class="w-4 h-4 text-gray-400 group-hover:text-red-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                                            Delete
                                        </button>
                                    </div>
                                </div>

                                {{-- Text bubble --}}
                                @if($message->content || $message->replyTo)
                                <div class="px-5 py-2.5 text-[14.5px] leading-relaxed shadow-sm {{ $isMine ? 'bg-[#ef6c00] text-white rounded-3xl rounded-br-lg' : 'bg-white border border-gray-100 text-gray-800 rounded-3xl rounded-bl-lg' }}">
                                    
                                    {{-- Reply Block inside bubble --}}
                                    @if($message->replyTo)
                                        <div @click="scrollToMessage('message-{{ $message->replyTo->id }}')" 
                                             class="mb-2 p-2 rounded-md border-l-4 cursor-pointer transition-colors {{ $isMine ? 'bg-white/20 hover:bg-white/30 border-white/50' : 'bg-black/5 hover:bg-black/10 border-[#16a34a]' }}">
                                            
                                            <div class="text-[12.5px] font-bold mb-0.5 {{ $isMine ? 'text-white' : 'text-[#16a34a]' }}">
                                                {{ $message->replyTo->sender_id == Auth::id() ? 'You' : ($selectedContact['name'] ?? 'Team Member') }}
                                            </div>
                                            
                                            <div class="text-[12px] truncate {{ $isMine ? 'text-white/90' : 'text-gray-600' }}">
                                                @if($message->replyTo->attachment_path)
                                                    <span class="inline-flex items-center gap-1"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> Photo/Video</span>
                                                @else
                                                    {{ $message->replyTo->content }}
                                                @endif
                                            </div>
                                        </div>
                                    @endif

                                    {{-- Message Content with Read More logic --}}
                                    @if($message->content)
                                        @if(strlen($message->content) > 300)
                                            <div x-data="{ expanded: false }">
                                                <span x-show="!expanded">{{ substr($message->content, 0, 300) }}...</span>
                                                <span x-show="expanded" style="display:none;">{{ $message->content }}</span>
                                                <button @click="expanded = !expanded" class="text-blue-500 hover:underline text-[13px] font-medium block mt-1" x-text="expanded ? 'Read less' : 'Read more'"></button>
                                            </div>
                                        @else
                                            {{ $message->content }}
                                        @endif
                                    @endif
                                </div>
                            @endif

                            {{-- Image attachment --}}
                            @if($isImage && $imgUrl)
                                @php
                                    $imgIdx = array_search($imgUrl, $allImages);
                                    $senderName = $isMine ? Auth::user()->name : $selectedContact['name'];
                                    $senderAvatarPath = $isMine ? Auth::user()->avatar_path : ($selectedContact['avatar_path'] ?? null);
                                    $senderAvatar = $senderAvatarPath ? Storage::url($senderAvatarPath) : 'https://ui-avatars.com/api/?name=' . urlencode($senderName) . '&background=random&color=fff&size=80';
                                    $caption = Carbon::parse($message->created_at)->format('d/m/Y') . ' at ' . Carbon::parse($message->created_at)->format('g:i A');
                                    $imgIdxInt = $imgIdx !== false ? (int)$imgIdx : 0;
                                @endphp
                                <div class="mt-1">
                                    <img src="{{ $imgUrl }}"
                                         class="chat-img"
                                         @click.stop="$store.lb.show('{{ $imgUrl }}', {{ json_encode(array_values($allImages)) }}, {{ $imgIdxInt }}, '{{ addslashes($senderName) }}', '{{ $senderAvatar }}', '{{ $caption }}')"
                                         alt="{{ $message->attachment_name }}"/>
                                </div>

                            {{-- Video attachment --}}
                            @elseif($isVideo && $message->attachment_path)
                                <div class="mt-1">
                                    <video controls class="max-w-[220px] rounded-xl border border-gray-100 shadow-sm">
                                        <source src="{{ Storage::url($message->attachment_path) }}">
                                    </video>
                                </div>

                            {{-- Other file --}}
                            @elseif(!empty($message->attachment_path) && !$isImage && !$isVideo)
                                <div class="mt-1.5">
                                    <a href="{{ Storage::url($message->attachment_path) }}" target="_blank" download class="attach-chip hover:opacity-80 transition-opacity">
                                        <div class="w-8 h-8 rounded bg-gray-100 flex items-center justify-center shrink-0">
                                            <svg class="w-4 h-4 text-gray-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 10v6m0 0l-3-3m3 3l3-3m2 8H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z"/></svg>
                                        </div>
                                        <span class="text-[13px] font-medium text-gray-700 truncate w-[130px]">{{ $message->attachment_name }}</span>
                                    </a>
                                </div>
                            @endif

                            </div>

                            {{-- Message Time & Ticks --}}
                            <div class="flex items-center gap-1 mt-1 {{ $isMine ? 'justify-end' : 'justify-start' }} px-1">
                                <span class="text-[10px] text-gray-400 font-medium">{{ Carbon::parse($message->created_at)->format('g:i A') }}</span>
                                @if($isMine)
                                    @if($message->read_at)
                                        {{-- Double Blue Tick (Seen) --}}
                                        <svg class="w-[14px] h-[14px] text-blue-500" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M18 6 7 17l-5-5"/>
                                            <path d="m22 10-7.5 7.5L13 16"/>
                                        </svg>
                                    @else
                                        {{-- Single Gray Tick (Sent) --}}
                                        <svg class="w-[14px] h-[14px] text-gray-400" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round">
                                            <path d="M20 6 9 17l-5-5"/>
                                        </svg>
                                    @endif
                                @endif
                            </div>

                        </div>
                    </div>
                @endforeach

            @empty
                <div class="flex-1 flex flex-col items-center justify-center text-center">
                    <div class="w-14 h-14 rounded-2xl bg-orange-100 flex items-center justify-center mb-4 shadow-sm">
                        <svg class="w-7 h-7 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                        </svg>
                    </div>
                    <p class="text-[14px] font-bold text-gray-700">Start the conversation</p>
                    <p class="text-[12.5px] text-gray-400 mt-1">Say hello to {{ $selectedContact['name'] }} 👋</p>
                </div>
            @endforelse

            {{-- Typing indicator --}}
            @if($isContactTyping)
                <div class="flex justify-start mt-1 mb-3">
                    <div class="flex items-end gap-2">
                        <img src="https://ui-avatars.com/api/?name={{ urlencode($selectedContact['name']) }}&background=random&color=fff&size=60"
                             class="w-7 h-7 rounded-full object-cover shrink-0 mb-0.5"/>
                        <div class="bg-white border border-gray-100 rounded-2xl rounded-bl-sm px-4 py-3 flex items-center gap-1.5 shadow-sm">
                            <span class="typing-dot w-2 h-2 rounded-full bg-orange-400"></span>
                            <span class="typing-dot w-2 h-2 rounded-full bg-orange-500"></span>
                            <span class="typing-dot w-2 h-2 rounded-full bg-orange-600"></span>
                        </div>
                    </div>
                </div>
            @endif

            <div class="h-1 shrink-0"></div>
        </div>

        {{-- ── Reply Context Bar ── --}}
        @if($replyToMessageId)
            <div class="bg-gray-50 flex items-center justify-between shadow-sm relative z-10 px-6 py-2.5">
                <div class="flex-1 bg-white rounded-lg border-l-4 border-orange-500 pl-3 pr-3 py-2 flex items-center justify-between shadow-sm border border-gray-100">
                    <div class="flex-1 min-w-0">
                        <div class="text-[13px] font-bold text-orange-600 mb-0.5">{{ $replyToMessageData['sender'] }}</div>
                        <div class="text-[12px] text-gray-500 truncate pr-4">
                            @if($replyToMessageData['has_attachment'])
                                <span class="inline-flex items-center gap-1 text-gray-500"><svg class="w-3.5 h-3.5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg> Photo/Video</span>
                            @else
                                {{ $replyToMessageData['content'] }}
                            @endif
                        </div>
                    </div>
                    <button wire:click="cancelReply" class="w-6 h-6 flex items-center justify-center rounded-full hover:bg-gray-100 text-gray-400 transition-colors ml-2 shrink-0">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                </div>
            </div>
        @endif

        {{-- ── Composer ── --}}
        <div class="shrink-0 bg-white border-t border-gray-100 px-3 md:px-5 py-3">
            <form wire:submit="sendMessage">

                {{-- Upload Previews --}}
                @if(!empty($attachments))
                    <div class="px-8 mt-3 flex flex-wrap gap-2">
                        @foreach($attachments as $idx => $att)
                            <div class="relative bg-orange-50 border border-orange-200 rounded-lg p-2 pr-8 text-[12px] font-medium text-orange-700 max-w-[200px] truncate shadow-sm flex items-center gap-2">
                                <svg class="w-4 h-4 text-orange-400 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                                <span class="truncate">{{ $att->getClientOriginalName() }}</span>
                            </div>
                        @endforeach
                        <button wire:click="$set('attachments', [])" class="text-[12px] text-red-500 font-bold hover:underline px-2 py-1">Clear</button>
                    </div>
                @endif

                {{-- Input row --}}
                <div class="flex items-center gap-3">

                    {{-- Hidden input for drag & drop --}}
                    <input type="file" id="dnd-file-input" wire:model="attachment" class="hidden"
                           accept="image/*,video/*,.pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar">

                    {{-- Attach button with dropdown menu --}}
                    <div class="relative shrink-0" x-data="{ open: false }">
                        <button type="button" @click="open=!open" @click.away="open=false"
                                class="w-9 h-9 flex items-center justify-center rounded-full text-gray-400 hover:text-orange-500 hover:bg-orange-50 transition-colors">
                            <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15.172 7l-6.586 6.586a2 2 0 102.828 2.828l6.414-6.586a4 4 0 00-5.656-5.656l-6.415 6.585a6 6 0 108.486 8.486L20.5 13"/></svg>
                        </button>

                        {{-- Attach menu --}}
                        <div class="attach-menu" x-show="open" style="display:none"
                             x-transition:enter="transition ease-out duration-150"
                             x-transition:enter-start="opacity-0 translate-y-2"
                             x-transition:enter-end="opacity-100 translate-y-0"
                             x-transition:leave="transition ease-in duration-100"
                             x-transition:leave-start="opacity-100 translate-y-0"
                             x-transition:leave-end="opacity-0 translate-y-2">

                            {{-- Image --}}
                            <label class="attach-menu-item" @click="open=false">
                                <span class="attach-icon-wrap" style="background:#fdf2f8;">
                                    {{-- Gallery/Image icon --}}
                                    <svg class="w-[18px] h-[18px]" style="color:#db2777" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><rect x="3" y="3" width="18" height="18" rx="3"/><circle cx="8.5" cy="8.5" r="1.5"/><path d="M21 15l-5-5L5 21"/></svg>
                                </span>
                                <span>Image</span>
                                <input type="file" wire:model="attachments" class="hidden" accept="image/*" multiple>
                            </label>

                            {{-- Video --}}
                            <label class="attach-menu-item" @click="open=false">
                                <span class="attach-icon-wrap" style="background:#eff6ff;">
                                    {{-- Video camera icon --}}
                                    <svg class="w-[18px] h-[18px]" style="color:#2563eb" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M15 10l4.553-2.069A1 1 0 0121 8.82v6.36a1 1 0 01-1.447.893L15 14"/><rect x="2" y="7" width="13" height="10" rx="2"/></svg>
                                </span>
                                <span>Video</span>
                                <input type="file" wire:model="attachments" class="hidden" accept="video/*" multiple>
                            </label>

                            {{-- Document --}}
                            <label class="attach-menu-item" @click="open=false">
                                <span class="attach-icon-wrap" style="background:#f0fdf4;">
                                    {{-- Document icon --}}
                                    <svg class="w-[18px] h-[18px]" style="color:#16a34a" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M14 2H6a2 2 0 00-2 2v16a2 2 0 002 2h12a2 2 0 002-2V8z"/><polyline points="14 2 14 8 20 8"/><line x1="16" y1="13" x2="8" y2="13"/><line x1="16" y1="17" x2="8" y2="17"/><polyline points="10 9 9 9 8 9"/></svg>
                                </span>
                                <span>Document</span>
                                <input type="file" wire:model="attachments" class="hidden" accept=".pdf,.doc,.docx,.xls,.xlsx,.ppt,.pptx,.txt,.zip,.rar" multiple>
                            </label>
                        </div>
                    </div>

                    {{-- Input box (pill shape) --}}
                    <div class="composer-box flex-1 flex items-center px-5 py-2.5 gap-3">
                        <textarea wire:model="newMessage"
                                  wire:keydown.throttle.1000ms="markTyping"
                                  placeholder="Type a message…"
                                  rows="1"
                                  class="flex-1 resize-none bg-transparent border-0 focus:ring-0 focus:outline-none text-[14px] text-gray-800 placeholder-gray-400 leading-relaxed p-0 m-0 chat-scroll align-middle"
                                  style="max-height:110px; overflow-y:auto;"
                                  x-data="{ r() { $el.style.height='auto'; $el.style.height=Math.min($el.scrollHeight,110)+'px'; } }"
                                  x-init="r()"
                                  @input="r()"
                                  @keydown.enter.prevent="if(!$event.shiftKey){ $wire.sendMessage(); $nextTick(()=>r()); }">
                        </textarea>

                    </div>

                    {{-- Send – round circle with solid right-arrow (Image 4/5 style) --}}
                    <button type="submit" wire:loading.attr="disabled" class="btn-send">
                        {{-- Solid filled play/send arrow – centered --}}
                        <svg width="20" height="20" viewBox="0 0 24 24" fill="white">
                            <path d="M8 5.14v14l11-7-11-7z"/>
                        </svg>
                    </button>
                </div>

                {{-- Upload progress --}}
                <div wire:loading wire:target="attachments" class="mt-2 flex items-center gap-2 text-[12px] text-orange-500 font-medium">
                    <svg class="w-4 h-4 animate-spin" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path>
                    </svg>
                    Uploading…
                </div>
            </form>
        </div>

    @else
        <div class="flex-1 flex flex-col items-center justify-center text-center px-8">
            <div class="w-20 h-20 rounded-3xl bg-orange-100 flex items-center justify-center mb-5 shadow-sm">
                <svg class="w-9 h-9 text-orange-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.5" d="M8 12h.01M12 12h.01M16 12h.01M21 12c0 4.418-4.03 8-9 8a9.863 9.863 0 01-4.255-.949L3 20l1.395-3.72C3.512 15.042 3 13.574 3 12c0-4.418 4.03-8 9-8s9 3.582 9 8z"/>
                </svg>
            </div>
            <h3 class="text-[19px] font-bold text-gray-900 mb-2">Team Messages</h3>
            <p class="text-[13.5px] text-gray-400 max-w-[240px]">Select a team member from the left to start chatting.</p>
        </div>
    @endif

    {{-- Forward Message Modal --}}
    @if($isForwardModalOpen)
        <div class="fixed inset-0 z-[100] flex items-center justify-center bg-black/40 backdrop-blur-sm transition-opacity"
             style="font-family:'Plus Jakarta Sans',sans-serif;">
            <div class="bg-[#f0f2f5] sm:bg-white w-full max-w-[420px] h-[550px] sm:h-auto sm:max-h-[80vh] flex flex-col rounded-2xl shadow-xl overflow-hidden relative"
                 @click.away="$wire.closeForwardModal()">
                
                {{-- Header --}}
                <div class="flex items-center px-4 py-3 bg-white">
                    <button wire:click="closeForwardModal" class="p-2 text-gray-500 hover:text-gray-700 transition-colors rounded-full hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/></svg>
                    </button>
                    <h2 class="ml-2 text-[16px] font-semibold text-gray-800 flex-1">Forward message to</h2>
                    <button class="p-2 text-gray-500 hover:text-gray-700 transition-colors rounded-full hover:bg-gray-100">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18 9v3m0 0v3m0-3h3m-3 0h-3m-2-5a4 4 0 11-8 0 4 4 0 018 0zM3 20a6 6 0 0112 0v1H3v-1z"/></svg>
                    </button>
                </div>

                {{-- Search Bar --}}
                <div class="px-4 py-2 bg-white border-b border-gray-100">
                    <div class="relative flex items-center">
                        <svg class="absolute left-3 w-4 h-4 text-gray-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                        <input type="text" wire:model.live="forwardSearchQuery" placeholder="Search name, number or @username"
                               class="w-full pl-9 pr-4 py-2 bg-white border border-gray-300 rounded-full text-[14px] focus:outline-none focus:border-orange-500 focus:ring-1 focus:ring-orange-500 transition-all placeholder-gray-400">
                    </div>
                </div>

                {{-- Contacts List --}}
                <div class="flex-1 overflow-y-auto bg-white px-2 py-2 chat-scroll pb-20">
                    <p class="px-4 py-2 text-[13px] font-medium text-gray-500">Recent chats</p>
                    
                    @php
                        $filtered = collect($contacts)->filter(fn($c) => empty($forwardSearchQuery) || stripos($c['name'], $forwardSearchQuery) !== false);
                    @endphp

                    @forelse($filtered as $contact)
                        @php 
                            $isSelected = in_array($contact['id'], $forwardSelectedContacts); 
                        @endphp
                        <div wire:click="toggleForwardContact({{ $contact['id'] }})" class="flex items-center px-4 py-2.5 hover:bg-orange-50/50 cursor-pointer rounded-xl transition-colors {{ $isSelected ? 'bg-orange-50' : '' }}">
                            
                            {{-- Avatar --}}
                            <img src="https://ui-avatars.com/api/?name={{ urlencode($contact['name']) }}&background=random&color=fff&size=80"
                                 class="w-10 h-10 rounded-full object-cover mr-3 shrink-0"/>
                                 
                            {{-- Info --}}
                            <div class="flex-1 min-w-0">
                                <p class="text-[14.5px] font-semibold truncate {{ $isSelected ? 'text-orange-800' : 'text-gray-900' }}">{{ $contact['name'] }}</p>
                                <p class="text-[13px] truncate {{ $isSelected ? 'text-orange-600/80' : 'text-gray-500' }}">Team Member</p>
                            </div>

                            {{-- Checkmark (Image 1 Style) --}}
                            @if($isSelected)
                                <svg class="w-5 h-5 text-orange-500 shrink-0 ml-2" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>
                            @endif
                        </div>
                    @empty
                        <p class="text-center text-gray-400 text-[13px] py-4">No contacts found</p>
                    @endforelse
                </div>

                {{-- Floating Send Button --}}
                @if(count($forwardSelectedContacts) > 0)
                    <div class="absolute bottom-6 right-6 z-10">
                        <button wire:click="forwardMessage" wire:loading.attr="disabled" class="w-12 h-12 bg-orange-500 hover:bg-orange-600 text-white rounded-full flex items-center justify-center shadow-lg transition-transform hover:scale-105">
                            <svg class="w-5 h-5 ml-1" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M9 5l7 7-7 7"/></svg>
                        </button>
                    </div>
                @endif
            </div>
        </div>
    @endif
</div>
</div>
</div>
