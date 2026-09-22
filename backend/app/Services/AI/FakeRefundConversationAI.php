<?php

namespace App\Services\AI;

use App\Contracts\AI\RefundConversationAI;
use App\Data\AI\AIAnalysisMetadata;
use App\Data\AI\ConversationContext;
use App\Data\AI\RefundAnalysisResult;
use App\Enums\AI\FakeRefundConversationScenario;
use App\Enums\AI\RefundIntent;
use App\Enums\RefundReason;
use App\Services\AI\Exceptions\AIProviderException;
use App\Services\AI\Exceptions\AIProviderUnavailableException;
use App\Services\AI\Exceptions\InvalidAIResponseException;

final class FakeRefundConversationAI implements RefundConversationAI
{
    /**
     * @var list<array{context: ConversationContext, message: string}>
     */
    private array $analyses = [];

    private ?RefundAnalysisResult $result = null;

    private ?AIProviderException $exception = null;

    public function __construct(
        private readonly int $maximumRawResponseBytes,
        private FakeRefundConversationScenario $scenario = FakeRefundConversationScenario::DamagedItem,
    ) {
        if ($maximumRawResponseBytes < 1) {
            throw new InvalidAIResponseException;
        }
    }

    public function useScenario(FakeRefundConversationScenario $scenario): self
    {
        $this->scenario = $scenario;
        $this->result = null;
        $this->exception = null;

        return $this;
    }

    public function respondWith(RefundAnalysisResult $result): self
    {
        $this->result = $result;
        $this->exception = null;

        return $this;
    }

    public function failWith(AIProviderException $exception): self
    {
        $this->exception = $exception;
        $this->result = null;

        return $this;
    }

    public function analyze(ConversationContext $context, string $message): RefundAnalysisResult
    {
        $this->analyses[] = ['context' => $context, 'message' => $message];

        if ($this->exception !== null) {
            throw $this->exception;
        }

        if ($this->result !== null) {
            return $this->result;
        }

        if ($this->scenario === FakeRefundConversationScenario::Unavailable) {
            throw new AIProviderUnavailableException;
        }

        return RefundAnalysisResult::fromUntrusted(
            $this->payloadFor($this->scenario),
            AIAnalysisMetadata::fromUntrusted(
                'fake',
                'deterministic-v1',
                RefundConversationPrompt::VERSION,
                maximumRawResponseBytes: $this->maximumRawResponseBytes,
            ),
        );
    }

    public function analysisCount(): int
    {
        return count($this->analyses);
    }

    public function maximumRawResponseBytes(): int
    {
        return $this->maximumRawResponseBytes;
    }

    public function lastContext(): ?ConversationContext
    {
        $analysis = $this->analyses[array_key_last($this->analyses)] ?? null;

        return $analysis['context'] ?? null;
    }

    public function lastMessage(): ?string
    {
        $analysis = $this->analyses[array_key_last($this->analyses)] ?? null;

        return $analysis['message'] ?? null;
    }

    /**
     * @return array{intent: string, order_reference: ?string, order_item_hint: ?string, reason: ?string, reason_details: ?string, prompt_injection_detected: bool, conflicting_information: bool, confidence: int}
     */
    private function payloadFor(FakeRefundConversationScenario $scenario): array
    {
        return match ($scenario) {
            FakeRefundConversationScenario::DamagedItem => $this->payload(
                orderReference: 'ORD-1042',
                orderItemHint: 'Mechanical Keyboard',
                reason: RefundReason::DamagedItem,
                reasonDetails: 'Two keys were broken when the package was opened.',
                confidence: 96,
            ),
            FakeRefundConversationScenario::IncorrectItem => $this->payload(
                orderReference: 'ORD-1042',
                orderItemHint: 'Mechanical Keyboard',
                reason: RefundReason::IncorrectItem,
                reasonDetails: 'A mouse was delivered instead of the keyboard.',
                confidence: 94,
            ),
            FakeRefundConversationScenario::MissingInformation => $this->payload(
                confidence: 62,
            ),
            FakeRefundConversationScenario::PromptInjection => $this->payload(
                promptInjectionDetected: true,
                confidence: 99,
            ),
            FakeRefundConversationScenario::ConflictingInformation => $this->payload(
                conflictingInformation: true,
                confidence: 82,
            ),
            FakeRefundConversationScenario::Unavailable => throw new AIProviderUnavailableException,
        };
    }

    /**
     * @return array{intent: string, order_reference: ?string, order_item_hint: ?string, reason: ?string, reason_details: ?string, prompt_injection_detected: bool, conflicting_information: bool, confidence: int}
     */
    private function payload(
        RefundIntent $intent = RefundIntent::Refund,
        ?string $orderReference = null,
        ?string $orderItemHint = null,
        ?RefundReason $reason = null,
        ?string $reasonDetails = null,
        bool $promptInjectionDetected = false,
        bool $conflictingInformation = false,
        int $confidence = 75,
    ): array {
        return [
            'intent' => $intent->value,
            'order_reference' => $orderReference,
            'order_item_hint' => $orderItemHint,
            'reason' => $reason?->value,
            'reason_details' => $reasonDetails,
            'prompt_injection_detected' => $promptInjectionDetected,
            'conflicting_information' => $conflictingInformation,
            'confidence' => $confidence,
        ];
    }
}
