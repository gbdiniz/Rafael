<?php

namespace App;

use App\Enums\MutationAction;
use App\Enums\QuestionTopic;
use App\Enums\RecordKind;
use App\Enums\TurnIntent;

final readonly class Interpretation
{
    public function __construct(
        public TurnIntent $intent,
        public ?QuestionTopic $topic = null,
        public ?MutationAction $action = null,
        public ?RecordKind $kind = null,
        public ?string $title = null,
        public ?string $scheduledAt = null,
        public ?int $recordId = null,
    ) {}
}
