<!doctype html>
<html lang="en">
<head>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
    <title>Staff sign in</title>
</head>
<body class="min-h-screen bg-zinc-950 text-zinc-100">
    <main class="mx-auto flex min-h-screen max-w-md items-center px-6">
        <form method="POST" action="{{ route('admin.login.store') }}" class="w-full space-y-6 rounded-2xl border border-zinc-800 bg-zinc-900 p-8 shadow-2xl">
            @csrf
            <div>
                <p class="text-sm font-semibold uppercase tracking-[0.2em] text-emerald-400">Financial Tracker</p>
                <h1 class="mt-3 text-2xl font-semibold">Staff sign in</h1>
                <p class="mt-2 text-sm text-zinc-400">Use your staff credentials and authenticator code.</p>
            </div>
            @if ($errors->any())
                <p class="rounded-lg border border-red-900 bg-red-950/50 p-3 text-sm text-red-200" role="alert">{{ $errors->first() }}</p>
            @endif
            <label class="block text-sm">Email<input name="email" type="email" required autofocus autocomplete="username" value="{{ old('email') }}" class="mt-2 w-full rounded-lg border-zinc-700 bg-zinc-950 text-zinc-100"></label>
            <label class="block text-sm">Password<input name="password" type="password" required autocomplete="current-password" class="mt-2 w-full rounded-lg border-zinc-700 bg-zinc-950 text-zinc-100"></label>
            <label class="block text-sm">Authenticator code<input name="code" inputmode="numeric" pattern="[0-9]{6}" maxlength="6" required autocomplete="one-time-code" class="mt-2 w-full rounded-lg border-zinc-700 bg-zinc-950 text-zinc-100"></label>
            <button class="w-full rounded-lg bg-emerald-500 px-4 py-3 font-semibold text-zinc-950 hover:bg-emerald-400" type="submit">Sign in</button>
        </form>
    </main>
</body>
</html>
