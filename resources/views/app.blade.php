<!DOCTYPE html>
<html lang="es">
    <head>
        <meta charset="utf-8">
        <meta name="viewport" content="width=device-width, initial-scale=1">
        <meta name="csrf-token" content="{{ csrf_token() }}">
        <title>Chat RAG</title>
        @viteReactRefresh
        @vite('resources/react/src/main.tsx')
    </head>
    <body class="bg-gray-100">
        <div id="root"></div>
    </body>
</html>
