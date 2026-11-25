{{-- Package views are not scanned by the admin theme, so this view styles itself without utility classes --}}
@if ($batch !== null && (! $batch->finished() || $batch->failedJobs > 0))
    <div @if (! $batch->finished()) wire:poll.5s @endif style="font-size: 0.875rem; color: var(--gray-500)">
        @if (! $batch->finished())
            <p>{{ __('tempo::tempo.settings.import_running', ['remaining' => $batch->pendingJobs - $batch->failedJobs, 'total' => $batch->totalJobs]) }}</p>
        @endif

        @if ($batch->failedJobs > 0)
            <p style="color: var(--danger-600)">{{ trans_choice('tempo::tempo.settings.import_failed', $batch->failedJobs) }}</p>
        @endif
    </div>
@endif
