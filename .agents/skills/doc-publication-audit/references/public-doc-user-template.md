# User-Facing Public Doc Template

Use this template for setup guides, feature guides, onboarding pages, troubleshooting pages, and any page a product user should read first.

## Recommended placement

- Root `README.md`: only the shortest entry point and link hub.
- `docs/README.md`: the public docs index.
- `docs/getting-started/*`: installation, first run, demo setup, and configuration pages.
- `docs/features/*`: user-visible feature explanations.

## Page structure

1. Title block
2. Purpose
3. Audience
4. What the user can do or observe
5. Step-by-step usage
6. Examples or screenshots if they improve clarity
7. Troubleshooting or recovery steps
8. Common mistakes and constraints
9. Related links

## Starter outline

```md
# Feature Name

## Purpose
Explain what the feature helps the user accomplish.

## Audience
State who should read this page.

## What you will see
Describe the observable behavior or result.

## Setup or usage
List the minimum steps needed to use the feature.

## Common mistakes
List the two or three most likely pitfalls.

## Troubleshooting
Describe the fastest recovery path for the most common setup or demo failures.

## Constraints
Call out unsupported cases or required assumptions.

## Related links
- Link to the docs index
- Link to the implementation or config reference
```

## Typical OSS pattern to mirror

- Keep the page short and action-oriented.
- Prefer explicit verbs: install, configure, log in, create, search, export, review.
- Put internal history, migrations, and design debates in private work notes instead of the public page.
- For onboarding and demo pages, include one short troubleshooting section so readers do not have to guess the recovery path.
