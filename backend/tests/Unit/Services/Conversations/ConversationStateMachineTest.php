<?php

namespace Tests\Unit\Services\Conversations;

use App\Enums\ConversationState;
use App\Enums\ConversationStatus;
use App\Enums\Http\ApiErrorCode;
use App\Exceptions\Conversations\ConversationWorkflowException;
use App\Models\RefundConversation;
use App\Services\Conversations\ConversationStateMachine;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class ConversationStateMachineTest extends TestCase
{
    #[DataProvider('transitionMatrix')]
    public function test_transition_matrix_accepts_only_documented_edges(
        ConversationState $from,
        ConversationState $to,
        bool $expected,
    ): void {
        $this->assertSame($expected, (new ConversationStateMachine)->canTransition($from, $to));
    }

    public function test_invalid_transition_returns_a_structured_conflict_without_changing_state(): void
    {
        $conversation = $this->conversation(ConversationState::IdentifyingOrder);
        $stateMachine = new ConversationStateMachine;

        try {
            $stateMachine->transition($conversation, ConversationState::Evaluating);
            $this->fail('An invalid transition was accepted.');
        } catch (ConversationWorkflowException $exception) {
            $this->assertSame(ApiErrorCode::InvalidConversationTransition, $exception->errorCode());
            $this->assertSame(409, $exception->status());
            $this->assertSame([
                'from' => ConversationState::IdentifyingOrder->value,
                'to' => ConversationState::Evaluating->value,
            ], $exception->details());
            $this->assertSame(ConversationState::IdentifyingOrder, $conversation->state);
            $this->assertSame(ConversationStatus::Active, $conversation->status);
        }
    }

    public function test_advance_to_walks_documented_edges_for_a_complete_analysis(): void
    {
        $conversation = $this->conversation(ConversationState::Started);

        (new ConversationStateMachine)->advanceTo($conversation, ConversationState::Evaluating);

        $this->assertSame(ConversationState::Evaluating, $conversation->state);
        $this->assertSame(ConversationStatus::Active, $conversation->status);
    }

    public function test_advance_to_uses_the_details_branch_when_details_are_required(): void
    {
        $conversation = $this->conversation(ConversationState::Started);

        (new ConversationStateMachine)->advanceTo($conversation, ConversationState::CollectingDetails);

        $this->assertSame(ConversationState::CollectingDetails, $conversation->state);
        $this->assertSame(ConversationStatus::Active, $conversation->status);
    }

    public function test_advance_to_is_a_no_op_when_already_at_the_target_state(): void
    {
        $conversation = $this->conversation(ConversationState::CollectingReason);

        (new ConversationStateMachine)->advanceTo($conversation, ConversationState::CollectingReason);

        $this->assertSame(ConversationState::CollectingReason, $conversation->state);
        $this->assertSame(ConversationStatus::Active, $conversation->status);
    }

    public function test_advance_to_rejects_a_backward_transition_without_changing_state(): void
    {
        $conversation = $this->conversation(ConversationState::CollectingDetails);

        try {
            (new ConversationStateMachine)->advanceTo($conversation, ConversationState::IdentifyingItem);
            $this->fail('A backward transition was accepted.');
        } catch (ConversationWorkflowException $exception) {
            $this->assertSame(ApiErrorCode::InvalidConversationTransition, $exception->errorCode());
            $this->assertSame(ConversationState::CollectingDetails, $conversation->state);
            $this->assertSame(ConversationStatus::Active, $conversation->status);
        }
    }

    public function test_advance_to_cannot_resolve_an_active_conversation(): void
    {
        $conversation = $this->conversation(ConversationState::Evaluating);

        try {
            (new ConversationStateMachine)->advanceTo($conversation, ConversationState::Resolved);
            $this->fail('Automatic advancement resolved an active conversation.');
        } catch (ConversationWorkflowException $exception) {
            $this->assertSame(ApiErrorCode::InvalidConversationTransition, $exception->errorCode());
            $this->assertSame([
                'from' => ConversationState::Evaluating->value,
                'to' => ConversationState::Resolved->value,
            ], $exception->details());
            $this->assertSame(ConversationState::Evaluating, $conversation->state);
            $this->assertSame(ConversationStatus::Active, $conversation->status);
            $this->assertNull($conversation->resolved_at);
        }
    }

    public function test_duplicate_resolution_is_the_explicit_active_state_exception(): void
    {
        $this->travelTo('2026-09-22 12:00:00');
        $conversation = $this->conversation(ConversationState::IdentifyingItem);

        (new ConversationStateMachine)->resolveAsDuplicate($conversation);

        $this->assertSame(ConversationState::Resolved, $conversation->state);
        $this->assertSame(ConversationStatus::Resolved, $conversation->status);
        $this->assertSame('2026-09-22 12:00:00', $conversation->resolved_at?->format('Y-m-d H:i:s'));
    }

    public function test_duplicate_resolution_rejects_other_active_states_without_changing_them(): void
    {
        $conversation = $this->conversation(ConversationState::Started);
        $stateMachine = new ConversationStateMachine;

        try {
            $stateMachine->resolveAsDuplicate($conversation);
            $this->fail('An invalid duplicate resolution was accepted.');
        } catch (ConversationWorkflowException $exception) {
            $this->assertSame(ApiErrorCode::InvalidConversationTransition, $exception->errorCode());
            $this->assertSame(ConversationState::Started, $conversation->state);
            $this->assertSame(ConversationStatus::Active, $conversation->status);
        }
    }

    /**
     * @return array<string, array{ConversationState, ConversationState, bool}>
     */
    public static function transitionMatrix(): array
    {
        $allowed = [
            ConversationState::Started->value => [ConversationState::IdentifyingOrder],
            ConversationState::IdentifyingOrder->value => [ConversationState::IdentifyingItem],
            ConversationState::IdentifyingItem->value => [ConversationState::CollectingReason],
            ConversationState::CollectingReason->value => [
                ConversationState::CollectingDetails,
                ConversationState::Evaluating,
            ],
            ConversationState::CollectingDetails->value => [ConversationState::Evaluating],
            ConversationState::Evaluating->value => [ConversationState::Resolved],
            ConversationState::Resolved->value => [],
        ];
        $matrix = [];

        foreach (ConversationState::cases() as $from) {
            foreach (ConversationState::cases() as $to) {
                $matrix["{$from->value} to {$to->value}"] = [
                    $from,
                    $to,
                    in_array($to, $allowed[$from->value], true),
                ];
            }
        }

        return $matrix;
    }

    private function conversation(ConversationState $state): RefundConversation
    {
        return new RefundConversation([
            'customer_id' => 1,
            'state' => $state,
            'status' => ConversationStatus::Active,
        ]);
    }
}
