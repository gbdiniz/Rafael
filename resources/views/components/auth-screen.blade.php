<div class="flex min-h-screen items-center justify-center px-6">
    <form action="{{ route('login') }}" method="post" class="flex w-full max-w-sm flex-col gap-6">
        @csrf

        <div class="flex flex-col gap-2">
            <label for="email" class="text-sm font-medium">E-mail</label>
            <input
                id="email"
                name="email"
                type="email"
                value="{{ old('email') }}"
                autocomplete="username"
                required
                class="rounded border border-ice/20 bg-transparent px-3 py-2 text-ice outline-none focus:border-ice"
            >
            @error('email')
                <p class="text-sm text-ice">{{ $message }}</p>
            @enderror
        </div>

        <div class="flex flex-col gap-2">
            <label for="password" class="text-sm font-medium">Senha</label>
            <input
                id="password"
                name="password"
                type="password"
                autocomplete="current-password"
                required
                class="rounded border border-ice/20 bg-transparent px-3 py-2 text-ice outline-none focus:border-ice"
            >
            @error('password')
                <p class="text-sm text-ice">{{ $message }}</p>
            @enderror
        </div>

        <button
            type="submit"
            class="rounded bg-ice px-4 py-2 font-medium text-void"
        >
            Entrar
        </button>
    </form>
</div>
