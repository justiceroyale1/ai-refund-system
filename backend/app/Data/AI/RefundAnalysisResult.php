<?php

namespace App\Data\AI;

use App\Enums\AI\RefundIntent;
use App\Enums\RefundReason;
use App\Services\AI\Exceptions\InvalidAIResponseException;
use Illuminate\Support\Str;

final readonly class RefundAnalysisResult
{
    private function __construct(
        public RefundIntent $intent,
        public ?string $orderReference,
        public ?string $orderItemHint,
        public ?RefundReason $reason,
        public ?string $reasonDetails,
        public bool $promptInjectionDetected,
        public bool $conflictingInformation,
        public int $confidence,
        public AIAnalysisMetadata $metadata,
    ) {}

    public static function fromUntrusted(mixed $payload, AIAnalysisMetadata $metadata): self
    {
        if (! is_array($payload) || ! self::hasExactShape($payload)) {
            throw new InvalidAIResponseException;
        }

        $intent = is_string($payload['intent'])
            ? RefundIntent::tryFrom($payload['intent'])
            : null;
        $reason = match (true) {
            $payload['reason'] === null => null,
            is_string($payload['reason']) => RefundReason::tryFrom($payload['reason']),
            default => null,
        };

        if ($intent === null || ($payload['reason'] !== null && $reason === null)) {
            throw new InvalidAIResponseException;
        }

        if (! is_bool($payload['prompt_injection_detected']) || ! is_bool($payload['conflicting_information'])) {
            throw new InvalidAIResponseException;
        }

        if (! is_int($payload['confidence']) || $payload['confidence'] < 0 || $payload['confidence'] > 100) {
            throw new InvalidAIResponseException;
        }

        return new self(
            $intent,
            self::nullableBoundedString(
                $payload['order_reference'],
                RefundAnalysisConstraints::ORDER_REFERENCE_MAX_LENGTH,
            ),
            self::nullableBoundedString(
                $payload['order_item_hint'],
                RefundAnalysisConstraints::ORDER_ITEM_TEXT_MAX_LENGTH,
            ),
            $reason,
            self::nullableBoundedString(
                $payload['reason_details'],
                RefundAnalysisConstraints::REASON_DETAILS_MAX_LENGTH,
            ),
            $payload['prompt_injection_detected'],
            $payload['conflicting_information'],
            $payload['confidence'],
            $metadata,
        );
    }

    /**
     * @return array{intent: string, order_reference: ?string, order_item_hint: ?string, reason: ?string, reason_details: ?string}
     */
    public function extractedData(): array
    {
        return [
            'intent' => $this->intent->value,
            'order_reference' => $this->orderReference,
            'order_item_hint' => $this->orderItemHint,
            'reason' => $this->reason?->value,
            'reason_details' => $this->reasonDetails,
        ];
    }

    /**
     * @param  array<array-key, mixed>  $payload
     */
    private static function hasExactShape(array $payload): bool
    {
        $expectedFields = [
            'intent',
            'order_reference',
            'order_item_hint',
            'reason',
            'reason_details',
            'prompt_injection_detected',
            'conflicting_information',
            'confidence',
        ];

        $actualFields = array_keys($payload);
        sort($expectedFields);
        sort($actualFields);

        return $actualFields === $expectedFields;
    }

    private static function nullableBoundedString(mixed $value, int $maximumLength): ?string
    {
        if ($value === null) {
            return null;
        }

        if (! is_string($value) || trim($value) === '' || Str::length($value) > $maximumLength) {
            throw new InvalidAIResponseException;
        }

        return $value;
    }
}
