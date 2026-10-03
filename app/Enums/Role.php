<?php

namespace App\Enums;

enum Role: string
{
    case Admin = 'admin';
    case Counsellor = 'counsellor';
    case Viewer = 'viewer';
}
