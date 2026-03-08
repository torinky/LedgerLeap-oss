# External Research Order and Reporting

External research starts only after internal evidence has been reviewed.

## Source Priority

1. Official product/framework documentation
2. Package documentation and release notes
3. GitHub Issues / Discussions / maintainer comments
4. Similar OSS implementations
5. Trusted technical articles or Q&A

## Separate the findings

Document these as separate sections:
- Similar implementation examples
- Similar error / exception examples
- General best practices

## Evaluation Rules

- Prefer sources that match the installed version or architecture.
- Do not copy an external fix directly without comparing it to LedgerLeap's code path.
- If an external suggestion conflicts with repo evidence, prefer repo evidence and note the conflict.
- Cite the source URL or document path in the investigation output.

## Minimum Response Format

### Similar external implementations
- Source
- Why it is relevant
- What can be reused safely

### Similar external errors
- Source
- Matching symptoms
- Differences from LedgerLeap's case

### Best practices
- Source
- Recommended pattern
- Whether LedgerLeap already follows it

## Exit Condition

External research is complete when it helps compare response options, not merely when more links have been collected.

