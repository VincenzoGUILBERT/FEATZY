<?php

namespace App\Enums;

enum ParticipantRole: string
{
    case Organizer = 'organizer';
    case Guest = 'guest';
}
