<?php

namespace App\Actions\Records;

use App\Models\Record;

class RemoveRecord
{
    public function handle(Record $record): void
    {
        $record->delete();
    }
}
