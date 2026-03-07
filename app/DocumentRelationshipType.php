<?php

namespace App;

enum DocumentRelationshipType: string
{
    case MergedInto = 'merged_into';
    case AttachedTo = 'attached_to';
    case RelatedTo = 'related_to';
}
