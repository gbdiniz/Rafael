<?php

namespace App;

use App\Enums\QuestionTopic;
use App\Enums\TurnIntent;

final readonly class Interpretation
{
    public function __construct(
        public TurnIntent $intent,
        public ?QuestionTopic $topic = null,
    ) {}
}
