<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Operator = 'operator';
    case Viewer = 'viewer';
}
