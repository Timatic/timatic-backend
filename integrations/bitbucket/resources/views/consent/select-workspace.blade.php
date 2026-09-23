@extends('layouts.consent')

@section('subtitle', __('bitbucket::bitbucket.consent.subtitle'))

@php
    $controlClasses = 'w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-primary';
@endphp

@section('content')
    @if ($justConnected)
        <x-consent.notice type="success" class="mb-4">
            {{ __('bitbucket::bitbucket.consent.connected_select_workspace') }}
        </x-consent.notice>
    @endif

    @if ($webhookFailed)
        <x-consent.notice type="error" class="mb-4">
            {{ __('bitbucket::bitbucket.consent.webhook_failed') }}
        </x-consent.notice>
    @endif

    <form method="POST" action="{{ route('bitbucket.delegate.install-webhook', $integration->share_token) }}" class="space-y-4">
        @csrf

        @if ($workspaces->isNotEmpty())
            <x-consent.field :label="__('bitbucket::bitbucket.consent.workspace_label')" for="workspace_slug">
                <select name="workspace_slug" id="workspace_slug" class="{{ $controlClasses }}">
                    @foreach ($workspaces as $workspace)
                        <option value="{{ $workspace->slug }}" @disabled(! $workspace->isAdministrator)>
                            {{ $workspace->slug }}@unless($workspace->isAdministrator) ({{ __('bitbucket::bitbucket.consent.not_administrator') }})@endunless
                        </option>
                    @endforeach
                </select>

                @if ($workspaces->contains(fn ($workspace) => ! $workspace->isAdministrator))
                    <x-slot:hint>{{ __('bitbucket::bitbucket.consent.administrator_hint') }}</x-slot:hint>
                @endif
            </x-consent.field>
        @else
            <x-consent.field :label="__('bitbucket::bitbucket.consent.workspace_slug_label')" for="workspace_slug">
                <input type="text" name="workspace_slug" id="workspace_slug" required
                       placeholder="{{ __('bitbucket::bitbucket.consent.workspace_slug_placeholder') }}"
                       class="{{ $controlClasses }}">
            </x-consent.field>
        @endif

        <x-consent.button>
            {{ __('bitbucket::bitbucket.consent.install_webhook_action') }}
        </x-consent.button>
    </form>
@endsection
