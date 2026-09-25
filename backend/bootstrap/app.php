<?php

use App\Enums\Http\ApiErrorCode;
use App\Exceptions\AI\AIProviderException;
use App\Exceptions\Conversations\ConversationWorkflowException;
use App\Exceptions\Refunds\RefundReviewException;
use App\Http\Middleware\ResolveBroadcastIdentity;
use App\Http\Middleware\ResolveDemoCustomer;
use App\Http\Responses\ApiErrorResponse;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withBroadcasting(
        __DIR__.'/../routes/channels.php',
        [
            'prefix' => 'api',
            'middleware' => ['api', ResolveBroadcastIdentity::class],
        ],
    )
    ->withMiddleware(function (Middleware $middleware): void {
        $middleware->statefulApi();

        $middleware->alias([
            'demo.customer' => ResolveDemoCustomer::class,
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request, Throwable $exception): bool => ApiErrorResponse::shouldRender($request),
        );

        $exceptions->render(function (ValidationException $exception, Request $request): ?JsonResponse {
            if (! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::ValidationFailed,
                ApiErrorCode::ValidationFailed->defaultMessage(),
                ['errors' => $exception->errors()],
                $exception->status,
            );
        });

        $exceptions->render(function (AuthenticationException $exception, Request $request): ?JsonResponse {
            if (! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::Unauthenticated,
                ApiErrorCode::Unauthenticated->defaultMessage(),
                status: 401,
            );
        });

        $exceptions->render(function (ConversationWorkflowException $exception, Request $request): ?JsonResponse {
            if (! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->details(),
                $exception->status(),
            );
        });

        $exceptions->render(function (RefundReviewException $exception, Request $request): ?JsonResponse {
            if (! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                $exception->errorCode(),
                $exception->getMessage(),
                $exception->details(),
                $exception->status(),
            );
        });

        $exceptions->render(function (AIProviderException $exception, Request $request): ?JsonResponse {
            if (! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::ServiceUnavailable,
                $exception->getMessage(),
                status: 503,
            );
        });

        $exceptions->render(function (HttpExceptionInterface $exception, Request $request): ?JsonResponse {
            if (! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            $code = ApiErrorCode::fromStatus($exception->getStatusCode());

            return ApiErrorResponse::make(
                $code,
                $code->defaultMessage(),
                status: $exception->getStatusCode(),
                headers: $exception->getHeaders(),
            );
        });

        $exceptions->render(function (Throwable $exception, Request $request): ?JsonResponse {
            if ($exception instanceof HttpResponseException || ! ApiErrorResponse::shouldRender($request)) {
                return null;
            }

            return ApiErrorResponse::make(
                ApiErrorCode::InternalServerError,
                ApiErrorCode::InternalServerError->defaultMessage(),
            );
        });
    })->create();
