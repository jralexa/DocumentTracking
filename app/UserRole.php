<?php

namespace App;

enum UserRole: string
{
    case Admin = 'admin';
    case Manager = 'manager';
    case Regular = 'regular';
    case Guest = 'guest';
}
