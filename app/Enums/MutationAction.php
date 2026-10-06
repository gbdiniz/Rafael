<?php

namespace App\Enums;

enum MutationAction: string
{
    case Create = 'create';
    case Change = 'change';
    case Remove = 'remove';
}
