<div>
<div class="pointer-events-none relative z-20 flex min-h-screen flex-col items-center justify-center gap-8 px-6">
    @if ($turnError)
        <p class="max-w-md text-center text-sm text-ice/80">{{ $turnError }}</p>
    @endif

    @if ($lastTranscript)
        <p class="max-w-md text-center text-sm text-ice/90">{{ $lastTranscript }}</p>
    @endif

    <x-secretary-turn :message="$message" />

    @if ($speechBlocked)
        <p class="max-w-md text-center text-sm text-ice/80">Não foi possível falar. A mensagem está na tela.</p>
    @endif

    @if (count($matches) > 0)
        <ul class="flex max-w-md flex-col gap-2 text-center text-sm text-ice/90">
            @foreach ($matches as $match)
                <li wire:key="match-{{ $match['id'] }}">{{ $match['title'] }}</li>
            @endforeach
        </ul>
    @endif

    <div
        @if ($trackingTurnUuid)
            wire:poll.2s="refreshTurnStatus"
        @endif
        class="pointer-events-auto relative z-30 flex flex-col items-center gap-3"
    >
        @if ($trackingTurnUuid)
            <p class="text-sm text-ice/70">Pensando...</p>
        @endif

        <x-talk-control />
    </div>
</div>

<div
    wire:ignore
    x-data="speechPlayback({
        streamUrl: @js(route('speech.stream')),
        csrfToken: @js(csrf_token()),
        speakOnLoad: @js(! $speechBlocked && $message !== ''),
    })"
    x-on:rafael-speak.window="schedulePlay()"
    x-init="if (speakOnLoad) schedulePlay()"
>
    <div x-bind:hidden="! waitingForTap" hidden class="speech-wave pointer-events-none fixed inset-0 z-0"></div>
    <button
        type="button"
        x-bind:hidden="! waitingForTap"
        hidden
        class="fixed inset-0 z-10 cursor-pointer border-0 bg-transparent p-0"
        @click="tap()"
        aria-label="Ouvir"
    ></button>
    <p
        x-bind:hidden="! blocked"
        hidden
        x-text="failure"
        class="pointer-events-none fixed inset-x-0 bottom-24 z-20 px-6 text-center text-sm text-ice/80"
    ></p>
</div>
</div>
