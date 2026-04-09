<form method="POST" action="{{ route('bitbucket.delegate.install-webhook', $integration->share_token) }}" class="space-y-4">
    @csrf
    @if(count($workspaces) > 0)
        <div>
            <label for="workspace_slug" class="block text-sm font-medium text-gray-700 mb-1">Workspace</label>
            <select name="workspace_slug" id="workspace_slug"
                    class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--color-primary))]">
                @foreach($workspaces as $workspace)
                    <option value="{{ $workspace->slug }}" @disabled(! $workspace->isAdministrator)>
                        {{ $workspace->slug }}@if(! $workspace->isAdministrator) (geen beheerder) @endif
                    </option>
                @endforeach
            </select>
            @if(collect($workspaces)->contains(fn ($workspace) => ! $workspace->isAdministrator))
                <p class="text-xs text-gray-500 mt-1">
                    Workspaces met "(geen beheerder)" zijn uitgeschakeld omdat u daar geen beheerdersrechten heeft. Alleen beheerders kunnen de webhook installeren.
                </p>
            @endif
        </div>
    @else
        <div>
            <label for="workspace_slug" class="block text-sm font-medium text-gray-700 mb-1">Workspace slug</label>
            <input type="text" name="workspace_slug" id="workspace_slug"
                   placeholder="bijv. mijn-organisatie"
                   class="w-full border border-gray-300 rounded-lg text-sm px-3 py-2 focus:outline-none focus:ring-2 focus:ring-[rgb(var(--color-primary))]"
                   required>
        </div>
    @endif
    <button type="submit"
            class="w-full bg-[rgb(var(--color-primary))] hover:bg-[rgb(var(--color-primary-hover))] text-white hover:text-black text-sm font-medium py-2.5 px-4 rounded-lg transition-colors">
        Webhook installeren
    </button>
</form>
