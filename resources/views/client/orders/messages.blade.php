{{--
    Client Order Message Thread View (F-188)
    -----------------------------------------
    Displays the chat-like message thread for a specific order.
    Messages are shown chronologically, accessible from the order detail page.

    BR-239: Each order has exactly one message thread shared between client and cook/manager.
    BR-240: Messages displayed in chronological order (oldest first).
    BR-241: Initial load shows the most recent 20 messages, scrolled to the bottom.
    BR-242: Older messages load in batches of 20 when scrolling to the top.
    BR-243: Each message displays: sender name, sender role badge, timestamp, and message text.
    BR-244: Thread accessible only by the order's client, tenant's cook, and authorized managers.
    BR-245: Thread is read-only after order is Completed + 7 days or Cancelled.
    BR-246: All user-facing text uses __() localization.
    BR-247: Timestamps displayed in relative format (e.g. "2 hours ago") with full date on hover.
--}}
@extends(tenant() ? 'layouts.tenant-public' : 'layouts.main-public')

@section('title', __('Messages') . ' — #' . $order->order_number)

@section('content')
<div
    class="max-w-2xl mx-auto px-4 sm:px-6 lg:px-8 py-6 sm:py-8"
    x-data="{
        messages: {{ \Illuminate\Support\Js::from($messages) }},
        hasOlderMessages: {{ $hasOlderMessages ? 'true' : 'false' }},
        oldestMessageId: {{ $oldestMessageId ?? 'null' }},
        isLoadingOlder: false,
        olderMessages: [],

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

            $action('{{ url('/my-orders/' . $order->id . '/messages/load-older') }}', {
                include: ['oldestMessageId']
            });
        },

        applyOlderMessages() {
            if (this.olderMessages && this.olderMessages.length > 0) {
                const container = this.$refs.messageContainer;
                const previousScrollHeight = container ? container.scrollHeight : 0;

                this.messages = [...this.olderMessages, ...this.messages];
                this.olderMessages = null;
                this.isLoadingOlder = false;

                this.$nextTick(() => {
                    if (container) {
                        container.scrollTop = container.scrollHeight - previousScrollHeight;
                    }
                });
            }
        },

        formatTimestamp(isoString) {
            const date = new Date(isoString);
            return date.toLocaleString();
        }
    }"
    x-init="init(); $watch('olderMessages', val => { if (val && val.length > 0) applyOlderMessages(); })"
