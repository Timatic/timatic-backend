@extends('layouts.consent')

@section('subtitle', __('consent.authorize.subtitle', ['client' => $client->label]))

@section('content')
    <h1 class="text-lg font-semibold text-gray-900">
        {{ __('consent.authorize.subtitle', ['client' => $client->label]) }}
    </h1>

    <x-consent.intro class="mt-2 mb-4">
        {{ __('consent.authorize.intro', [
            'client' => $client->label,
            'name' => $user->full_name,
            'email' => $user->email,
        ]) }}
    </x-consent.intro>

    <ul class="text-sm text-gray-600 space-y-2 list-disc list-inside">
        @foreach (__('consent.authorize.scopes') as $scope)
            <li>{{ $scope }}</li>
        @endforeach
    </ul>

    <p class="text-xs text-gray-400 mt-4">{{ __('consent.authorize.revoke_hint') }}</p>

    <form method="POST" action="{{ $approveUrl }}" class="mt-6">
        @csrf

        <x-consent.button>{{ __('consent.authorize.approve') }}</x-consent.button>
    </form>

    <a href="{{ $denyUrl }}" class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-3">
        {{ __('consent.authorize.deny') }}
    </a>
@endsection
