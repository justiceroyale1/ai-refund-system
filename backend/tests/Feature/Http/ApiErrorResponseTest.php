<?php

namespace Tests\Feature\Http;

use App\Enums\ConversationState;
use App\Services\Conversations\ConversationWorkflowException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use RuntimeException;
use Symfony\Component\HttpKernel\Exception\ConflictHttpException;
use Symfony\Component\HttpKernel\Exception\ServiceUnavailableHttpException;
use Tests\TestCase;

class ApiErrorResponseTest extends TestCase
{
    public function test_returns_422_error_contract_when_validation_fails(): void
    {
        Route::post('/api/testing/validation-error', function (Request $request): array {
            return $request->validate([
                'name' => ['required', 'string'],
            ]);
        });

        $response = $this->postJson('/api/testing/validation-error');

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'VALIDATION_FAILED',
                    'message' => 'The given data was invalid.',
                    'details' => [
                        'errors' => [
                            'name' => ['The name field is required.'],
                        ],
                    ],
                ],
            ]);
    }

    public function test_returns_401_error_contract_when_authentication_is_required(): void
    {
        Route::get('/api/testing/authentication-error', function (): never {
            throw new AuthenticationException;
        });

        $response = $this->getJson('/api/testing/authentication-error');

        $response
            ->assertUnauthorized()
            ->assertExactJson([
                'error' => [
                    'code' => 'UNAUTHENTICATED',
                    'message' => 'Authentication is required.',
                    'details' => [],
                ],
            ])
            ->assertSee('"details":{}', false);
    }

    public function test_returns_403_error_contract_when_authorization_fails(): void
    {
        Route::get('/api/testing/authorization-error', function (): never {
            throw new AuthorizationException;
        });

        $response = $this->getJson('/api/testing/authorization-error');

        $response
            ->assertForbidden()
            ->assertExactJson([
                'error' => [
                    'code' => 'FORBIDDEN',
                    'message' => 'You are not authorized to perform this action.',
                    'details' => [],
                ],
            ]);
    }

    public function test_returns_404_error_contract_for_a_missing_api_route(): void
    {
        $response = $this->get('/api/testing/missing-resource');

        $response
            ->assertNotFound()
            ->assertHeader('Content-Type', 'application/json')
            ->assertExactJson([
                'error' => [
                    'code' => 'RESOURCE_NOT_FOUND',
                    'message' => 'The requested resource was not found.',
                    'details' => [],
                ],
            ]);
    }

    public function test_returns_409_error_contract_for_an_invalid_transition(): void
    {
        Route::post('/api/testing/conflict-error', function (): never {
            throw new ConflictHttpException;
        });

        $response = $this->postJson('/api/testing/conflict-error');

        $response
            ->assertConflict()
            ->assertExactJson([
                'error' => [
                    'code' => 'CONFLICT',
                    'message' => 'The request conflicts with the current resource state.',
                    'details' => [],
                ],
            ]);
    }

    public function test_returns_domain_error_contract_for_an_invalid_conversation_transition(): void
    {
        Route::post('/api/testing/conversation-transition-error', function (): never {
            throw ConversationWorkflowException::invalidTransition(
                ConversationState::IdentifyingOrder,
                ConversationState::Evaluating,
            );
        });

        $response = $this->postJson('/api/testing/conversation-transition-error');

        $response
            ->assertConflict()
            ->assertExactJson([
                'error' => [
                    'code' => 'INVALID_CONVERSATION_TRANSITION',
                    'message' => 'The refund conversation cannot make that transition.',
                    'details' => [
                        'from' => 'identifying_order',
                        'to' => 'evaluating',
                    ],
                ],
            ]);
    }

    public function test_returns_domain_error_contract_for_an_invalid_conversation_selection(): void
    {
        Route::post('/api/testing/conversation-selection-error', function (): never {
            throw ConversationWorkflowException::invalidSelection(
                'The selected order is not available for this refund conversation.',
                ['field' => 'selection.value'],
            );
        });

        $response = $this->postJson('/api/testing/conversation-selection-error');

        $response
            ->assertUnprocessable()
            ->assertExactJson([
                'error' => [
                    'code' => 'INVALID_CONVERSATION_SELECTION',
                    'message' => 'The selected order is not available for this refund conversation.',
                    'details' => [
                        'field' => 'selection.value',
                    ],
                ],
            ]);
    }

    public function test_returns_503_error_contract_when_a_service_is_unavailable(): void
    {
        Route::post('/api/testing/service-unavailable-error', function (): never {
            throw new ServiceUnavailableHttpException;
        });

        $response = $this->postJson('/api/testing/service-unavailable-error');

        $response
            ->assertServiceUnavailable()
            ->assertExactJson([
                'error' => [
                    'code' => 'SERVICE_UNAVAILABLE',
                    'message' => 'The service is temporarily unavailable.',
                    'details' => [],
                ],
            ]);
    }

    public function test_returns_safe_500_error_contract_without_exception_details(): void
    {
        Route::get('/api/testing/unexpected-error', function (): never {
            throw new RuntimeException('Sensitive implementation detail.');
        });

        $response = $this->getJson('/api/testing/unexpected-error');

        $response
            ->assertInternalServerError()
            ->assertExactJson([
                'error' => [
                    'code' => 'INTERNAL_SERVER_ERROR',
                    'message' => 'An unexpected error occurred.',
                    'details' => [],
                ],
            ])
            ->assertJsonMissing(['message' => 'Sensitive implementation detail.']);
    }
}
