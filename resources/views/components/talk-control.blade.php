<div
    wire:ignore
    data-talk-control
    data-talk-control-ignores-mouseleave
    x-data="talkControl({
        uploadUrl: @js(route('voice-turns.store')),
        csrfToken: @js(csrf_token()),
    })"
    x-init="initBars($refs.bars)"
    class="flex flex-col items-center gap-4"
>
    <button
        type="button"
        data-talk-control-click="toggle"
        x-bind:data-talk-control-state="recording ? 'stop' : 'start'"
        @click="toggle()"
        class="flex size-16 items-center justify-center rounded-full border border-ice/30 bg-ice/10 text-sm font-medium text-ice transition hover:border-ice/60"
    >
        <span x-text="recording ? 'Parar' : 'Falar'"></span>
    </button>

    <div
        x-ref="bars"
        data-talk-listening-bars
        x-bind:data-talk-listening="recording ? 'true' : 'false'"
        class="flex h-8 items-end gap-1"
    >
        @foreach (range(1, 4) as $bar)
            <span
                data-talk-bar
                class="block h-4 w-1 origin-bottom rounded-full bg-ice/70"
            ></span>
        @endforeach
    </div>
</div>