>

    {{-- Back Navigation --}}
    <nav class="flex items-center gap-2 text-sm text-on-surface/60 mb-6" aria-label="{{ __('Breadcrumb') }}">
        <a href="{{ url('/my-orders/' . $order->id) }}" class="hover:text-primary transition-colors duration-200 flex items-center gap-1" x-navigate>
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Order') }} #{{ $order->order_number }}
        </a>
        <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m9 18 6-6-6-6"></path></svg>
        <span class="text-on-surface-strong font-medium">{{ __('Messages') }}</span>
    </nav>

    {{-- Thread Header --}}
    <div class="flex items-center gap-3 mb-6">
        <div class="w-10 h-10 rounded-full bg-primary-subtle flex items-center justify-center shrink-0">
            {{-- MessageCircle icon (Lucide, md=20) --}}
            <svg class="w-5 h-5 text-primary" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
        </div>
        <div>
            <h1 class="text-lg font-display font-bold text-on-surface-strong">{{ __('Order Messages') }}</h1>
            <p class="text-xs text-on-surface/60">
                {{ __('Conversation with') }} <a href="{{ $tenantUrl }}" class="font-medium text-primary hover:underline" x-navigate-skip>{{ $cookName }}</a>
            </p>
        </div>

        @if($isReadOnly)
            <span class="ml-auto inline-flex items-center gap-1 px-2.5 py-1 rounded-full text-xs font-medium bg-surface-alt text-on-surface/60 border border-outline dark:border-outline">
                {{-- Lock icon (Lucide, xs=14) --}}
                <svg class="w-3.5 h-3.5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><rect width="18" height="11" x="3" y="11" rx="2" ry="2"></rect><path d="M7 11V7a5 5 0 0 1 10 0v4"></path></svg>
                {{ __('Read-only') }}
            </span>
        @endif
    </div>

    {{-- Message Thread Container --}}
    <div class="bg-surface dark:bg-surface rounded-2xl shadow-card border border-outline dark:border-outline overflow-hidden flex flex-col" style="min-height: 400px; max-height: calc(100vh - 280px);">

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
                {{-- Empty state (BR-241: no messages) --}}
                <template x-if="messages.length === 0">
                    <div class="flex flex-col items-center justify-center py-16 text-center">
                        {{-- MessageCircle icon (Lucide, xl=32) --}}
                        <svg class="w-8 h-8 text-on-surface/20 mb-3" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.5" stroke-linecap="round" stroke-linejoin="round"><path d="M7.9 20A9 9 0 1 0 4 16.1L2 22Z"></path></svg>
                        <p class="text-sm text-on-surface/50 font-medium">{{ __('No messages yet.') }}</p>
                        <p class="text-xs text-on-surface/40 mt-1">{{ __('Start a conversation about this order.') }}</p>
                    </div>
                </template>

                {{-- Message bubbles (BR-240: chronological order) --}}
                <template x-for="message in messages" :key="message.id">
                    <div
                        class="flex flex-col gap-1"
                        :class="message.is_mine ? 'items-end' : 'items-start'"
                    >
                        {{-- Sender info above bubble (BR-243: name + role) --}}
                        <div class="flex items-center gap-1.5 px-1" :class="message.is_mine ? 'flex-row-reverse' : 'flex-row'">
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
                            :class="message.is_mine
                                ? 'bg-primary text-on-primary rounded-br-sm'
                                : 'bg-surface-alt dark:bg-surface-alt text-on-surface rounded-bl-sm border border-outline dark:border-outline'"
                        >
                            <p x-text="message.body" class="whitespace-pre-wrap"></p>
                        </div>

                        {{-- Timestamp (BR-247: relative format with full date on hover) --}}
                        <div class="px-1" :class="message.is_mine ? 'text-right' : 'text-left'">
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
                {{-- Message input (F-189 placeholder — read notice only, input added by F-189) --}}
                <div class="px-4 py-3 border-t border-outline dark:border-outline bg-surface-alt dark:bg-surface-alt">
                    <div class="flex items-center gap-2 p-3 rounded-xl bg-surface dark:bg-surface border border-outline dark:border-outline text-on-surface/40 text-sm cursor-default">
                        {{-- PenLine icon (Lucide, sm=16) --}}
                        <svg class="w-4 h-4 shrink-0" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M12 20h9"></path><path d="M16.376 3.622a1 1 0 0 1 3.002 3.002L7.368 18.635a2 2 0 0 1-.855.506l-2.872.838a.5.5 0 0 1-.62-.62l.838-2.872a2 2 0 0 1 .506-.854z"></path></svg>
                        <span>{{ __('Type a message...') }}</span>
                    </div>
                    <p class="text-[10px] text-on-surface/30 text-center mt-2">{{ __('Message sending will be available soon.') }}</p>
                </div>
            @endif

        </div>
        @endfragment

    </div>

    {{-- Back link at bottom --}}
    <div class="mt-4 text-center">
        <a href="{{ url('/my-orders/' . $order->id) }}" class="inline-flex items-center gap-1.5 text-sm text-on-surface/60 hover:text-primary transition-colors" x-navigate>
            {{-- ArrowLeft icon (Lucide, sm=16) --}}
            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="m12 19-7-7 7-7"></path><path d="M19 12H5"></path></svg>
            {{ __('Back to Order') }}
        </a>
    </div>

</div>
@endsection
