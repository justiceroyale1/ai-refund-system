<?php

namespace App\Enums\Http;

enum ApiErrorCode: string
{
    case ValidationFailed = 'VALIDATION_FAILED';
    case DemoCustomerIdRequired = 'DEMO_CUSTOMER_ID_REQUIRED';
    case DemoCustomerIdInvalid = 'DEMO_CUSTOMER_ID_INVALID';
    case DemoCustomerNotFound = 'DEMO_CUSTOMER_NOT_FOUND';
    case Unauthenticated = 'UNAUTHENTICATED';
    case Forbidden = 'FORBIDDEN';
    case ResourceNotFound = 'RESOURCE_NOT_FOUND';
    case MethodNotAllowed = 'METHOD_NOT_ALLOWED';
    case Conflict = 'CONFLICT';
    case UnprocessableEntity = 'UNPROCESSABLE_ENTITY';
    case TooManyRequests = 'TOO_MANY_REQUESTS';
    case ServiceUnavailable = 'SERVICE_UNAVAILABLE';
    case HttpError = 'HTTP_ERROR';
    case InternalServerError = 'INTERNAL_SERVER_ERROR';

    public static function fromStatus(int $status): self
    {
        return match ($status) {
            401 => self::Unauthenticated,
            403 => self::Forbidden,
            404 => self::ResourceNotFound,
            405 => self::MethodNotAllowed,
            409 => self::Conflict,
            422 => self::UnprocessableEntity,
            429 => self::TooManyRequests,
            503 => self::ServiceUnavailable,
            500 => self::InternalServerError,
            default => self::HttpError,
        };
    }

    public function defaultMessage(): string
    {
        return match ($this) {
            self::ValidationFailed => 'The given data was invalid.',
            self::DemoCustomerIdRequired => 'The X-Demo-Customer-Id header is required.',
            self::DemoCustomerIdInvalid => 'The X-Demo-Customer-Id header must contain a valid customer ID.',
            self::DemoCustomerNotFound => 'The selected demo customer could not be found.',
            self::Unauthenticated => 'Authentication is required.',
            self::Forbidden => 'You are not authorized to perform this action.',
            self::ResourceNotFound => 'The requested resource was not found.',
            self::MethodNotAllowed => 'The requested method is not allowed for this resource.',
            self::Conflict => 'The request conflicts with the current resource state.',
            self::UnprocessableEntity => 'The request could not be processed.',
            self::TooManyRequests => 'Too many requests have been made.',
            self::ServiceUnavailable => 'The service is temporarily unavailable.',
            self::HttpError => 'The request could not be completed.',
            self::InternalServerError => 'An unexpected error occurred.',
        };
    }
}
