<?php

namespace App;

enum TransferStatus: string
{
    case Pending = 'pending';
    case Accepted = 'accepted';
    case Recalled = 'recalled';
    case Cancelled = 'cancelled';
}
