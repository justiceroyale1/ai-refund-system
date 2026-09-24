<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundDecision;
use App\Enums\RefundStatus;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class ListRefundRequestsRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'decision' => ['nullable', Rule::enum(RefundDecision::class)],
            'execution_status' => ['nullable', Rule::enum(RefundStatus::class)],
            'search' => ['nullable', 'string', 'max:255'],
            'page' => ['sometimes', 'integer', 'min:1'],
        ];
    }

    protected function prepareForValidation(): void
    {
        $search = $this->input('search');

        if (! is_string($search)) {
            return;
        }

        $search = trim($search);

        $this->merge([
            'search' => $search === '' ? null : $search,
        ]);
    }
}
