<div class="flex min-h-screen flex-col items-center justify-center gap-8 px-6">
    @if ($turnError)
        <p class="max-w-md text-center text-sm text-ice/80">{{ $turnError }}</p>
    @endif

    @if ($lastTranscript)
        <p class="max-w-md text-center text-sm text-ice/90">{{ $lastTranscript }}</p>
    @endif

    <x-secretary-turn :message="$message" />

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
        class="flex flex-col items-center gap-3"
    >
        @if ($trackingTurnUuid)
            <p class="text-sm text-ice/70">Pensando...</p>
        @endif

        <x-talk-control />
    </div>
</div>
