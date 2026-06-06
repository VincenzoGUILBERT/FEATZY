<?php

namespace App\Enums;

enum UserRole: string
{
    case Client = 'client';
    case Restaurateur = 'restaurateur';
    case Admin = 'admin';
}
