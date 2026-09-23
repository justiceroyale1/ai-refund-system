---
paths:
  - app/Actions/Refunds/EvaluateRefundConversation.php
---

# Refunds

## Preserve conversation-wide AI risk signals
Policy evaluation aggregates every persisted AI analysis for the conversation: prompt-injection and conflicting-information flags use any-match semantics, while confidence uses the minimum observed value. An absence of AI analysis uses confidence 0 so the policy fails safe to human review instead of automatic approval.
