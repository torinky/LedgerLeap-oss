#!/usr/bin/env php
<?php
/**
 * PHPDoc Sweep Scanner — v2 with cross-reference and git integration.
 *
 * Scans PHP source directories for files with class/interface/trait declarations
 * that lack class-level PHPDoc. For each candidate, also detects:
 *   - Related docs in docs/ that reference this class (drift candidates)
 *   - Git recency (prioritize files changed recently)
 *
 * Usage:
 *   php scripts/phpdoc-scan.php                                  # scan app/ (top 10 + summary)
 *   php scripts/phpdoc-scan.php app/Models,app/Services           # specific dirs
 *   php scripts/phpdoc-scan.php .                                 # full project
 *   php scripts/phpdoc-scan.php --limit 5                        # override to 5
 *   php scripts/phpdoc-scan.php --all                            # no limit
 *   php scripts/phpdoc-scan.php app/Services --limit 3            # both
 */

$baseDir = getcwd();
$targets = 'app';
$limit = 10;  // default: top 10 by priority + total summary

for ($i = 1; $i < $argc; $i++) {
    if ($argv[$i] === '--limit' && isset($argv[$i + 1])) {
        $limit = (int) $argv[$i + 1];
        if ($limit <= 0) $limit = null;  // --limit 0 or negative = no limit
        $i++;
    } elseif ($argv[$i] === '--all') {
        $limit = null;  // --all = no limit
    } elseif (str_starts_with($argv[$i], '--')) {
        // skip unknown flags
    } else {
        $targets = $argv[$i];
    }
}
$targetDirs = array_map('trim', explode(',', $targets));

$errors = [];

// ──────────────────────────────────────────────
// 1. Collect doc files (exclude work/, harnesses/)
// ──────────────────────────────────────────────
$docFiles = [];
if (is_dir($baseDir . '/docs')) {
    $dit = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($baseDir . '/docs'));
    foreach ($dit as $df) {
        if ($df->getExtension() !== 'md' || $df->isDir()) continue;
        $p = $df->getPathname();
        if (str_contains($p, '/work/') || str_contains($p, '/harnesses/')) continue;
        $docFiles[] = $p;
    }
}
sort($docFiles);

// ──────────────────────────────────────────────
// 2. Collect git recency map (files changed in last 30 commits)
// ──────────────────────────────────────────────
$gitRecent = [];
exec('git log --format="%H %ai" --name-only -30 2>/dev/null', $gitLines, $gitCode);
if ($gitCode === 0) {
    $currentCommit = '';
    foreach ($gitLines as $line) {
        if (preg_match('/^[0-9a-f]{40}\s/', $line)) {
            $currentCommit = trim($line);
            continue;
        }
        if ($line !== '' && $currentCommit !== '') {
            $gitRecent[$line] = $currentCommit;
        }
    }
}

// ──────────────────────────────────────────────
// 3. Scan PHP files for classes
// ──────────────────────────────────────────────
$allFiles = [];
$results = [];
$total = 0;
$missing = 0;

foreach ($targetDirs as $target) {
    $absTarget = $baseDir . '/' . ltrim($target, '/');
    if (!is_dir($absTarget)) {
        $errors[] = "Directory not found: $target";
        continue;
    }

    $it = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($absTarget));
    foreach ($it as $file) {
        if ($file->getExtension() !== 'php') continue;
        if ($file->isDir()) continue;

        $path = $file->getPathname();
        $content = @file_get_contents($path);
        if ($content === false) {
            $errors[] = "Cannot read: $path";
            continue;
        }

        // Extract class/interface/trait name and namespace
        if (!preg_match('/\b(class|interface|trait)\s+(\w+)/', $content, $nameMatch)) {
            continue;
        }
        $total++;
        $shortName = $nameMatch[2];
        $ns = '';
        if (preg_match('/^namespace\s+([^;]+)/m', $content, $nsMatch)) {
            $ns = $nsMatch[1] . '\\' . $shortName;
        }

        // Check for DocBlock before class declaration
        $hasClassDocBlock = (bool) preg_match(
            '/\/\*\*[\s\S]*?\*\/' .
            '(?:\s*#\[[\s\S]*?\])*' .
            '\s*(abstract\s+|final\s+|readonly\s+)?' .
            '\b(class|interface|trait)\s+\w+/',
            $content
        );

        // Scan each public method for DocBlock presence
        $allMethods = [];
        preg_match_all(
            '/' .
            '(\/\*\*[\s\S]*?\*\/)?' .     // $1: optional DocBlock
            '\s*' .
            '(?:#\[[\s\S]*?\]\s*)*' .      // zero or more attributes
            'public\s+function\s+(\w+)\s*\(' .
            '/',
            $content,
            $methodMatches,
            PREG_SET_ORDER
        );

        $totalPublicMethods = 0;
        $publicMethodsMissingDockblock = 0;
        foreach ($methodMatches as $m) {
            $totalPublicMethods++;
            if (empty($m[1])) {
                $publicMethodsMissingDockblock++;
            }
        }

        $relative = str_replace($baseDir . '/', '', $path);

        // Include file if class-level DocBlock is missing OR any public method is missing DocBlock
        if (!$hasClassDocBlock || $publicMethodsMissingDockblock > 0) {
            $results[] = [
                'file' => $relative,
                'short_name' => $shortName,
                'fqn' => $ns,
                'methods' => $totalPublicMethods,
                'methods_missing_docblock' => $publicMethodsMissingDockblock,
                'has_class_docblock' => $hasClassDocBlock,
            ];
            $missing++;
        }

        $allFiles[] = [
            'file' => $relative,
            'short_name' => $shortName,
            'fqn' => $ns,
        ];
    }
}

