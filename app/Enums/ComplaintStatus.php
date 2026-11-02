<?php

namespace App\Enums;

enum ComplaintStatus: string
{
    case Submitted = 'submitted';
    case Forwarded = 'forwarded';
    case Resolved = 'resolved';
}
