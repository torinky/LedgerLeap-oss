<?php

/**
 * content_attached のデータ構造を確認するスクリプト
 *
 * 実行方法:
 * ./vendor/bin/sail artisan tinker < scripts/check_content_attached_structure.php
 *
 * または:
 * ./vendor/bin/sail tinker
 * > include 'scripts/check_content_attached_structure.php';
 */
echo "=== Checking content_attached structure ===\n\n";

// 1. 添付ファイルを持つ台帳を検索
echo "Searching for ledgers with attachments...\n";
$ledgersWithAttachments = App\Models\Ledger::whereNotNull('content_attached')
    ->whereRaw('JSON_LENGTH(content_attached) > 0')
    ->limit(3)
    ->get();

if ($ledgersWithAttachments->isEmpty()) {
    echo "❌ No ledgers with attachments found.\n";
    echo "   Please create a test ledger with file attachments first.\n";
    exit;
}

echo '✅ Found '.$ledgersWithAttachments->count()." ledger(s) with attachments\n\n";

// 2. 各台帳のcontent_attachedを確認
foreach ($ledgersWithAttachments as $index => $ledger) {
    echo '--- Ledger #'.($index + 1)." (ID: {$ledger->id}) ---\n";
    echo 'Title: '.($ledger->content[0] ?? 'N/A')."\n";
    echo 'Created: '.$ledger->created_at->format('Y-m-d H:i:s')."\n\n";

    $contentAttached = $ledger->content_attached;

    if (empty($contentAttached)) {
        echo "⚠️  content_attached is empty\n\n";

        continue;
    }

    // content_attachedの型を確認
    echo 'Type of content_attached: '.gettype($contentAttached)."\n";

    if (is_object($contentAttached)) {
        echo "Converting object to array...\n";
        $contentAttached = json_decode(json_encode($contentAttached), true);
    }

    echo "\n";

    // 各カラムの添付ファイルを確認
    foreach ($contentAttached as $columnId => $files) {
        if (empty($files) || ! is_array($files)) {
            continue;
        }

        echo "Column ID: {$columnId}\n";
        echo 'Number of files: '.count($files)."\n";

        // 最初のファイルの詳細を確認
        $firstFile = array_values($files)[0];
        $firstHash = array_keys($files)[0];

        echo "First file hash: {$firstHash}\n";
        echo 'Available keys: '.implode(', ', array_keys($firstFile))."\n\n";

        // 各キーの内容を表示
        foreach ($firstFile as $key => $value) {
            echo "  [{$key}]: ";

            if (is_string($value)) {
                $length = mb_strlen($value);
                if ($length > 100) {
                    echo "(string, {$length} chars) ".mb_substr($value, 0, 100)."...\n";
                } else {
                    echo "(string, {$length} chars) {$value}\n";
                }
            } elseif (is_numeric($value)) {
                echo "({$value})\n";
            } elseif (is_array($value)) {
                echo '(array, '.count($value)." items)\n";
            } elseif (is_bool($value)) {
                echo '('.($value ? 'true' : 'false').")\n";
            } elseif (is_null($value)) {
                echo "(null)\n";
            } else {
                echo '('.gettype($value).")\n";
            }
        }

        echo "\n";

        // テキスト抽出関連のキーを特定
        $textKeys = ['extracted_text', 'text', 'content', 'contain_content', 'ocr_text'];
        $foundTextKeys = array_filter($textKeys, fn ($key) => isset($firstFile[$key]));

        if (! empty($foundTextKeys)) {
            echo '✅ Found text-related keys: '.implode(', ', $foundTextKeys)."\n";

            foreach ($foundTextKeys as $textKey) {
                if (is_string($firstFile[$textKey])) {
                    $textLength = mb_strlen($firstFile[$textKey]);
                    echo "   {$textKey} length: {$textLength} chars\n";

                    if ($textLength > 0) {
                        echo '   Preview: '.mb_substr($firstFile[$textKey], 0, 200)."...\n";
                    }
                }
            }
        } else {
            echo '❌ No text-related keys found in: '.implode(', ', array_keys($firstFile))."\n";
        }

        echo "\n".str_repeat('-', 60)."\n\n";
    }
}

// 3. AttachedFileテーブルも確認
echo "=== Checking attached_files table ===\n\n";

$attachedFile = App\Models\AttachedFile::whereNotNull('contain_content')
    ->where('contain_content', true)
    ->first();

if ($attachedFile) {
    echo "✅ Found AttachedFile with contain_content = true\n";
    echo "   ID: {$attachedFile->id}\n";
    echo "   Filename: {$attachedFile->filename}\n";
    echo "   Status: {$attachedFile->status->value}\n";
    echo "   MIME: {$attachedFile->mime}\n";
    echo '   Size: '.number_format($attachedFile->size)." bytes\n";
    echo "   Ledger ID: {$attachedFile->ledger_id}\n";
    echo "   Column ID: {$attachedFile->column_id}\n";
    echo "   Hash: {$attachedFile->hashedbasename}\n";
} else {
    echo "⚠️  No AttachedFile records with contain_content = true found\n";
}

echo "\n=== Analysis Complete ===\n";
echo "\nConclusion:\n";
echo "- Check if text-related keys exist in content_attached\n";
echo "- Verify if AttachedFile.contain_content is used correctly\n";
echo "- Determine if Option 3-A implementation is feasible\n";
