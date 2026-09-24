---
paths:
  - app/Services/Refunds/RefundProcessor.php
---

# Services Refunds

## Keep processor outcomes atomic and non-retriable
Persist each returned payment outcome under a locked refund transaction. Success atomically writes processed state, audit, system message, and an after-commit event. A processor failure result must remain processing, increment attempts once, store a safe admin-only error, write no customer message, schedule no retry, and publish only the after-commit admin notification intent.
