<!DOCTYPE html>
<html lang="pt-BR" class="h-full">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>500 — Erro interno</title>
    <script src="https://cdn.tailwindcss.com"></script>
</head>
<body class="h-full">
    <main class="grid min-h-full place-items-center bg-white px-6 py-24 sm:py-32 lg:px-8">
        <div class="text-center">
            <p class="text-base font-semibold text-red-600">500</p>
            <h1 class="mt-4 text-3xl font-bold tracking-tight text-gray-900 sm:text-5xl">Erro interno</h1>
            <p class="mt-6 text-base text-gray-600">Ocorreu um erro inesperado. Nossa equipe foi notificada.</p>
            <div class="mt-10 flex items-center justify-center gap-x-6">
                <a href="<?= url('dashboard') ?>" class="rounded-md bg-indigo-600 px-3.5 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-indigo-500">Ir para o Dashboard</a>
            </div>
        </div>
    </main>
</body>
</html>
