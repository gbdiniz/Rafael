<?php

namespace App\Enums;

enum QuestionTopic: string
{
    case Tasks = 'tasks';
    case Appointments = 'appointments';
    case Both = 'both';
}
