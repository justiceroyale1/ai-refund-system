<?php

namespace App\Enums;

enum AuditEvent: string
{
    case ConversationStarted = 'conversation.started';
    case ConversationOrderIdentified = 'conversation.order_identified';
    case ConversationItemIdentified = 'conversation.item_identified';
    case ConversationDuplicateResolved = 'conversation.duplicate_resolved';
    case AiAnalysisCompleted = 'ai.analysis.completed';
    case AiAnalysisFailed = 'ai.analysis.failed';
    case PolicyEvaluated = 'policy.evaluated';
    case RefundRequestApproved = 'refund_request.approved';
    case RefundRequestDenied = 'refund_request.denied';
    case RefundRequestEscalated = 'refund_request.escalated';
    case RefundRequestReviewed = 'refund_request.reviewed';
    case RefundCreated = 'refund.created';
    case RefundProcessing = 'refund.processing';
    case RefundProcessed = 'refund.processed';
    case RefundFailed = 'refund.failed';
}
