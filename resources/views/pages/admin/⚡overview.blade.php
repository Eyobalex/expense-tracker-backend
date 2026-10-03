<?php

use Livewire\Attributes\Title;
use Livewire\Component;

new #[Title('Admin overview')] class extends Component {
};
?>

<div class="min-h-screen bg-zinc-100 text-zinc-900">
    <header class="border-b border-zinc-200 bg-white">
        <div class="mx-auto flex max-w-7xl items-center justify-between gap-4 px-6 py-5">
            <div>
                <p class="text-xs font-semibold uppercase tracking-[0.2em] text-emerald-700">Financial Tracker</p>
                <h1 class="mt-1 text-xl font-semibold">Operations overview</h1>
            </div>
            <form method="POST" action="{{ route('admin.logout') }}">
                @csrf
                <button class="rounded-lg border border-zinc-300 px-3 py-2 text-sm" type="submit">Sign out</button>
            </form>
        </div>
    </header>

    <main class="mx-auto max-w-7xl px-6 py-10">
        <section class="rounded-2xl border border-zinc-200 bg-white p-8 shadow-sm" aria-labelledby="foundation-title">
            <h2 id="foundation-title" class="text-lg font-semibold">Admin foundation is active</h2>
            <p class="mt-2 max-w-2xl text-sm text-zinc-600">Operational cards will be added only when their server-observed source, freshness and coverage are implemented.</p>
            <p class="mt-6 text-xs text-zinc-500">Signed in as {{ auth('staff')->user()->name }}.</p>
        </section>
    </main>
</div>
