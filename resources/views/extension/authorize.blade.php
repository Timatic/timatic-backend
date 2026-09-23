@extends('layouts.consent')

@section('subtitle', __('consent.extension.subtitle'))

@section('content')
    <h1 class="text-lg font-semibold text-gray-900">{{ __('consent.extension.subtitle') }}</h1>

    <x-consent.intro class="mt-2">
        {{ __('consent.extension.intro', ['name' => $user->full_name, 'email' => $user->email]) }}
    </x-consent.intro>

    <ul class="text-sm text-gray-600 mt-4 space-y-2 list-disc list-inside">
        <li>{{ __('consent.extension.scope_track_time') }}</li>
        <li>{{ __('consent.extension.scope_read_customers') }}</li>
    </ul>

    <p class="text-xs text-gray-400 mt-4">
        {{ __('consent.extension.revoke_hint') }}
    </p>

    <form method="POST" action="{{ $approveUrl }}" class="mt-6">
        @csrf

        <x-consent.button>
            {{ __('consent.extension.approve_action') }}
        </x-consent.button>
    </form>

    <a href="{{ $denyUrl }}"
       class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-3">
        {{ __('consent.extension.deny_action') }}
    </a>
@endsection
