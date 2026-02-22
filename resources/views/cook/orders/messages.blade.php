{{--
    Cook/Manager Order Message Thread View (F-188)
    ------------------------------------------------
    Displays the chat-like message thread for a specific order from the cook dashboard.
    Cook and manager messages appear on the right, client messages on the left.

    BR-239: Each order has exactly one message thread shared between client and cook/manager.
    BR-240: Messages displayed in chronological order (oldest first).
    BR-241: Initial load shows the most recent 20 messages, scrolled to the bottom.
    BR-242: Older messages load in batches of 20 when scrolling to the top.
    BR-243: Each message displays: sender name, sender role badge, timestamp, and message text.
    BR-244: Thread accessible only by order's client, tenant's cook, and authorized managers.
    BR-245: Thread is read-only after order is Completed + 7 days or Cancelled.
    BR-246: All user-facing text uses __() localization.
    BR-247: Timestamps displayed in relative format with full date on hover.
    Scenario 5: Manager sees all messages, sends as cook's business.
--}}
@extends('layouts.cook-dashboard')

@section('title', __('Messages') . ' — #' . $order->order_number)
@section('page-title', __('Order Messages'))

@section('content')
<div
    class="max-w-2xl mx-auto"
    x-data="{
        thread: {{ \Illuminate\Support\Js::from($messages) }},
        hasOlderMessages: {{ $hasOlderMessages ? 'true' : 'false' }},
        oldestMessageId: {{ $oldestMessageId ?? 'null' }},
        isLoadingOlder: false,
        olderMessages: [],
        body: '',
        newMessage: null,

        get charCount() { return this.body.length; },
        get isOverLimit() { return this.body.length > 500; },
        get canSend() { return this.body.trim().length > 0 && !this.isOverLimit; },

        init() {
            this.$nextTick(() => this.scrollToBottom());
        },

        scrollToBottom() {
            const container = this.$refs.messageContainer;
            if (container) {
                container.scrollTop = container.scrollHeight;
            }
        },

        loadOlder() {
            if (!this.hasOlderMessages || this.isLoadingOlder || !this.oldestMessageId) return;
            this.isLoadingOlder = true;

            const container = this.$refs.messageContainer;
            const previousScrollHeight = container ? container.scrollHeight : 0;

            $action('{{ url('/dashboard/orders/' . $order->id . '/messages/load-older') }}', {
                include: ['oldestMessageId']
            });
        },

        applyOlderMessages() {
            if (this.olderMessages && this.olderMessages.length > 0) {
                const container = this.$refs.messageContainer;
                const previousScrollHeight = container ? container.scrollHeight : 0;

                const current = Array.isArray(this.thread) ? this.thread : Object.values(this.thread || {});
                this.thread = [...this.olderMessages, ...current];
                this.olderMessages = null;
                this.isLoadingOlder = false;

                this.$nextTick(() => {
                    if (container) {
                        container.scrollTop = container.scrollHeight - previousScrollHeight;
                    }
                });
            }
        },

        handleNewMessage() {
            if (this.newMessage) {
                const current = Array.isArray(this.thread) ? this.thread : Object.values(this.thread || {});
                this.thread = [...current, this.newMessage];
                this.newMessage = null;
                this.$nextTick(() => this.scrollToBottom());
            }
        },

        handleSendKeydown(event) {
            if (event.key === 'Enter' && !event.shiftKey) {
                event.preventDefault();
                if (this.canSend) {
                    $action('{{ url('/dashboard/orders/' . $order->id . '/messages') }}', {
                        include: ['body']
                    });
                }
            }
        }
    }"
    x-init="init(); $watch('olderMessages', val => { if (val && val.length > 0) applyOlderMessages(); }); $watch('newMessage', val => { if (val) handleNewMessage(); })"
    x-sync="['body']"
