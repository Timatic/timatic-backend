@php
    use Carbon\CarbonImmutable;
    use Illuminate\Support\Str;
    use Timatic\Tempo\Enums\WorklogSyncStatus;

    $isActive = in_array($run?->status, [WorklogSyncStatus::Queued, WorklogSyncStatus::Running], true);
    $day = fn (int $milliseconds): string => CarbonImmutable::createFromTimestampMs($milliseconds, $timezone)->format('d-m-Y');
@endphp

{{-- Package views are not scanned by the admin theme, so this view styles itself without utility classes --}}
<div @if ($isActive) wire:poll.2s @endif style="font-size: 0.875rem; color: var(--gray-500)">
    @if ($run?->status === WorklogSyncStatus::Queued)
        <p>{{ __('tempo::tempo.settings.sync_status_queued') }}</p>
    @elseif ($run?->status === WorklogSyncStatus::Running && $run->currentWorklogStartedAtMilliseconds === null)
        <p>{{ __('tempo::tempo.settings.sync_status_fetching') }}</p>
    @elseif ($run?->status === WorklogSyncStatus::Running)
        <p>{{ __('tempo::tempo.settings.sync_status_running', ['count' => $run->processed, 'first' => $day($run->firstWorklogStartedAtMilliseconds), 'current' => $day($run->currentWorklogStartedAtMilliseconds)]) }}</p>
    @elseif ($run?->status === WorklogSyncStatus::Finished)
        <p>{{ __('tempo::tempo.settings.sync_status_finished', ['created' => $run->created, 'updated' => $run->updated, 'deleted' => $run->deleted, 'skipped' => $run->skipped]) }}</p>
    @elseif ($run?->status === WorklogSyncStatus::Failed)
        <p style="color: var(--danger-600)">{{ __('tempo::tempo.settings.sync_status_failed', ['message' => Str::limit((string) $run->failure, 200)]) }}</p>
    @endif

    @if ($isActive)
        <progress style="width: 100%; margin-top: 0.5rem" @if ($run->progressPercentage() !== null) value="{{ $run->progressPercentage() }}" max="100" @endif></progress>
    @endif
</div>
