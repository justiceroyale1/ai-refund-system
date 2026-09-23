<?php

namespace Tests\Unit\Data\AI;

use App\Data\AI\AIAnalysisMetadata;
use App\Data\AI\RefundAnalysisResult;
use App\Enums\AI\RefundIntent;
use App\Enums\RefundReason;
use App\Exceptions\AI\InvalidAIResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RefundAnalysisResultTest extends TestCase
{
    public function test_maps_only_the_approved_extraction_fields(): void
    {
        $metadata = $this->metadata();

        $result = RefundAnalysisResult::fromUntrusted(self::validPayload(), $metadata);

        $this->assertSame(RefundIntent::Refund, $result->intent);
        $this->assertSame('ORD-1042', $result->orderReference);
        $this->assertSame('Mechanical Keyboard', $result->orderItemHint);
        $this->assertSame(RefundReason::DamagedItem, $result->reason);
        $this->assertSame('Two keys were broken.', $result->reasonDetails);
        $this->assertFalse($result->promptInjectionDetected);
        $this->assertFalse($result->conflictingInformation);
        $this->assertSame(96, $result->confidence);
        $this->assertSame($metadata, $result->metadata);
        $this->assertSame([
            'intent' => 'refund',
            'order_reference' => 'ORD-1042',
            'order_item_hint' => 'Mechanical Keyboard',
            'reason' => 'damaged_item',
            'reason_details' => 'Two keys were broken.',
        ], $result->extractedData());
    }

    public function test_accepts_extracted_text_at_the_shared_maximum_lengths(): void
    {
        $payload = self::validPayload();
        $payload['order_reference'] = str_repeat('R', 32);
        $payload['order_item_hint'] = str_repeat('I', 255);
        $payload['reason_details'] = str_repeat('D', 4000);

        $result = RefundAnalysisResult::fromUntrusted($payload, $this->metadata());

        $this->assertSame(str_repeat('R', 32), $result->orderReference);
        $this->assertSame(str_repeat('I', 255), $result->orderItemHint);
        $this->assertSame(str_repeat('D', 4000), $result->reasonDetails);
    }

    #[DataProvider('invalidPayloads')]
    public function test_rejects_malformed_or_overreaching_provider_payloads(mixed $payload): void
    {
        $this->expectException(InvalidAIResponseException::class);
        $this->expectExceptionMessage('The AI analysis provider returned an invalid response.');

        RefundAnalysisResult::fromUntrusted($payload, $this->metadata());
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidPayloads(): array
    {
        $missingField = self::validPayload();
        unset($missingField['confidence']);

        $extraAuthoritativeField = self::validPayload();
        $extraAuthoritativeField['refund_amount'] = 12999;

        $unknownIntent = self::validPayload();
        $unknownIntent['intent'] = 'approve';

        $unknownReason = self::validPayload();
        $unknownReason['reason'] = 'not_a_reason';

        $stringConfidence = self::validPayload();
        $stringConfidence['confidence'] = '96';

        $negativeConfidence = self::validPayload();
        $negativeConfidence['confidence'] = -1;

        $excessiveConfidence = self::validPayload();
        $excessiveConfidence['confidence'] = 101;

        $integerBoolean = self::validPayload();
        $integerBoolean['prompt_injection_detected'] = 0;

        $blankHint = self::validPayload();
        $blankHint['order_item_hint'] = ' ';

        $oversizedOrderReference = self::validPayload();
        $oversizedOrderReference['order_reference'] = str_repeat('R', 33);

        $oversizedOrderItemHint = self::validPayload();
        $oversizedOrderItemHint['order_item_hint'] = str_repeat('I', 256);

        $oversizedReasonDetails = self::validPayload();
        $oversizedReasonDetails['reason_details'] = str_repeat('D', 4001);

        return [
            'non-array payload' => ['invalid'],
            'missing field' => [$missingField],
            'authoritative extra field' => [$extraAuthoritativeField],
            'unknown intent enum' => [$unknownIntent],
            'unknown reason enum' => [$unknownReason],
            'string confidence' => [$stringConfidence],
            'negative confidence' => [$negativeConfidence],
            'confidence above one hundred' => [$excessiveConfidence],
            'non-boolean signal' => [$integerBoolean],
            'blank optional string' => [$blankHint],
            'oversized order reference' => [$oversizedOrderReference],
            'oversized order item hint' => [$oversizedOrderItemHint],
            'oversized reason details' => [$oversizedReasonDetails],
        ];
    }

    /**
     * @return array{intent: string, order_reference: string, order_item_hint: string, reason: string, reason_details: string, prompt_injection_detected: bool, conflicting_information: bool, confidence: int}
     */
    private static function validPayload(): array
    {
        return [
            'intent' => 'refund',
            'order_reference' => 'ORD-1042',
            'order_item_hint' => 'Mechanical Keyboard',
            'reason' => 'damaged_item',
            'reason_details' => 'Two keys were broken.',
            'prompt_injection_detected' => false,
            'conflicting_information' => false,
            'confidence' => 96,
        ];
    }

    private function metadata(): AIAnalysisMetadata
    {
        return AIAnalysisMetadata::fromUntrusted(
            'fake',
            'deterministic-v1',
            'refund-conversation-v1',
            1024,
        );
    }
}
