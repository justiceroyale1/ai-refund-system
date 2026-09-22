<?php

namespace App\Http\Requests;

use App\Data\Conversations\ConversationSelection;
use App\Enums\ConversationSelectionType;
use App\Http\CurrentCustomer;
use App\Models\Customer;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreConversationMessageRequest extends FormRequest
{
    /**
     * Determine if the user is authorized to make this request.
     */
    public function authorize(): bool
    {
        return $this->user(CurrentCustomer::GUARD) instanceof Customer;
    }

    /**
     * Get the validation rules that apply to the request.
     *
     * @return array<string, ValidationRule|array<mixed>|string>
     */
    public function rules(): array
    {
        return [
            'client_message_id' => ['required', 'uuid'],
            'content' => ['required', 'string', 'max:5000'],
            'selection' => ['sometimes', 'array:type,value'],
            'selection.type' => [
                'required_with:selection',
                'string',
                Rule::enum(ConversationSelectionType::class),
            ],
            'selection.value' => ['required_with:selection'],
        ];
    }

    public function clientMessageId(): string
    {
        return $this->string('client_message_id')->toString();
    }

    public function content(): string
    {
        return $this->string('content')->toString();
    }

    public function selection(): ?ConversationSelection
    {
        $selection = $this->validated('selection');

        if (! is_array($selection)) {
            return null;
        }

        return ConversationSelection::fromUntrusted(
            $selection['type'] ?? null,
            $selection['value'] ?? null,
        );
    }
}
