@extends('layouts.delegate')

@section('subtitle', 'GitHub integratie')

@section('content')
    @php $config = $integration->config ?? []; @endphp

    @if($configured)
        <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700 mb-4">
            GitHub is verbonden voor {{ implode(', ', $accounts) }}. Timatic rondt de configuratie verder af.
        </div>
        <p class="text-sm text-gray-600 mb-6">
            Heeft u de app op nog een organisatie geïnstalleerd? Installeer deze op GitHub en herlaad daarna
            deze pagina.
        </p>
        <a href="{{ $installUrl }}" target="_blank" rel="noopener"
           class="block w-full text-center bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
            App installeren op nog een organisatie
        </a>

    @elseif(filled($config['access_token'] ?? null))
        @if(request('connected'))
            <div class="rounded-lg bg-green-50 border border-green-200 p-4 text-sm text-green-700 mb-4">
                GitHub succesvol verbonden.
            </div>
        @endif

        <p class="text-sm text-gray-600 mb-6">
            De Timatic app is nog niet geïnstalleerd op uw organisatie. Installeer de app op GitHub en herlaad
            daarna deze pagina.
        </p>
        <a href="{{ $installUrl }}" target="_blank" rel="noopener"
           class="block w-full text-center bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
            App installeren op GitHub
        </a>

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
