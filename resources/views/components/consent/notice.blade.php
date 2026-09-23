@props(['type' => 'success'])

@php
    $appearance = match ($type) {
        'error' => 'bg-red-50 border-red-200 text-red-700',
        default => 'bg-green-50 border-green-200 text-green-700',
    };
@endphp

<div {{ $attributes->merge(['class' => "rounded-lg border p-4 text-sm {$appearance}"]) }}>
    {{ $slot }}
</div>
