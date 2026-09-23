<?php

namespace App\Actions\Conversations;

use App\Contracts\AI\RefundConversationAI;
use App\Data\AI\ConversationContext;
use App\Data\AI\ConversationContextMessage;
use App\Data\Conversations\ConversationSelectionResult;
use App\Enums\AuditActorType;
use App\Enums\AuditEvent;
use App\Exceptions\AI\AIProviderException;
use App\Exceptions\AI\AIProviderUnavailableException;
use App\Exceptions\AI\InvalidAIResponseException;
use App\Models\AiAnalysis;
use App\Models\ConversationMessage;
use App\Models\RefundConversation;
use Illuminate\Support\Facades\DB;

final class ProcessConversationAnalysis
{
    public function __construct(
        private readonly RefundConversationAI $ai,
        private readonly ApplyConversationAnalysis $applyConversationAnalysis,
    ) {}

    public function handle(
        RefundConversation $conversation,
        ConversationMessage $customerMessage,
    ): ConversationSelectionResult {
        $result = $this->ai->analyze(
            $this->context($conversation, $customerMessage),
            $customerMessage->content,
        );

        $analysis = $conversation->aiAnalyses()->create([
            'conversation_message_id' => $customerMessage->id,
            'provider' => $result->metadata->provider,
            'model' => $result->metadata->model,
            'prompt_version' => $result->metadata->promptVersion,
            'confidence' => $result->confidence,
            'prompt_injection_detected' => $result->promptInjectionDetected,
            'conflicting_information' => $result->conflictingInformation,
            'extracted_data' => $result->extractedData(),
            'raw_response' => $result->metadata->rawResponse,
        ]);

        $this->recordCompletedAudit($conversation, $customerMessage, $analysis);

        return $this->applyConversationAnalysis->handle($conversation, $result);
    }

    public function recordFailure(
        RefundConversation $conversation,
        string $clientMessageId,
        AIProviderException $exception,
    ): void {
        DB::transaction(function () use ($conversation, $clientMessageId, $exception): void {
            $lockedConversation = RefundConversation::query()
                ->whereKey($conversation->id)
                ->lockForUpdate()
                ->firstOrFail();

            $lockedConversation->auditLogs()->create([
                'actor_type' => AuditActorType::System,
                'actor_id' => null,
                'event' => AuditEvent::AiAnalysisFailed->value,
                'metadata' => [
                    'client_message_id' => $clientMessageId,
                    'failure_type' => $this->failureType($exception),
                ],
            ]);
        });
    }

    private function context(
        RefundConversation $conversation,
        ConversationMessage $customerMessage,
    ): ConversationContext {
        $conversation->loadMissing(['order:id,reference', 'orderItem:id,name']);
        $messages = $conversation->messages()
            ->whereKeyNot($customerMessage->id)
            ->get(['id', 'refund_conversation_id', 'sender', 'content'])
            ->map(
                static fn (ConversationMessage $message): ConversationContextMessage => new ConversationContextMessage(
                    $message->sender,
                    $message->content,
                ),
            )
            ->values()
            ->all();

        return new ConversationContext(
            state: $conversation->state,
            orderReference: $conversation->order?->reference,
            orderItemName: $conversation->orderItem?->name,
            reason: $conversation->reason,
            reasonDetails: $conversation->reason_details,
            messages: $messages,
        );
    }

    private function recordCompletedAudit(
        RefundConversation $conversation,
        ConversationMessage $customerMessage,
        AiAnalysis $analysis,
    ): void {
        $conversation->auditLogs()->create([
            'actor_type' => AuditActorType::System,
            'actor_id' => null,
            'event' => AuditEvent::AiAnalysisCompleted->value,
            'metadata' => [
                'ai_analysis_id' => $analysis->id,
                'conversation_message_id' => $customerMessage->id,
                'provider' => $analysis->provider,
                'model' => $analysis->model,
                'confidence' => $analysis->confidence,
                'prompt_injection_detected' => $analysis->prompt_injection_detected,
                'conflicting_information' => $analysis->conflicting_information,
            ],
        ]);
    }

    private function failureType(AIProviderException $exception): string
    {
        return match (true) {
            $exception instanceof InvalidAIResponseException => 'invalid_response',
            $exception instanceof AIProviderUnavailableException => 'provider_unavailable',
            default => 'provider_configuration',
        };
    }
}
