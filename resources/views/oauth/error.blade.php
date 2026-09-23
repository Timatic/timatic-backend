@extends('layouts.consent')

@section('subtitle', __('consent.error.subtitle'))

@section('content')
    <h1 class="text-lg font-semibold text-gray-900">
        {{ __('consent.error.subtitle') }}
    </h1>

    <x-consent.intro class="mt-2">
        {{ __('consent.error.intro') }}
    </x-consent.intro>
@endsection
