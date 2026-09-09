@extends('layouts.delegate')

@section('subtitle', 'GitHub integratie')

@section('content')
    @php $config = $integration->config ?? []; @endphp

    @if($configured)
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700">
            GitHub is verbonden en de installatie is gekoppeld. Timatic rondt de configuratie verder af.
        </div>

    @elseif(filled($config['access_token'] ?? null))
        @if(request('connected'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700 mb-4">
                GitHub succesvol verbonden. Selecteer nu de installatie van de Timatic app.
            </div>
        @endif

        @if(request('error') === 'installation_invalid')
            <div class="rounded-lg bg-red-50 border border-red-200 p-4 text-sm text-red-700 mb-4">
                De gekozen installatie hoort niet bij uw account. Probeer het opnieuw.
            </div>
        @endif

        @include('github::delegate._installation_form')

    @else
        <p class="text-sm text-gray-600 mb-6">
            Klik op de knop hieronder om GitHub te verbinden met Timatic voor {{ config('timatic.tenant_name') }}.
            U wordt doorgestuurd naar GitHub om toestemming te verlenen.
        </p>
        <a href="{{ route('github.delegate.oauth-redirect', $integration->share_token) }}"
           class="block w-full text-center bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
            Verbinden met GitHub
        </a>
    @endif
@endsection
