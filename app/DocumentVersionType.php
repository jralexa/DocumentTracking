<?php

namespace App;

enum DocumentVersionType: string
{
    case Original = 'original';
    case CertifiedCopy = 'certified_copy';
    case Photocopy = 'photocopy';
    case Scan = 'scan';
}
