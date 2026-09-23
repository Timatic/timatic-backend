@props(['label', 'for'])

<div>
    <label for="{{ $for }}" class="block text-sm font-medium text-gray-700 mb-1">{{ $label }}</label>

    {{ $slot }}

    @if (isset($hint))
        <p class="text-xs text-gray-500 mt-1">{{ $hint }}</p>
    @endif
</div>
