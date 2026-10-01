<!DOCTYPE html>
<html lang="pt-BR">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <title>{{ config('app.name') }}</title>
        @vite(['resources/css/app.css', 'resources/js/app.js'])
    </head>
    <body class="min-h-screen bg-void text-ice antialiased">
        <main class="flex min-h-screen items-center justify-center px-6">
            <p>Conversa</p>
        </main>
    </body>
</html>
