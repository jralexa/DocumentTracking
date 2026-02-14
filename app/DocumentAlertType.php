<?php

namespace App;

enum DocumentAlertType: string
{
    case Overdue = 'overdue';
    case Stalled = 'stalled';
}
