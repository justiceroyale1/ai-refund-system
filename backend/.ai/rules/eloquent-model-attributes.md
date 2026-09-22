---
paths:
  - 'app/**/*.php'
  - 'database/**/*.php'
  - 'tests/**/*.php'
---

# Eloquent Model Attributes

## Prefer property syntax for known attribute reads
Read statically named Eloquent attributes through property syntax, such as `$conversation->state`. Do not use `$model->getAttribute('attribute')` when the attribute name is known while writing the code.

If static analysis cannot infer a casted attribute's runtime type, declare the accurate property type on the model instead of falling back to `getAttribute()`, adding a suppression, or forcing a type cast. Reserve `getAttribute($name)` for genuinely dynamic or framework-generic access where the attribute name is determined at runtime.

This rule applies to attribute reads. Explicit `setAttribute()` calls remain permitted for writes when they make a domain transition clearer.
