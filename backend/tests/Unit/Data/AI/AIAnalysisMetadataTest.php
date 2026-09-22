<?php

namespace Tests\Unit\Data\AI;

use App\Data\AI\AIAnalysisMetadata;
use App\Services\AI\Exceptions\InvalidAIResponseException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class AIAnalysisMetadataTest extends TestCase
{
    public function test_maps_valid_provider_metadata(): void
    {
        $metadata = AIAnalysisMetadata::fromUntrusted(
            'fake',
            'deterministic-v1',
            'refund-conversation-v1',
            1024,
            rawResponse: '{"confidence":96}',
        );

        $this->assertSame('fake', $metadata->provider);
        $this->assertSame('deterministic-v1', $metadata->model);
        $this->assertSame('refund-conversation-v1', $metadata->promptVersion);
        $this->assertSame('{"confidence":96}', $metadata->rawResponse);
    }

    public function test_accepts_a_raw_response_at_the_configured_byte_limit(): void
    {
        $metadata = AIAnalysisMetadata::fromUntrusted(
            'fake',
            'deterministic-v1',
            'refund-conversation-v1',
            4,
            rawResponse: '1234',
        );

        $this->assertSame('1234', $metadata->rawResponse);
    }

    #[DataProvider('invalidMetadata')]
    public function test_rejects_malformed_provider_metadata(
        mixed $provider,
        mixed $model,
        mixed $promptVersion,
        int $maximumRawResponseBytes,
        mixed $rawResponse,
    ): void {
        $this->expectException(InvalidAIResponseException::class);
        $this->expectExceptionMessage('The AI analysis provider returned an invalid response.');

        AIAnalysisMetadata::fromUntrusted(
            $provider,
            $model,
            $promptVersion,
            $maximumRawResponseBytes,
            $rawResponse,
        );
    }

    /**
     * @return array<string, array{mixed, mixed, mixed, int, mixed}>
     */
    public static function invalidMetadata(): array
    {
        return [
            'non-string provider' => [1, 'model', 'version', 1024, null],
            'blank provider' => ['', 'model', 'version', 1024, null],
            'provider too long' => [str_repeat('p', 65), 'model', 'version', 1024, null],
            'non-string model' => ['provider', [], 'version', 1024, null],
            'model too long' => ['provider', str_repeat('m', 129), 'version', 1024, null],
            'non-string prompt version' => ['provider', 'model', false, 1024, null],
            'prompt version too long' => ['provider', 'model', str_repeat('v', 65), 1024, null],
            'zero response limit' => ['provider', 'model', 'version', 0, null],
            'negative response limit' => ['provider', 'model', 'version', -1, null],
            'non-string raw response' => ['provider', 'model', 'version', 1024, ['response']],
            'raw response one byte over limit' => ['provider', 'model', 'version', 4, '12345'],
        ];
    }
}
