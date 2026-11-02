<?php

namespace App\Enums;

enum ComplaintCategory: string
{
    case Billing = 'billing';
    case Meter = 'meter';
    case Load = 'load';
    case Connection = 'connection';
    case Supply = 'supply';
    case Other = 'other';
}
