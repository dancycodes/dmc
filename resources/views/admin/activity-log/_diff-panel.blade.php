{{--
    Activity Log Diff Panel
    -----------------------
    F-064: Expandable before/after attribute diff view.

    BR-195: Before/after values stored for model attribute changes.
    Edge case: Activity without before/after (create/delete) shows "Created" or "Deleted"
    with the final/initial state only.
    Edge case: Very large properties payload — scrollable with max height.
--}}
@php
    $properties = $activity->properties ?? collect([]);
    $oldValues = $properties->get('old', []) ?? [];
    $newValues = $properties->get('attributes', []) ?? [];
    $hasOldNew = !empty($oldValues) && !empty($newValues);
    $hasOnlyNew = empty($oldValues) && !empty($newValues);
    $hasOnlyOld = !empty($oldValues) && empty($newValues);

    // For create/delete — show the single state
    $singleState = $hasOnlyNew ? $newValues : ($hasOnlyOld ? $oldValues : []);

    // Determine changed keys (for update events with both old and new)
    $changedKeys = [];
    if ($hasOldNew) {
        $allKeys = array_unique(array_merge(array_keys($oldValues), array_keys($newValues)));
        foreach ($allKeys as $key) {
            $changedKeys[] = $key;
        }
    }

    // Other non-diff properties (ip, description overrides, etc.)
    $metaProps = $properties->except(['old', 'attributes'])->toArray();
@endphp

<div class="p-4">
    @if ($hasOldNew)
        {{-- Before / After diff --}}
        <p class="text-xs font-semibold text-on-surface uppercase tracking-wide mb-3">{{ __('Changed Fields') }}</p>
        <div class="overflow-auto max-h-64">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline">
                        <th class="text-left py-1.5 pr-4 font-semibold text-on-surface w-1/4">{{ __('Field') }}</th>
                        <th class="text-left py-1.5 pr-4 font-semibold text-danger w-5/12">{{ __('Before') }}</th>
                        <th class="text-left py-1.5 font-semibold text-success w-5/12">{{ __('After') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline">
                    @foreach ($changedKeys as $key)
                        @php
                            $before = array_key_exists($key, $oldValues) ? $oldValues[$key] : null;
                            $after = array_key_exists($key, $newValues) ? $newValues[$key] : null;
                            $isChanged = $before !== $after;
                        @endphp
                        <tr class="{{ $isChanged ? '' : 'opacity-50' }}">
                            <td class="py-1.5 pr-4 font-mono text-on-surface font-medium">{{ $key }}</td>
                            <td class="py-1.5 pr-4">
                                @if ($before !== null)
                                    <span class="{{ $isChanged ? 'bg-danger-subtle text-danger' : 'text-on-surface' }} px-1.5 py-0.5 rounded font-mono break-all">
                                        {{ is_array($before) ? json_encode($before) : (string) $before }}
                                    </span>
                                @else
                                    <span class="text-on-surface/40 italic">{{ __('(empty)') }}</span>
                                @endif
                            </td>
                            <td class="py-1.5">
                                @if ($after !== null)
                                    <span class="{{ $isChanged ? 'bg-success-subtle text-success' : 'text-on-surface' }} px-1.5 py-0.5 rounded font-mono break-all">
                                        {{ is_array($after) ? json_encode($after) : (string) $after }}
                                    </span>
                                @else
                                    <span class="text-on-surface/40 italic">{{ __('(removed)') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @elseif (!empty($singleState))
        {{-- Create or Delete — show single state --}}
        @php
            $stateLabel = $hasOnlyNew ? __('Created State') : __('Deleted State');
        @endphp
        <p class="text-xs font-semibold text-on-surface uppercase tracking-wide mb-3">{{ $stateLabel }}</p>
        <div class="overflow-auto max-h-64">
            <table class="w-full text-xs">
                <thead>
                    <tr class="border-b border-outline">
                        <th class="text-left py-1.5 pr-4 font-semibold text-on-surface w-1/3">{{ __('Field') }}</th>
                        <th class="text-left py-1.5 font-semibold text-on-surface">{{ __('Value') }}</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-outline">
                    @foreach ($singleState as $key => $value)
                        <tr>
                            <td class="py-1.5 pr-4 font-mono text-on-surface font-medium">{{ $key }}</td>
                            <td class="py-1.5">
                                @if ($value !== null)
                                    <span class="{{ $hasOnlyNew ? 'bg-success-subtle text-success' : 'bg-danger-subtle text-danger' }} px-1.5 py-0.5 rounded font-mono break-all">
                                        {{ is_array($value) ? json_encode($value) : (string) $value }}
                                    </span>
                                @else
                                    <span class="text-on-surface/40 italic">{{ __('null') }}</span>
                                @endif
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>
    @endif

    {{-- Meta Properties (ip, custom description, etc.) --}}
    @if (!empty($metaProps))
        <div class="mt-3 pt-3 border-t border-outline">
            <p class="text-xs font-semibold text-on-surface uppercase tracking-wide mb-2">{{ __('Additional Info') }}</p>
            <div class="overflow-auto max-h-32">
                <dl class="grid grid-cols-2 gap-x-4 gap-y-1 text-xs">
                    @foreach ($metaProps as $key => $value)
                        <dt class="font-mono text-on-surface font-medium truncate">{{ $key }}</dt>
                        <dd class="font-mono text-on-surface break-all">
                            {{ is_array($value) ? json_encode($value) : (string) $value }}
                        </dd>
                    @endforeach
                </dl>
            </div>
        </div>
    @endif
</div>
