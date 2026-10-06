<?php

namespace App\Enums;

enum TurnIntent: string
{
    case Question = 'question';
    case MutationDraft = 'mutation_draft';
    case Yes = 'yes';
    case No = 'no';
    case NotAboutTheBooks = 'not_about_the_books';
}
