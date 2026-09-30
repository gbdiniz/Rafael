<?php

namespace App;

enum RecordKind: string
{
    case Appointment = 'appointment';
    case Task = 'task';
}