>

    {{-- Breadcrumb + Header --}}
    <div class="mb-6">
        <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-3" aria-label="{{ __('Breadcrumb') }}">
            <a href="{{ url('/dashboard/orders') }}" class="hover:text-primary transition-colors" x-navigate>
                {{ __('Orders') }}
            </a>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            <a href="{{ url('/dashboard/orders/' . $order->id) }}" class="hover:text-primary transition-colors" x-navigate>
                #{{ $order->order_number }}
            </a>
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
            <span class="text-on-surface-strong font-medium">{{ __('Messages') }}</span>
        </nav>

        <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
                {{-- MessageCircle icon (Lucide, md=20) --}}
                <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
            </div>
            <div>
                <h2 class="text-base font-display font-bold text-on-surface-strong">
                    {{ __('Conversation with') }} {{ $clientName }}
                </h2>
                <p class="text-xs text-on-surface/60">{{ __('Order') }} #{{ $order->order_number }}</p>
            </div>

            @if($isReadOnly)
                <span class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-surface-alt text-on-surface/60 border border-outline dark:border-outline">
                    {{-- Lock icon (Lucide, xs=14) --}}
                    <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                    {{ __('Read-only') }}
                </span>
            @endif
        </div>
    </div>

    {{-- Message Thread Container --}}
    <div class="bg-surface dark:bg-surface rounded-2xl shadow-card border border-outline dark:border-outline overflow-hidden flex flex-col" style="min-height: 400px; max-height: calc(100vh - 300px);">

        @fragment('messages-thread')
        <div id="messages-thread" class="flex flex-col flex-1 overflow-hidden">

            {{-- Load Older Messages Button --}}
            <div
                class="px-4 py-2 border-b border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt flex items-center justify-center"
                x-show="hasOlderMessages"
                x-cloak
            >
                <button
                    type="button"
                    class="inline-flex items-center gap-1.5 px-3 py-1.5 rounded-lg text-xs font-medium text-primary hover:bg-primary-subtle transition-colors disabled:opacity-50"
                    x-on:click="loadOlder()"
                    :disabled="isLoadingOlder"
                >
                    <span x-show="!isLoadingOlder">
                        {{-- ChevronUp icon (Lucide, xs=14) --}}
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"></path></svg>
                    </span>
                    <span x-show="isLoadingOlder" class="animate-spin-slow">
                        <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                    </span>
                    <span x-show="!isLoadingOlder">{{ __('Load older messages') }}</span>
                    <span x-show="isLoadingOlder">{{ __('Loading...') }}</span>
                </button>
            </div>

            {{-- Messages scrollable area --}}
            <div
                class="flex-1 overflow-y-auto p-4 space-y-4"
                x-ref="messageContainer"
            >
                {{-- Empty state --}}
                <template x-if="thread.length === 0">
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        {{-- MessageCircle icon (Lucide, lg=24) --}}
                        <svg class="w-8 h-8 text-on-surface/20 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                        <p class="text-sm text-on-surface/50 font-medium">{{ __('No messages yet.') }}</p>
                        <p class="text-xs text-on-surface/40 mt-1">{{ __('Start a conversation about this order.') }}</p>
                    </div>
                </template>

                {{-- Message bubbles (BR-240: chronological order) --}}
                {{-- Cook/manager view: own messages (cook/manager) on right, client on left --}}
                <template x-for="message in thread" :key="message.id">
                    <div
                        class="flex flex-col gap-1"
                        :class="message.sender_role === 'client' ? 'items-start' : 'items-end'"
                    >
                        {{-- Sender info (BR-243: name + role) --}}
                        <div
                            class="flex items-center gap-1.5 px-1"
                            :class="message.sender_role === 'client' ? 'flex-row' : 'flex-row-reverse'"
                        >
                            <span class="text-xs font-medium text-on-surface-strong" x-text="message.sender_name"></span>
                            <span
                                class="inline-flex items-center px-1.5 py-0.5 rounded text-[10px] font-medium leading-none"
                                :class="{
                                    'bg-primary-subtle text-primary': message.sender_role === 'client',
                                    'bg-secondary-subtle text-secondary': message.sender_role === 'cook',
                                    'bg-info-subtle text-info': message.sender_role === 'manager'
                                }"
                                x-text="message.role_label"
                            ></span>
                        </div>

                        {{-- Message bubble --}}
                        <div
                            class="relative max-w-[80%] rounded-2xl px-4 py-2.5 text-sm leading-relaxed break-words"
                            :class="message.sender_role === 'client'
                                ? 'bg-surface-alt dark:bg-surface-alt text-on-surface rounded-bl-sm border border-outline dark:border-outline'
                                : 'bg-primary text-on-primary rounded-br-sm'"
                        >
                            <p x-text="message.body" class="whitespace-pre-wrap"></p>
                        </div>

                        {{-- Timestamp (BR-247) --}}
                        <div class="px-1" :class="message.sender_role === 'client' ? 'text-left' : 'text-right'">
                            <time
                                :datetime="message.created_at_iso"
                                :title="message.created_at_full"
                                class="text-[10px] text-on-surface/40 cursor-default"
                                x-text="message.created_at_formatted"
                            ></time>
                        </div>
                    </div>
                </template>
            </div>

            {{-- Read-only notice (BR-245) --}}
            @if($isReadOnly)
                <div class="px-4 py-3 border-t border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <p class="text-xs text-on-surface/50 text-center flex items-center justify-center gap-1.5">
                        {{-- Lock icon (Lucide, xs=14) --}}
                        <svg class="w-3.5 h-3.5 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                        @if($order->status === \App\Models\Order::STATUS_CANCELLED)
                            {{ __('This order was cancelled. The message thread is read-only.') }}
                        @else
                            {{ __('This order is completed. The message thread is read-only after 7 days.') }}
                        @endif
                    </p>
                </div>
            @else
                {{-- Message Input (F-189: Message Send) --}}
                <div class="px-4 py-3 border-t border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    {{-- Validation error --}}
                    <p x-message="body" class="text-xs text-danger mb-2 px-1 empty:hidden"></p>

                    <div class="flex items-end gap-2">
                        {{-- Text input area --}}
                        <div class="flex-1 relative">
                            <textarea
                                x-model="body"
                                x-on:keydown="handleSendKeydown($event)"
                                x-name="body"
                                rows="1"
                                maxlength="510"
                                placeholder="{{ __('Type a message...') }}"
                                class="w-full resize-none rounded-xl px-4 py-2.5 text-sm bg-surface dark:bg-surface border transition-colors duration-200 outline-none focus:ring-2 focus:ring-primary/20 text-on-surface placeholder:text-on-surface/40 leading-relaxed"
                                :class="isOverLimit
                                    ? 'border-danger focus:border-danger'
                                    : 'border-outline dark:border-outline focus:border-primary'"
                                style="min-height: 42px; max-height: 120px; overflow-y: auto; field-sizing: content;"
                            ></textarea>
                        </div>

                        {{-- Send button --}}
                        <button
                            type="button"
                            :disabled="!canSend || $fetching()"
                            x-on:click="canSend && $action('{{ url('/dashboard/orders/' . $order->id . '/messages') }}', { include: ['body'] })"
                            class="flex-shrink-0 w-10 h-10 rounded-xl flex items-center justify-center transition-all duration-200 focus:outline-none focus:ring-2 focus:ring-primary/30"
                            :class="canSend && !$fetching()
                                ? 'bg-primary text-on-primary hover:bg-primary-hover shadow-sm'
                                : 'bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface/30 cursor-not-allowed'"
                            :aria-label="@js(__('Send message'))"
                        >
                            {{-- Loading spinner --}}
                            <span x-show="$fetching()" class="animate-spin-slow">
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M21 12a9 9 0 1 1-6.219-8.56"></path></svg>
                            </span>
                            {{-- Send arrow icon --}}
                            <span x-show="!$fetching()">
                                {{-- SendHorizontal icon (Lucide, sm=16) --}}
                                <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m3 3 3 9-3 9 19-9Z"></path><path d="M6 12h16"></path></svg>
                            </span>
                        </button>
                    </div>

                    {{-- Character counter --}}
                    <div class="flex items-center justify-between mt-1.5 px-1">
                        <p class="text-[10px] text-on-surface/40">{{ __('Enter to send · Shift+Enter for new line') }}</p>
                        <span
                            class="text-[10px] tabular-nums transition-colors duration-150"
                            :class="isOverLimit ? 'text-danger font-medium' : (charCount > 450 ? 'text-warning' : 'text-on-surface/40')"
                            x-text="charCount + '/500'"
                        ></span>
                    </div>
                </div>
            @endif

        </div>
        @endfragment

    </div>

    {{-- Action link back --}}
    <div class="mt-4">
        <a href="{{ url('/dashboard/orders/' . $order->id) }}" class="inline-flex items-center gap-1.5 text-sm text-on-surface/60 hover:text-primary transition-colors" x-navigate>
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Order') }}
        </a>
    </div>

</div>
@endsection
