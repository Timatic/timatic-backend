<!DOCTYPE html>
<html lang="nl">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Timatic — Integratie koppelen</title>
    <script src="https://cdn.tailwindcss.com"></script>
    <style>
        :root {
            --color-primary: 91 180 111;
            --color-primary-hover: 158 216 169;
        }
    </style>
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <div class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8">
        <div class="mb-6">
            <p class="text-xs font-semibold uppercase tracking-widest text-gray-400 mb-1">
                <img src="/logo.svg"/>
            </p>
        </div>

        @yield('content')

        @unless($expired ?? false)
            <p class="text-xs text-gray-400 mt-6 text-center">
                Link geldig tot {{ $integration->share_token_expires_at?->format('d-m-Y H:i') }}
            </p>
        @endunless
    </div>
</body>
</html>
