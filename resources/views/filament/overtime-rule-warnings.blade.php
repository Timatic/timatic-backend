<div class="rounded-xl border border-warning-200 bg-warning-50 p-4 dark:border-warning-700 dark:bg-warning-950">
    <div class="flex gap-3">
        <x-filament::icon
            icon="heroicon-o-exclamation-triangle"
            class="mt-0.5 h-5 w-5 shrink-0 text-warning-500 dark:text-warning-400"
        />
        <div class="space-y-1">
            <p class="text-sm font-semibold text-warning-800 dark:text-warning-200">
                {{ trans_choice(':count conflict found in overtime rules|:count conflicts found in overtime rules', count($warnings), ['count' => count($warnings)]) }}
            </p>
            <ul class="list-inside list-disc space-y-0.5 text-sm text-warning-700 dark:text-warning-300">
                @foreach ($warnings as $warning)
                    <li>{{ $warning }}</li>
                @endforeach
            </ul>
        </div>
    </div>
</div>
