---
paths:
  - app/Actions/Conversations/SubmitConversationMessage.php
---

# Conversations

## Preserve message retry ordering
Resolve customer ownership before this action. Inside one transaction, lock the conversation and replay an existing same-conversation customer client_message_id before rejecting a new message for resolved status. Keep customer persistence, structured selection, generated messages, state transitions, and audits atomic so retries never repeat side effects.
