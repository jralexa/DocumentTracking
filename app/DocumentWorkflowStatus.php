<?php

namespace App;

enum DocumentWorkflowStatus: string
{
    case Incoming = 'incoming';
    case OnQueue = 'on_queue';
    case Outgoing = 'outgoing';
    case Finished = 'finished';
}
