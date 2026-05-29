# Tags, Synonyms, and Search Taxonomy

## Summary

LedgerLeap's search system relies on three kinds of administrator-managed metadata: **tags** for cross-cutting ledger classification, **synonyms** for search query expansion, and **technical terms** for domain-specific vocabulary matching. These are configured through the Filament admin panel and affect search results for all end users within the tenant.

This page is for system administrators who configure the search taxonomy layer.

## Admin Surface

All search taxonomy management is performed through the Filament admin panel. The following resources are available:

| Resource | Purpose | Navigation |
|----------|---------|------------|
| Tags | Create, edit, and delete tags assigned to ledger definitions | Admin panel sidebar (navigation sort 3) |
| Synonym WordNet | Browse and manage the WordNet-based dictionary of words and their synonym relations | Accessible via navigation group (not in main nav) |
| Synonym Tansi | Manage Japanese pronunciation-based synonym candidates | Admin panel navigation group |
| Technical Term Groups | Define groups of equivalent technical terms for search expansion | Admin panel navigation group |

### Tags

Tags are per-tenant labels assigned to ledger definitions. They provide a cross-cutting classification axis independent of the folder hierarchy.

The tag resource table shows:

- **Tag Name**: Display name of the tag (sortable, searchable)
- **Ledger Defines**: Which ledger definitions this tag is assigned to (badge display)
- **Creator**: User who created the tag
- **Created At**: Creation timestamp

Operations available:
- **Create**: Define a new tag with a name and assign it to a ledger definition
- **Edit**: Rename the tag
- **Delete**: Remove the tag

Each tag also has a `Ledger Defines` relation manager on its edit page, allowing assignment to multiple ledger definitions.

### Synonym WordNet

The WordNet resource (`Synonym\WordResource`) provides access to a WordNet-based dictionary. Each word entry has:

- **Word ID**: Unique identifier from WordNet
- **Language**: Language code (`jpn` for Japanese, `eng` for English)
- **Lemma**: Dictionary form of the word (searchable)
- **Pronunciation**: Phonetic representation
- **Part of Speech**: POS tag

Filters are available for Japanese (`jpn`) and English (`eng`) language entries. Each word has a `Synonyms` relation manager that shows synonym relationships via shared synsets.

### Synonym Tansi

The Tansi resource (`Synonym\TansiResource`) provides access to a Japanese synonym candidate table based on pronunciation similarity. Each entry has:

- **Pronunciation 1**: Primary pronunciation reading
- **Pronunciation 2**: Secondary pronunciation reading (both searchable)
- **Category 1 / Category 2**: Semantic categories
- **Candidates**: Candidate synonym words (searchable)

This is a read-heavy reference table sourced from the `TANSI_V110` dataset.

### Technical Term Groups

The Technical Term Group resource (`Synonym\TechnicalTermGroupResource`) lets administrators define custom groups of equivalent technical terms. Each group contains:

- **ID**: Auto-generated identifier
- **Synonyms**: A repeater field of synonym strings within the group (searchable)
- **Modifier**: Last user who edited the group
- **Updated At**: Last modification timestamp
- **Creator / Created At**: Origin metadata

Operations available:
- **Create**: Define a new group with one or more synonym strings
- **Edit**: Modify the synonym list
- **Delete**: Remove the group

Technical term groups are stored as JSON arrays. Each group is a set of equivalent terms: searching for any member of the group will also match other members.

## Effects

### On Search

- **Tags**: When a user searches with a tag filter, only ledgers belonging to ledger definitions with that tag are returned. Tags are used for cross-cutting search — they span folder boundaries.
- **Synonyms (WordNet)**: When a user searches with a keyword, the search system consults the WordNet synonym dictionary. If the keyword matches a word entry, related words from shared synsets are automatically included in the query expansion.
- **Synonyms (Tansi)**: Japanese pronunciation-based candidates supplement the WordNet expansion for phonetic and orthographic variations.
- **Technical Terms**: When a user searches with a term that belongs to a technical term group, all other terms in the same group are included in the search. This enables matching of abbreviations, internal codes, and alternative terminology.

### On the MCP / API Client

- The `SearchLedgersTool` (MCP) and `SearchApi` (REST) both respect tag filters.
- The `GetSearchTermsTool` (MCP) can extract synonym and technical term candidates from a search phrase, distinguishing between the two categories so clients can build better queries.

## Constraints

- **Tags are tenant-scoped**: Tags use the `BelongsToTenant` trait and are isolated per tenant. A tag defined in tenant A is not visible in tenant B.
- **Tags are ledger-define-scoped**: Each tag is associated with one ledger definition. The same tag name can exist on different ledger definitions independently.
- **WordNet and Tansi are separate database connections**: WordNet uses the `wordnet` database connection; Tansi uses the `tansi` connection. They are shared across tenants and are not tenant-scoped.
- **Technical term group synonyms are JSON**: The `synonyms` column uses `AsJson` cast. Direct mutation outside the admin panel requires JSON array format.
- **Synonym resources have `shouldRegisterNavigation = false`**: These resources are hidden from the main sidebar and must be accessed through the navigation group mechanism or direct URL.
- **All resources require admin role**: Creation, editing, and deletion of tags, synonyms, and technical terms require administrative privileges.
- **Search expansion is additive only**: Synonyms and technical terms add matching candidates but do not exclude results. There is no negative synonym or antonym mechanism.

## Related Resources

- [Search and Lookup](../features/search-and-lookup.md) — End-user search features and how synonyms/terms affect results
- [Search API](../api/search-api.md) — REST API contract for search, including tag and keyword parameters
- [Tag Design Architecture](../architecture/tag-design.md) — Design rationale and best practices for tag taxonomy
- [Getting Started Overview](../getting-started/overview.md) — End-user concept overview
