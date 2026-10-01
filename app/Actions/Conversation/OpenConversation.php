<?php

namespace App\Actions\Conversation;

use App\Models\User;

class OpenConversation
{
    public function handle(User $user): string
    {
        if ($user->conversation_opened_at === null) {
            $user->conversation_opened_at = now();
            $user->save();
        }

        return 'Olá.';
    }
}
