<?php

namespace App\Actions\Conversation;

final readonly class TurnOutcome
{
    /**
     * @param  array<string, mixed>|null  $proposal
     * @param  array<int, array<string, mixed>>  $matches
     * @param  array<string, mixed>|null  $pendingPatch
     */
    public function __construct(
        public string $message,
        public ?array $proposal = null,
        public array $matches = [],
        public ?array $pendingPatch = null,
    ) {}
}
