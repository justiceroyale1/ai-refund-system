<?php

namespace App\Providers;

use App\Contracts\AI\RefundConversationAI;
use App\Exceptions\AI\UnsupportedAIProviderException;
use App\Services\AI\FakeRefundConversationAI;
use App\Services\AI\GeminiRefundConversationAI;
use Illuminate\Contracts\Config\Repository;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\ServiceProvider;

class AIServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->singleton(FakeRefundConversationAI::class, function (Application $app): FakeRefundConversationAI {
            $maximumRawResponseBytes = $app->make(Repository::class)->get('ai.max_response_bytes');

            if (! is_int($maximumRawResponseBytes) || $maximumRawResponseBytes < 1) {
                throw new UnsupportedAIProviderException;
            }

            return new FakeRefundConversationAI($maximumRawResponseBytes);
        });

        $this->app->singleton(GeminiRefundConversationAI::class, function (Application $app): GeminiRefundConversationAI {
            $config = $app->make(Repository::class);
            $apiKey = $config->get('ai.providers.gemini.api_key');
            $model = $config->get('ai.providers.gemini.model');
            $maximumRawResponseBytes = $config->get('ai.max_response_bytes');
            $connectionTimeoutSeconds = $config->get('ai.providers.gemini.connection_timeout_seconds');
            $timeoutSeconds = $config->get('ai.providers.gemini.timeout_seconds');
            $maximumAttempts = $config->get('ai.providers.gemini.maximum_attempts');
            $retryBaseDelayMilliseconds = $config->get('ai.providers.gemini.retry_base_delay_milliseconds');
            $thinkingLevel = $config->get('ai.providers.gemini.thinking_level');

            if (
                ! is_string($apiKey)
                || ! is_string($model)
                || ! is_int($maximumRawResponseBytes)
                || ! is_int($connectionTimeoutSeconds)
                || ! is_int($timeoutSeconds)
                || ! is_int($maximumAttempts)
                || ! is_int($retryBaseDelayMilliseconds)
                || ! is_string($thinkingLevel)
            ) {
                throw new UnsupportedAIProviderException;
            }

            return new GeminiRefundConversationAI(
                $apiKey,
                $model,
                $maximumRawResponseBytes,
                $connectionTimeoutSeconds,
                $timeoutSeconds,
                $maximumAttempts,
                $retryBaseDelayMilliseconds,
                $thinkingLevel,
            );
        });

        $this->app->singleton(RefundConversationAI::class, function (Application $app): RefundConversationAI {
            $config = $app->make(Repository::class);
            $provider = $config->get('ai.default');

            if (! is_string($provider) || trim($provider) === '') {
                throw new UnsupportedAIProviderException;
            }

            $driver = $config->get("ai.providers.{$provider}.driver");

            if (! is_string($driver) || ! is_a($driver, RefundConversationAI::class, true)) {
                throw new UnsupportedAIProviderException;
            }

            $implementation = $app->make($driver);

            if (! $implementation instanceof RefundConversationAI) {
                throw new UnsupportedAIProviderException;
            }

            return $implementation;
        });
    }
}
