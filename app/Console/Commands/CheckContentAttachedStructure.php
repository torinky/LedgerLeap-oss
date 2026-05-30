<?php

namespace App\Console\Commands;

use App\Models\AttachedFile;
use App\Models\Ledger;
use Illuminate\Console\Command;

class CheckContentAttachedStructure extends Command
{
    protected $signature = 'mcp:check-content-attached';

    protected $description = 'Check the structure of content_attached field in ledgers';

    public function handle()
    {
        $this->info('=== Checking content_attached structure ===');
        $this->newLine();

        // 1. 添付ファイルを持つ台帳を検索
        $this->info('Searching for ledgers with attachments...');
        $ledgersWithAttachments = Ledger::whereNotNull('content_attached')
            ->whereRaw('JSON_LENGTH(content_attached) > 0')
            ->limit(3)
            ->get();

        if ($ledgersWithAttachments->isEmpty()) {
            $this->error('❌ No ledgers with attachments found.');
            $this->warn('   Please create a test ledger with file attachments first.');

            return 1;
        }

        $this->info('✅ Found '.$ledgersWithAttachments->count().' ledger(s) with attachments');
        $this->newLine();

        // 2. 各台帳のcontent_attachedを確認
        foreach ($ledgersWithAttachments as $index => $ledger) {
            $this->line('--- Ledger #'.($index + 1).' (ID: '.$ledger->id.') ---');
            $this->line('Title: '.($ledger->content[0] ?? 'N/A'));
            $this->line('Created: '.$ledger->created_at->format('Y-m-d H:i:s'));
            $this->newLine();

            $contentAttached = $ledger->content_attached;

            if (empty($contentAttached)) {
                $this->warn('⚠️  content_attached is empty');
                $this->newLine();

                continue;
            }

            // content_attachedの型を確認
            $this->line('Type of content_attached: '.gettype($contentAttached));

            if (is_object($contentAttached)) {
                $this->line('Converting object to array...');
                $contentAttached = json_decode(json_encode($contentAttached), true);
            }

            $this->newLine();

            // 各カラムの添付ファイルを確認
            foreach ($contentAttached as $columnId => $files) {
                if (empty($files) || ! is_array($files)) {
                    continue;
                }

                $this->line('Column ID: '.$columnId);
                $this->line('Number of files: '.count($files));

                // 最初のファイルの詳細を確認
                $firstFile = array_values($files)[0];
                $firstHash = array_keys($files)[0];

                $this->line('First file hash: '.$firstHash);
                $this->line('Available keys: '.implode(', ', array_keys($firstFile)));
                $this->newLine();

                // 各キーの内容を表示
                foreach ($firstFile as $key => $value) {
                    $output = "  [{$key}]: ";

                    if (is_string($value)) {
                        $length = mb_strlen($value);
                        if ($length > 100) {
                            $output .= "(string, {$length} chars) ".mb_substr($value, 0, 100).'...';
                        } else {
                            $output .= "(string, {$length} chars) {$value}";
                        }
                    } elseif (is_numeric($value)) {
                        $output .= "({$value})";
                    } elseif (is_array($value)) {
                        $output .= '(array, '.count($value).' items)';
                    } elseif (is_bool($value)) {
                        $output .= '('.($value ? 'true' : 'false').')';
                    } elseif (is_null($value)) {
                        $output .= '(null)';
                    } else {
                        $output .= '('.gettype($value).')';
                    }

                    $this->line($output);
                }

                $this->newLine();

                // テキスト抽出関連のキーを特定
                $textKeys = ['extracted_text', 'text', 'content', 'contain_content', 'ocr_text'];
                $foundTextKeys = array_filter($textKeys, fn ($key) => isset($firstFile[$key]));

                if (! empty($foundTextKeys)) {
                    $this->info('✅ Found text-related keys: '.implode(', ', $foundTextKeys));

                    foreach ($foundTextKeys as $textKey) {
                        if (is_string($firstFile[$textKey])) {
                            $textLength = mb_strlen($firstFile[$textKey]);
                            $this->line("   {$textKey} length: {$textLength} chars");

                            if ($textLength > 0) {
                                $this->line('   Preview: '.mb_substr($firstFile[$textKey], 0, 200).'...');
                            }
                        }
                    }
                } else {
                    $this->error('❌ No text-related keys found in: '.implode(', ', array_keys($firstFile)));
                }

                $this->newLine();
                $this->line(str_repeat('-', 60));
                $this->newLine();
            }
        }

        // 3. AttachedFileテーブルも確認
        $this->info('=== Checking attached_files table ===');
        $this->newLine();

        $attachedFile = AttachedFile::whereNotNull('contain_content')
            ->where('contain_content', true)
            ->first();

        if ($attachedFile) {
            $this->info('✅ Found AttachedFile with contain_content = true');
            $this->line('   ID: '.$attachedFile->id);
            $this->line('   Filename: '.$attachedFile->filename);
            $this->line('   Status: '.$attachedFile->status->value);
            $this->line('   MIME: '.$attachedFile->mime);
            $this->line('   Size: '.number_format($attachedFile->size).' bytes');
            $this->line('   Ledger ID: '.$attachedFile->ledger_id);
            $this->line('   Column ID: '.$attachedFile->column_id);
            $this->line('   Hash: '.$attachedFile->hashedbasename);
        } else {
            $this->warn('⚠️  No AttachedFile records with contain_content = true found');
        }

        $this->newLine();
        $this->info('=== Analysis Complete ===');
        $this->newLine();

        $this->line('Conclusion:');
        $this->line('- Check if text-related keys exist in content_attached');
        $this->line('- Verify if AttachedFile.contain_content is used correctly');
        $this->line('- Determine if Option 3-A implementation is feasible');

        return 0;
    }
}
