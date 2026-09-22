<?php

namespace App\Http\Middleware;

use App\Enums\Http\ApiErrorCode;
use App\Http\CurrentCustomer;
use App\Http\Responses\ApiErrorResponse;
use App\Models\Customer;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

final class ResolveDemoCustomer
{
    public const string HEADER = 'X-Demo-Customer-Id';

    public function __construct(private readonly CurrentCustomer $currentCustomer) {}

    public function handle(Request $request, Closure $next): Response
    {
        $header = $request->header(self::HEADER);

        if ($header === null || $header === '') {
            return $this->authenticationError(ApiErrorCode::DemoCustomerIdRequired);
        }

        if (! preg_match('/^[1-9][0-9]*$/', $header)) {
            return $this->authenticationError(ApiErrorCode::DemoCustomerIdInvalid);
        }

        $customerId = filter_var(
            $header,
            FILTER_VALIDATE_INT,
            ['options' => ['min_range' => 1]],
        );

        if ($customerId === false) {
            return $this->authenticationError(ApiErrorCode::DemoCustomerIdInvalid);
        }

        $customer = Customer::query()->find($customerId);

        if ($customer === null) {
            return $this->authenticationError(ApiErrorCode::DemoCustomerNotFound);
        }

        $this->currentCustomer->set($customer);

        $previousResolver = $request->getUserResolver();
        $request->setUserResolver(
            static fn (?string $guard = null): mixed => $guard === null || $guard === CurrentCustomer::GUARD
                ? $customer
                : $previousResolver($guard),
        );

        return $next($request);
    }

    private function authenticationError(ApiErrorCode $code): Response
    {
        return ApiErrorResponse::make(
            $code,
            $code->defaultMessage(),
            status: Response::HTTP_UNAUTHORIZED,
        );
    }
}
