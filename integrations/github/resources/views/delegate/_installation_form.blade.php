@if(count($installations) > 0)
    <form method="POST" action="{{ route('github.delegate.choose-installation', $integration->share_token) }}" class="space-y-4">
        @csrf
        <div>
            <label for="installation_id" class="block text-sm font-medium text-gray-700 mb-1">Installatie</label>
            <select name="installation_id" id="installation_id"
                    class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--color-primary))]">
                @foreach($installations as $installation)
                    <option value="{{ $installation->id }}">
                        {{ $installation->accountLogin }} ({{ $installation->accountType }})
                    </option>
                @endforeach
            </select>
        </div>
        <button type="submit"
                class="w-full bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
            Installatie koppelen
        </button>
    </form>
@else
    <p class="text-sm text-gray-600 mb-6">
        De Timatic app is nog niet geïnstalleerd op uw organisatie. Installeer de app op GitHub en kom daarna
        terug op deze pagina.
    </p>
    <a href="{{ $installUrl }}" target="_blank" rel="noopener"
       class="block w-full text-center bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
        App installeren op GitHub
    </a>
@endif
