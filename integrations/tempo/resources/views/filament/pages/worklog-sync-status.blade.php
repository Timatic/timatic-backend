{{-- Package views are not scanned by the admin theme, so this view styles itself without utility classes --}}
@if ($isRunning)
    <div wire:poll.2s style="font-size: 0.875rem; color: var(--gray-500)">
        <p>{{ __('tempo::tempo.settings.sync_status_running') }}</p>
        <progress style="width: 100%; margin-top: 0.5rem"></progress>
    </div>
@endif
