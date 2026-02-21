{{--
    F-187: Complaint Status Timeline Partial
    -----------------------------------------
    Reusable timeline showing complaint progression through states.

    @param array $timeline - Array of timeline steps from ComplaintTrackingService
    UI/UX: Horizontal stepper on desktop, vertical on mobile.
    BR-232: All four states shown with current highlighted.
    BR-236: Timestamps for each reached state.
--}}
@php
    $icons = [
        'open' => '<circle cx="12" cy="12" r="10"></circle><path d="M12 16v-4"></path><path d="M12 8h.01"></path>',
        'in_review' => '<path d="M2 12s3-7 10-7 10 7 10 7-3 7-10 7-10-7-10-7Z"></path><circle cx="12" cy="12" r="3"></circle>',
        'escalated' => '<path d="m21.73 18-8-14a2 2 0 0 0-3.48 0l-8 14A2 2 0 0 0 4 21h16a2 2 0 0 0 1.73-3Z"></path><line x1="12" y1="9" x2="12" y2="13"></line><line x1="12" y1="17" x2="12.01" y2="17"></line>',
        'resolved' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"></path><path d="m9 11 3 3L22 4"></path>',
    ];

    $statusColors = [
        'completed' => 'bg-success text-on-success',
        'active' => 'bg-primary text-on-primary',
        'skipped' => 'bg-surface-alt text-on-surface/30',
        'upcoming' => 'bg-surface-alt text-on-surface/30',
    ];

    $lineColors = [
        'completed' => 'bg-success',
        'active' => 'bg-primary',
        'skipped' => 'bg-outline',
        'upcoming' => 'bg-outline',
    ];

    $labelColors = [
        'completed' => 'text-success',
        'active' => 'text-primary font-semibold',
        'skipped' => 'text-on-surface/30 line-through',
        'upcoming' => 'text-on-surface/40',
    ];
@endphp

{{-- Desktop: Horizontal stepper --}}
<div class="hidden sm:block">
    <div class="flex items-start justify-between relative">
        @foreach($timeline as $index => $step)
            <div class="flex flex-col items-center relative z-10" style="flex: 1;">
                {{-- Step circle with icon --}}
                <div class="w-10 h-10 rounded-full flex items-center justify-center {{ $statusColors[$step['status']] }} transition-all duration-300 @if($step['status'] === 'active') ring-4 ring-primary/20 @endif">
                    @if($step['status'] === 'completed')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                    @elseif($step['status'] === 'skipped')
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></svg>
                    @else
                        <svg class="w-5 h-5" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$step['state']] !!}</svg>
                    @endif
                </div>

                {{-- Label --}}
                <p class="text-xs mt-2 text-center {{ $labelColors[$step['status']] }}">
                    {{ $step['label'] }}
                </p>

                {{-- Timestamp --}}
                @if($step['timestamp'])
                    <p class="text-[10px] text-on-surface/40 mt-0.5 text-center">{{ $step['timestamp'] }}</p>
                @elseif($step['status'] === 'skipped')
                    <p class="text-[10px] text-on-surface/20 mt-0.5 text-center">{{ __('Skipped') }}</p>
                @endif
            </div>

            {{-- Connecting line between steps --}}
            @if($index < count($timeline) - 1)
                @php
                    $nextStep = $timeline[$index + 1];
                    $lineColor = in_array($step['status'], ['completed', 'active']) && $nextStep['status'] !== 'upcoming'
                        ? 'bg-success'
                        : 'bg-outline';
                    if ($step['status'] === 'active') {
                        $lineColor = 'bg-outline';
                    }
                    if ($step['status'] === 'completed' && $nextStep['status'] === 'active') {
                        $lineColor = 'bg-primary/50';
                    }
                    if ($step['status'] === 'completed' && in_array($nextStep['status'], ['completed', 'skipped'])) {
                        $lineColor = 'bg-success';
                    }
                @endphp
                <div class="absolute top-5 {{ $lineColor }} h-0.5" style="left: calc({{ ($index + 0.5) / (count($timeline) - 1) * 100 }}% + 20px); right: calc({{ (1 - ($index + 1.5) / (count($timeline) - 1)) * 100 }}% + 20px); transform: translateY(-50%);"></div>
            @endif
        @endforeach
    </div>
</div>

{{-- Mobile: Vertical stepper --}}
<div class="sm:hidden">
    <div class="space-y-0">
        @foreach($timeline as $index => $step)
            <div class="flex items-start gap-3">
                {{-- Vertical line + circle --}}
                <div class="flex flex-col items-center">
                    {{-- Step circle --}}
                    <div class="w-8 h-8 rounded-full flex items-center justify-center shrink-0 {{ $statusColors[$step['status']] }} @if($step['status'] === 'active') ring-4 ring-primary/20 @endif">
                        @if($step['status'] === 'completed')
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>
                        @elseif($step['status'] === 'skipped')
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"><path d="M5 12h14"></path></svg>
                        @else
                            <svg class="w-4 h-4" xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">{!! $icons[$step['state']] !!}</svg>
                        @endif
                    </div>

                    {{-- Connecting line --}}
                    @if($index < count($timeline) - 1)
                        @php
                            $nextStep = $timeline[$index + 1];
                            $vLineColor = in_array($step['status'], ['completed']) ? 'bg-success' : 'bg-outline';
                            if ($step['status'] === 'active') { $vLineColor = 'bg-outline'; }
                        @endphp
                        <div class="w-0.5 h-8 {{ $vLineColor }}"></div>
                    @endif
                </div>

                {{-- Content --}}
                <div class="pb-4 @if($index === count($timeline) - 1) pb-0 @endif pt-1">
                    <p class="text-sm {{ $labelColors[$step['status']] }}">
                        {{ $step['label'] }}
                    </p>
                    @if($step['timestamp'])
                        <p class="text-xs text-on-surface/40 mt-0.5">{{ $step['timestamp'] }}</p>
                    @elseif($step['status'] === 'skipped')
                        <p class="text-xs text-on-surface/20 mt-0.5">{{ __('Skipped') }}</p>
                    @endif
                </div>
            </div>
        @endforeach
    </div>
</div>
