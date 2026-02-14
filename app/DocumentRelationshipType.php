<?php

namespace App;

enum DocumentRelationshipType: string
{
    case MergedInto = 'merged_into';
    case SplitFrom = 'split_from';
    case AttachedTo = 'attached_to';
    case RelatedTo = 'related_to';
}