// ──────────────────────────────────────────────
// 4. Build cross-reference: which docs mention which class?
// ──────────────────────────────────────────────
$docRefMap = []; // cache: docPath => set of short names found
$refIndex = [];  // file key => [doc paths]

foreach ($allFiles as $info) {
    $key = $info['file'];
    $refIndex[$key] = [];
}

// Batch process each doc file: grep for all short names at once
// Use a combined pattern of all short names
$allShortNames = array_unique(array_column($allFiles, 'short_name'));
$processed = [];

// We'll do more targeted matching: for each doc, read and check each class name
// This is O(docs * classes) but all in-memory, should be fast for 127*427 ≈ 54k operations
foreach ($docFiles as $df) {
    $docContent = file_get_contents($df);
    $relDocPath = str_replace($baseDir . '/', '', $df);

    foreach ($allFiles as $info) {
        $key = $info['file'];
        $fqn = $info['fqn'];
        $shortName = $info['short_name'];

        // Match FQN first (most precise), then short name
        $found = false;
        if ($fqn && str_contains($docContent, $fqn)) {
            $found = true;
        }
        if (!$found && $shortName && str_contains($docContent, $shortName)) {
            // Avoid matching generic words. Only match PascalCase class names.
            if (preg_match('/\b' . preg_quote($shortName, '/') . '\b/', $docContent)) {
                $found = true;
            }
        }
        // Also match app/ relative path
        if (!$found) {
            $pathRef = str_replace('app/', 'app/', $info['file']);
            if (str_contains($docContent, $pathRef)) {
                $found = true;
            }
        }

        if ($found) {
            if (!isset($refIndex[$key])) $refIndex[$key] = [];
            $refIndex[$key][] = $relDocPath;
        }
    }
}

// Deduplicate refIndex entries
foreach ($refIndex as $key => $refs) {
    $refIndex[$key] = array_values(array_unique($refs));
}

// ──────────────────────────────────────────────
// 5. Enrich candidate queue
// ──────────────────────────────────────────────
$enriched = [];
foreach ($results as $r) {
    $key = $r['file'];
    $relatedDocs = $refIndex[$key] ?? [];
    $recentCommit = $gitRecent[$r['file']] ?? null;

    // Priority: missing PHPDoc + referenced in docs = highest priority
    $hasDocRef = !empty($relatedDocs);
    $isRecent = $recentCommit !== null;
    $needsClassDoc = !$r['has_class_docblock'];
    $missingMethods = $r['methods_missing_docblock'];

    // Boost priority for class-level gaps; method-only gaps are lower
    if ($needsClassDoc && $hasDocRef && $isRecent && $r['methods'] >= 10) {
        $priority = 'critical';
    } elseif ($needsClassDoc && ($hasDocRef || ($isRecent && $r['methods'] >= 10))) {
        $priority = 'high';
    } elseif ($needsClassDoc && $r['methods'] >= 10) {
        $priority = 'medium';
    } elseif ($missingMethods >= 5 && ($hasDocRef || $isRecent)) {
        $priority = 'high';
    } elseif ($missingMethods >= 3) {
        $priority = 'medium';
    } else {
        $priority = 'low';
    }

    $enriched[] = [
        'file' => $r['file'],
        'methods' => $r['methods'],
        'methods_missing_docblock' => $missingMethods,
        'needs_class_docblock' => $needsClassDoc,
        'priority' => $priority,
        'related_docs' => $relatedDocs,
        'git_recent_commit' => $recentCommit,
    ];
}

// Sort: critical → high → medium → low, then by method count desc
usort($enriched, function ($a, $b) {
    $order = ['critical' => 0, 'high' => 1, 'medium' => 2, 'low' => 3];
    $pa = $order[$a['priority']] ?? 99;
    $pb = $order[$b['priority']] ?? 99;
    if ($pa !== $pb) return $pa <=> $pb;
    return $b['methods'] <=> $a['methods'];
});

// ──────────────────────────────────────────────
// 6. Compute global drift summary
// ──────────────────────────────────────────────
$filesWithDocRef = count(array_filter($enriched, fn($e) => !empty($e['related_docs'])));
$filesRecent = count(array_filter($enriched, fn($e) => $e['git_recent_commit'] !== null));

// ──────────────────────────────────────────────
// 7. Output
// ──────────────────────────────────────────────
// Apply limit to queue
$queue = $enriched;
if ($limit !== null && $limit > 0) {
    $queue = array_slice($enriched, 0, $limit);
}

$output = [
    'scanned_at' => date('c'),
    'scanned_dirs' => $targetDirs,
    'total_files_with_classes' => $total,
    'files_in_queue' => $missing,
    'files_missing_class_docblock' => count(array_filter($results, fn($r) => !$r['has_class_docblock'])),
    'files_missing_method_docblock' => count(array_filter($results, fn($r) => $r['has_class_docblock'] && $r['methods_missing_docblock'] > 0)),
    'files_in_queue_breakdown' => [
        'needs_class_docblock' => count(array_filter($results, fn($r) => !$r['has_class_docblock'])),
        'has_class_only_methods_missing' => count(array_filter($results, fn($r) => $r['has_class_docblock'] && $r['methods_missing_docblock'] > 0)),
    ],
    'cross_ref_files_scanned' => count($docFiles),
    'drift_risk' => [
        'files_with_related_docs' => $filesWithDocRef,
        'files_with_recent_git_changes' => $filesRecent,
    ],
    'queue_limit' => $limit ?? $missing,
    'queue' => $queue,
];

// Summary counts by priority
$byPriority = [];
foreach ($enriched as $e) {
    $p = $e['priority'];
    $byPriority[$p] = ($byPriority[$p] ?? 0) + 1;
}
$output['queue_summary'] = $byPriority;

if ($errors) {
    $output['errors'] = $errors;
}

echo json_encode($output, JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
