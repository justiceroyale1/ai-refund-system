<?php

namespace Tests\Unit\Services\Conversations;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\RefundReason;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationRequirements;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationRequirementsTest extends TestCase
{
    #[DataProvider('missingInformation')]
    public function test_returns_the_state_for_each_missing_information_rule(
        array $attributes,
        ConversationState $expectedState,
    ): void {
        $conversation = new RefundConversation([
            'customer_id' => 1,
            'state' => ConversationState::Started,
            'status' => ConversationStatus::Active,
            ...$attributes,
        ]);

        $state = (new ConversationRequirements)->nextState($conversation);

        $this->assertSame($expectedState, $state);
    }

    #[DataProvider('reasonsRequiringDetails')]
    public function test_requires_non_whitespace_details_for_every_supported_reason(RefundReason $reason): void
    {
        $conversation = new RefundConversation([
            'customer_id' => 1,
            'order_id' => 10,
            'order_item_id' => 20,
            'reason' => $reason,
            'reason_details' => " \n\t ",
            'state' => ConversationState::CollectingDetails,
            'status' => ConversationStatus::Active,
        ]);

        $missingState = (new ConversationRequirements)->nextState($conversation);
        $conversation->reason_details = 'A specific explanation supplied by the customer.';
        $completeState = (new ConversationRequirements)->nextState($conversation);

        $this->assertSame(ConversationState::CollectingDetails, $missingState);
        $this->assertSame(ConversationState::Evaluating, $completeState);
    }

    /**
     * @return array<string, array{array<string, mixed>, ConversationState}>
     */
    public static function missingInformation(): array
    {
        return [
            'order missing' => [[], ConversationState::IdentifyingOrder],
            'item missing' => [['order_id' => 10], ConversationState::IdentifyingItem],
            'reason missing' => [
                ['order_id' => 10, 'order_item_id' => 20],
                ConversationState::CollectingReason,
            ],
            'unknown reason' => [
                ['order_id' => 10, 'order_item_id' => 20, 'reason' => RefundReason::Unknown],
                ConversationState::CollectingReason,
            ],
            'resolved conversation' => [
                ['status' => ConversationStatus::Resolved, 'state' => ConversationState::Resolved],
                ConversationState::Resolved,
            ],
        ];
    }

    /**
     * @return array<string, array{RefundReason}>
     */
    public static function reasonsRequiringDetails(): array
    {
        return [
            'damaged item' => [RefundReason::DamagedItem],
            'incorrect item' => [RefundReason::IncorrectItem],
            'missing item' => [RefundReason::MissingItem],
            'changed mind' => [RefundReason::ChangedMind],
            'other' => [RefundReason::Other],
        ];
    }
}
