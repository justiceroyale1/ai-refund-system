<?php

namespace App\Data\AI;

use App\Services\AI\Exceptions\InvalidAIResponseException;
use Illuminate\Support\Str;

final readonly class AIAnalysisMetadata
{
    private function __construct(
        public string $provider,
        public string $model,
        public string $promptVersion,
        public ?string $rawResponse,
    ) {}

    public static function fromUntrusted(
        mixed $provider,
        mixed $model,
        mixed $promptVersion,
        int $maximumRawResponseBytes,
        mixed $rawResponse = null,
    ): self {
        if ($maximumRawResponseBytes < 1) {
            throw new InvalidAIResponseException;
        }

        if ($rawResponse !== null && (! is_string($rawResponse) || strlen($rawResponse) > $maximumRawResponseBytes)) {
            throw new InvalidAIResponseException;
        }

        return new self(
            self::boundedString($provider, 64),
            self::boundedString($model, 128),
            self::boundedString($promptVersion, 64),
            $rawResponse,
        );
    }

    private static function boundedString(mixed $value, int $maximumLength): string
    {
        if (! is_string($value) || trim($value) === '' || Str::length($value) > $maximumLength) {
            throw new InvalidAIResponseException;
        }

        return $value;
    }
}
