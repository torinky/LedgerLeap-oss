# Minimal Change Guidance

Use this when implementing a selected bug fix.

## Scope Control
- Prefer the closest code path to the root cause.
- Avoid opportunistic refactors unless they are required for correctness.
- If behavior must change externally, state the impact explicitly.
- If multiple candidate fixes exist, prefer the one with the smallest surface area unless evidence favors a structural fix.

## Regression Protection
- Add a targeted regression test when the failure can be reproduced deterministically.
- If a full test is expensive, add the narrowest test that guards the root cause.
- Update docs or templates only when they reduce repeat failures or confusion.

## Report Format
- What was changed
- Why this option was chosen
- What was deliberately not changed
- What still needs follow-up

