<?php

namespace App\Contracts\AI;

use App\Data\AI\ConversationContext;
use App\Data\AI\RefundAnalysisResult;

interface RefundConversationAI
{
    public function analyze(ConversationContext $context, string $message): RefundAnalysisResult;
}
