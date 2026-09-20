<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timatic — Browserextensie koppelen</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8">
        <img src="/logo.svg" alt="Timatic" class="h-8 mb-6"/>

        <h1 class="text-lg font-semibold text-gray-900">Browserextensie koppelen</h1>

        <p class="text-sm text-gray-600 mt-2">
            De Timatic-browserextensie wil toegang tot Timatic als
            <span class="font-medium text-gray-900">{{ $user->full_name }}</span>
            ({{ $user->email }}).
        </p>

        <ul class="text-sm text-gray-600 mt-4 space-y-2 list-disc list-inside">
            <li>Tijd registreren op domeinen die jij hebt aangezet</li>
            <li>Klanten en budgetten lezen om een domein te koppelen</li>
        </ul>

        <p class="text-xs text-gray-400 mt-4">
            Je kunt de koppeling later intrekken vanuit de extensie of in Timatic.
        </p>

        <form method="POST" action="{{ $approveUrl }}" class="mt-6">
            <button type="submit"
                    class="w-full rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-sm font-medium py-2.5">
                Koppelen
            </button>
        </form>

        <a href="{{ $denyUrl }}"
           class="block text-center text-sm text-gray-500 hover:text-gray-700 mt-3">
            Annuleren
        </a>
    </div>
</body>
</html>
