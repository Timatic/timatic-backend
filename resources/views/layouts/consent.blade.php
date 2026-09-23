<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>@yield('subtitle', __('consent.title')) — Timatic</title>
    @vite('resources/css/consent.css')
</head>
<body class="bg-gray-50 min-h-screen flex items-center justify-center p-4">
    <main class="bg-white rounded-2xl shadow-sm border border-gray-200 w-full max-w-md p-8">
        <img src="/logo.svg" alt="Timatic" class="h-8 mb-6">

        @yield('content')
    </main>
</body>
</html>
