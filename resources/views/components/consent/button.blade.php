@props(['href' => null])

@php
    $appearance = 'block w-full text-center bg-primary hover:bg-primary-hover text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors';
@endphp

@if ($href)
    <a href="{{ $href }}" {{ $attributes->merge(['class' => $appearance]) }}>{{ $slot }}</a>
@else
    <button type="submit" {{ $attributes->merge(['class' => $appearance]) }}>{{ $slot }}</button>
@endif
