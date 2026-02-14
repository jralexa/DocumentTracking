<?php

namespace App;

enum DocumentEventType: string
{
    case DocumentCreated = 'document_created';
    case WorkflowForwarded = 'workflow_forwarded';
    case WorkflowAccepted = 'workflow_accepted';
    case WorkflowRecalled = 'workflow_recalled';
    case CustodyAssigned = 'custody_assigned';
    case CustodyDerivativeRecorded = 'custody_derivative_recorded';
    case CustodyReturned = 'custody_returned';
    case RelationshipLinked = 'relationship_linked';
    case RemarkAdded = 'remark_added';
}
