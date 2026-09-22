---
paths:
  - 'app/Http/Controllers/**'
---

# Controllers

## Move complex operations out of controllers
Keep controllers limited to authorization, validated input, invoking an operation, and returning a response. When an endpoint requires transactions, multiple writes, state transitions, audit or event side effects, or other domain workflow beyond simple CRUD, create a focused action or service class to own that operation.
