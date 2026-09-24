<?php

namespace App\Http\Requests\Admin;

use App\Enums\RefundDecision;
use App\Models\User;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;
use LogicException;

class ReviewRefundRequestRequest extends FormRequest
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
            'decision' => [
                'required',
                Rule::enum(RefundDecision::class)->only([
                    RefundDecision::Approved,
                    RefundDecision::Denied,
                ]),
            ],
            'review_note' => ['nullable', 'string', 'max:4000'],
        ];
    }

    public function decision(): RefundDecision
    {
        $decision = $this->enum('decision', RefundDecision::class);

        if (! $decision instanceof RefundDecision) {
            throw new LogicException('The validated review decision is unavailable.');
        }

        return $decision;
    }

    public function reviewNote(): ?string
    {
        $reviewNote = $this->validated('review_note');

        if ($reviewNote !== null && ! is_string($reviewNote)) {
            throw new LogicException('The validated review note has an invalid type.');
        }

        return $reviewNote;
    }

    public function reviewer(): User
    {
        $reviewer = $this->user();

        if (! $reviewer instanceof User) {
            throw new LogicException('An authenticated administrator is required for review.');
        }

        return $reviewer;
    }

    protected function prepareForValidation(): void
    {
        $reviewNote = $this->input('review_note');

        if (! is_string($reviewNote)) {
            return;
        }

        $reviewNote = trim($reviewNote);

        $this->merge([
            'review_note' => $reviewNote === '' ? null : $reviewNote,
        ]);
    }
}
