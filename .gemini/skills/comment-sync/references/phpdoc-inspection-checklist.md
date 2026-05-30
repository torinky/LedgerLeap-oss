# PHPDoc Inspection Checklist

Step-by-step procedure for auditing one source file against its public doc. Each step is self-contained so it can be executed file-by-file without losing context.

## Pre-check: determine if this class is in scope

Before auditing a class, verify:
- [ ] The class is listed in the packet's `source_anchor` or `comment_anchors`.
- [ ] The class is a `controller`, `Livewire component`, `service`, `MCP/API tool`, or `stable DTO/value object`.
- [ ] The class is NOT: a `private` helper with no public surface, a boilerplate accessor, a migration-only class, or an internal detail not described in the doc.

If any of these fail, skip the class and note the skip reason.

## Step 1: Class-level PHPDoc

### 1a. Does the class have a DocBlock immediately before `class ...`?

- **No** → add a new DocBlock with:
  - Short summary (one sentence describing the class's public responsibility).
  - For service/controller classes: a 1-2 line description of the lifecycle or data flow handled.
  - `@see` tags pointing to related classes (e.g. `@see \App\Enums\WorkflowStatus`).
  - `@api` tag — only if this is a **stable public contract surface** (rare; mostly for API controllers and MCP tools).

- **Yes** → verify the summary is **not stale**:
  - Does it still describe what the class actually does? (Read the first ~20 lines of the class body to confirm.)
  - Are `@see` references outdated? (Check that referenced classes still exist.)
  - If stale, rewrite only the stale parts. Do not add `@api` unless the class is genuinely a stable contract.

### 1b. Edge cases

- **Trait**: same rules as class. Focus on the trait's responsibility, not its consuming classes.
- **Interface**: short summary of the contract, not the implementation.
- **Abstract class**: summary of the abstract responsibility + `@see` for concrete implementations.

## Step 2: Public method PHPDoc

Only audit methods that are:
- Explicitly listed in `comment_anchors`, OR
- Public methods whose behavior is **directly described** in the public doc's prose.

### 2a. Does the method have a DocBlock?

- **No** → add one with:
  - Short summary (what the method does, not how).
  - If the method has complex parameters: `@param` for each.
  - If the method is non-void: `@return`.
  - If the method throws observable exceptions: `@throws`.

- **Yes** → verify each tag against the actual signature.

### 2b. Verify `@param` tags

For each `@param` in the DocBlock:

```
@param Type $name Description
```

| Check | How to verify | Action if wrong |
|---|---|---|
| Count matches? | Count `@param` tags vs actual parameters | Remove stale tags, add missing tags |
| Name matches? | Compare `$name` to the actual parameter name | Fix the name in the tag |
| Type matches? | Compare `Type` to the actual parameter type hint | Fix the type. If the actual type is nullable (`?string`), use `string|null` |

Skip `@param` entirely for:
- Trivial scalar parameters where the name is self-documenting (e.g. `int $id`).
- Parameters that exist only for framework wiring (e.g. injected dependencies in `mount()`).

### 2c. Verify `@return` tag

| Check | How to verify | Action |
|---|---|---|
| Tag exists? | Non-void return types should have `@return` | Add if missing |
| Type matches? | Compare `@return Type` to the actual return type declaration | Fix if wrong |
| Void method? | If `: void`, do NOT add `@return` | Remove any stale `@return` tag |

Structured return types (collections, resources, arrays of objects) should describe the shape:
```
@return \Illuminate\Http\Resources\Json\AnonymousResourceCollection|\Illuminate\Http\JsonResponse
```

### 2d. Verify `@throws` tag

Only add `@throws` for **observable failure modes** — exceptions that are:
- Explicitly thrown in the method body.
- Part of the method's documented contract (e.g. `@throws AuthorizationException`).
- NOT generic runtime errors (e.g. `\Exception`, `\Error`, `\TypeError`).

DO NOT add `@throws` for:
- Exceptions thrown by framework internals.
- Exceptions that are caught within the method.
- Generic `\Exception` as a catch-all.

### 2e. DocBlock order

Always maintain this order within the DocBlock:
```
/**
 * Short summary.
 *
 * Description (if needed — can be multi-line).
 *
 * @param Type $name Description
 * @return Type
 * @throws ExceptionType When it happens
 * @see OtherClass
 */
```

- `summary` → `description` → `@param` → `@return` → `@throws` → `@see`
- No extra blank lines between tags of the same type.
- One blank line between the description block and the first tag.

## Step 3: Post-edit verification

After editing each file:

- [ ] Read back the file; confirm the DocBlock text sits directly above the class/method it describes.
- [ ] Run PhpStorm inspections on the file (`get_inspections`). Fix any warnings from the new comments.
- [ ] If a `@param` or `@return` type references a class, confirm that class exists (use `find_symbol`).
- [ ] Do NOT reformat unrelated code. Only the DocBlock areas should change.

## Step 4: Record results

For each audited file, record:

| File | Symbols changed | What was done |
|---|---|---|
| `app/Services/WorkflowService.php` | class, `approve()`, `submitForInspection()` | Added class PHPDoc; fixed `@param` types in `approve()`; added `@return` to `submitForInspection()` |
| `app/Livewire/Ledger/Show.php` | class | Added class PHPDoc (methods already documented) |

Track this in the packet acceptance table under the "comment sync handled" row.
