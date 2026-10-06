<?php

namespace App\Enums;

enum RecordKind: string
{
    case Appointment = 'appointment';
    case Task = 'task';
}
