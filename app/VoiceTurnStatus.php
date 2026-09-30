<?php

namespace App;

enum VoiceTurnStatus: string
{
    case Uploaded = 'uploaded';
    case Transcribing = 'transcribing';
    case Completed = 'completed';
    case Failed = 'failed';
}
