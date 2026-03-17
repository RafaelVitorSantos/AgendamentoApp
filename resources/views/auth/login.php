<!DOCTYPE html>
<html lang="pt-BR" class="h-full bg-gray-50">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Login — <?= e(config('app.name')) ?></title>
    <script src="https://cdn.tailwindcss.com"></script>
    <script>tailwind.config = { theme: { extend: { colors: { brand: { 500: '#6366F1', 600: '#4F46E5', 700: '#4338CA' } } } } }</script>
</head>
<body class="h-full">
    <div class="flex min-h-full flex-col justify-center py-12 sm:px-6 lg:px-8">
        <div class="sm:mx-auto sm:w-full sm:max-w-md">
            <div class="flex justify-center">
                <div class="h-12 w-12 rounded-xl bg-brand-600 flex items-center justify-center">
                    <svg class="h-7 w-7 text-white" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" d="M6.75 3v2.25M17.25 3v2.25M3 18.75V7.5a2.25 2.25 0 0 1 2.25-2.25h13.5A2.25 2.25 0 0 1 21 7.5v11.25m-18 0A2.25 2.25 0 0 0 5.25 21h13.5A2.25 2.25 0 0 0 21 18.75m-18 0v-7.5A2.25 2.25 0 0 1 5.25 9h13.5A2.25 2.25 0 0 1 21 11.25v7.5"/></svg>
                </div>
            </div>
            <h2 class="mt-4 text-center text-2xl font-bold tracking-tight text-gray-900">Agenda<span class="text-brand-600">PRO</span></h2>
            <p class="mt-2 text-center text-sm text-gray-600">Acesse sua conta para gerenciar seus agendamentos</p>
        </div>

        <div class="mt-8 sm:mx-auto sm:w-full sm:max-w-md">
            <div class="bg-white px-6 py-8 shadow-xl sm:rounded-xl sm:px-12 border border-gray-100">

                <?php if ($error = get_flash('error')): ?>
                    <div class="mb-4 rounded-lg bg-red-50 p-3 text-sm text-red-700 border border-red-200"><?= e($error) ?></div>
                <?php endif; ?>

                <?php if (isset($_GET['expired'])): ?>
                    <div class="mb-4 rounded-lg bg-yellow-50 p-3 text-sm text-yellow-700 border border-yellow-200">Sua sessão expirou. Faça login novamente.</div>
                <?php endif; ?>

                <form method="POST" action="<?= url('login') ?>" class="space-y-5">
                    <?= csrf_field() ?>

                    <div>
                        <label for="email" class="block text-sm font-medium text-gray-700">Email</label>
                        <input type="email" name="email" id="email" required autofocus
                               value="<?= e(get_flash('old_email', '')) ?>"
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500 focus:outline-none sm:text-sm"
                               placeholder="seu@email.com">
                    </div>

                    <div>
                        <label for="password" class="block text-sm font-medium text-gray-700">Senha</label>
                        <input type="password" name="password" id="password" required
                               class="mt-1 block w-full rounded-lg border border-gray-300 px-3 py-2.5 text-gray-900 shadow-sm placeholder:text-gray-400 focus:border-brand-500 focus:ring-2 focus:ring-brand-500 focus:outline-none sm:text-sm"
                               placeholder="Sua senha">
                    </div>

                    <div class="flex items-center justify-between">
                        <label class="flex items-center gap-2">
                            <input type="checkbox" name="remember" class="h-4 w-4 rounded border-gray-300 text-brand-600 focus:ring-brand-500">
                            <span class="text-sm text-gray-600">Lembrar de mim</span>
                        </label>
                        <a href="<?= url('forgot-password') ?>" class="text-sm font-medium text-brand-600 hover:text-brand-500">Esqueceu a senha?</a>
                    </div>

                    <button type="submit"
                            class="flex w-full justify-center rounded-lg bg-brand-600 px-3 py-2.5 text-sm font-semibold text-white shadow-sm hover:bg-brand-500 focus-visible:outline focus-visible:outline-2 focus-visible:outline-offset-2 focus-visible:outline-brand-600 transition-colors">
                        Entrar
                    </button>
                </form>
            </div>

            <p class="mt-6 text-center text-sm text-gray-500">
                Ainda não tem conta?
                <a href="<?= url('register') ?>" class="font-semibold text-brand-600 hover:text-brand-500">Cadastre sua empresa grátis</a>
            </p>
        </div>
    </div>
</body>
</html>
