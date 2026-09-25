<?php

namespace Tests\Feature\Providers;

use App\Contracts\AI\RefundConversationAI;
use App\Data\AI\ConversationContext;
use App\Enums\ConversationState;
use App\Exceptions\AI\UnsupportedAIProviderException;
use App\Services\AI\FakeRefundConversationAI;
use App\Services\AI\GeminiRefundConversationAI;
use App\Services\AI\RefundConversationPrompt;
use PHPUnit\Framework\Attributes\DataProvider;
use stdClass;
use Tests\TestCase;

class AIServiceProviderTest extends TestCase
{
    public function test_resolves_the_configured_fake_as_a_singleton(): void
    {
        config()->set('ai.default', 'fake');

        $first = $this->app->make(RefundConversationAI::class);
        $second = $this->app->make(RefundConversationAI::class);

        $this->assertInstanceOf(FakeRefundConversationAI::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_exposes_stable_prompt_version_metadata(): void
    {
        $this->assertSame(RefundConversationPrompt::VERSION, config('ai.prompt_version'));
        $this->assertSame('refund-conversation-v1', config('ai.prompt_version'));
    }

    public function test_exposes_the_default_response_limit_configuration(): void
    {
        $this->assertSame(32768, config('ai.max_response_bytes'));
    }

    public function test_exposes_safe_gemini_transport_defaults(): void
    {
        $this->assertSame(3, config('ai.providers.gemini.connection_timeout_seconds'));
        $this->assertSame(60, config('ai.providers.gemini.timeout_seconds'));
        $this->assertSame(3, config('ai.providers.gemini.maximum_attempts'));
        $this->assertSame(500, config('ai.providers.gemini.retry_base_delay_milliseconds'));
        $this->assertSame('low', config('ai.providers.gemini.thinking_level'));
    }

    public function test_injects_a_custom_positive_response_limit_into_the_fake(): void
    {
        config()->set('ai.default', 'fake');
        config()->set('ai.max_response_bytes', 1);

        $provider = $this->app->make(RefundConversationAI::class);
        $result = $provider->analyze(
            new ConversationContext(ConversationState::Started),
            'I need a refund.',
        );

        $this->assertInstanceOf(FakeRefundConversationAI::class, $provider);
        $this->assertSame(1, $provider->maximumRawResponseBytes());
        $this->assertSame('fake', $result->metadata->provider);
    }

    public function test_resolves_the_configured_gemini_provider_as_a_singleton(): void
    {
        config()->set('ai.default', 'gemini');
        config()->set('ai.providers.gemini.api_key', 'test-gemini-key');
        config()->set('ai.providers.gemini.model', 'gemini-3.8-flash');

        $first = $this->app->make(RefundConversationAI::class);
        $second = $this->app->make(RefundConversationAI::class);

        $this->assertInstanceOf(GeminiRefundConversationAI::class, $first);
        $this->assertSame($first, $second);
    }

    public function test_rejects_gemini_configuration_without_an_api_key(): void
    {
        config()->set('ai.default', 'gemini');
        config()->set('ai.providers.gemini.api_key', null);

        $this->expectException(UnsupportedAIProviderException::class);
        $this->expectExceptionMessage('The configured AI analysis provider is not available.');

        $this->app->make(RefundConversationAI::class);
    }

    #[DataProvider('invalidResponseLimits')]
    public function test_rejects_an_invalid_response_limit_configuration(mixed $maximumRawResponseBytes): void
    {
        config()->set('ai.default', 'fake');
        config()->set('ai.max_response_bytes', $maximumRawResponseBytes);

        $this->expectException(UnsupportedAIProviderException::class);

        $this->app->make(RefundConversationAI::class);
    }

    /**
     * @return array<string, array{mixed}>
     */
    public static function invalidResponseLimits(): array
    {
        return [
            'null' => [null],
            'numeric string' => ['1024'],
            'zero' => [0],
            'negative integer' => [-1],
        ];
    }

    #[DataProvider('invalidGeminiTransportConfigurations')]
    public function test_rejects_invalid_gemini_transport_configuration(string $key, mixed $value): void
    {
        config()->set('ai.default', 'gemini');
        config()->set('ai.providers.gemini.api_key', 'test-gemini-key');
        config()->set("ai.providers.gemini.{$key}", $value);

        $this->expectException(UnsupportedAIProviderException::class);

        $this->app->make(RefundConversationAI::class);
    }

    /**
     * @return array<string, array{string, mixed}>
     */
    public static function invalidGeminiTransportConfigurations(): array
    {
        return [
            'zero connection timeout' => ['connection_timeout_seconds', 0],
            'zero response timeout' => ['timeout_seconds', 0],
            'zero maximum attempts' => ['maximum_attempts', 0],
            'excessive maximum attempts' => ['maximum_attempts', 4],
            'negative retry delay' => ['retry_base_delay_milliseconds', -1],
            'excessive retry delay' => ['retry_base_delay_milliseconds', 5001],
            'unsupported thinking level' => ['thinking_level', 'minimal'],
        ];
    }

    public function test_rejects_an_unknown_configured_provider_without_exposing_its_name(): void
    {
        config()->set('ai.default', 'sensitive-provider-name');

        try {
            $this->app->make(RefundConversationAI::class);
            $this->fail('An unknown AI provider was resolved.');
        } catch (UnsupportedAIProviderException $exception) {
            $this->assertSame('The configured AI analysis provider is not available.', $exception->getMessage());
            $this->assertStringNotContainsString('sensitive-provider-name', $exception->getMessage());
        }
    }

    public function test_rejects_a_driver_that_does_not_implement_the_contract(): void
    {
        config()->set('ai.default', 'invalid');
        config()->set('ai.providers.invalid.driver', stdClass::class);

        $this->expectException(UnsupportedAIProviderException::class);

        $this->app->make(RefundConversationAI::class);
    }
}
