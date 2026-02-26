<?php

namespace App\Services\Embedding;

use App\Models\AttachedFile;
use App\Models\Ledger;

class RuriChunkFormatter
{
    public function __construct(
        private KeywordEnhancedTextGenerator $keywordGenerator
    ) {}

    /**
     * 台帳レコード本体用のチャンクテキストを生成する
     */
    public function formatForLedger(Ledger $ledger, string $markdownContent): string
    {
        $metadata = [
            'Type' => '台帳レコード',
            'ID' => $ledger->id,
            'Title' => $ledger->define?->title ?? '不明',
            'Status' => $ledger->status?->label() ?? '不明',
            'Creator' => $ledger->creator?->name ?? '不明',
            'Created' => $ledger->created_at?->format('Y-m-d H:i') ?? '不明',
            'Folder' => $this->getFolderPath($ledger),
        ];

        return $this->format($metadata, $markdownContent);
    }

    /**
     * 添付ファイル用のチャンクテキストを生成する
     */
    public function formatForAttachedFile(AttachedFile $file, string $content): string
    {
        $metadata = [
            'Type' => '添付ファイル',
            'Filename' => $file->filename,
            'MimeType' => $file->mime,
            'Created' => $file->created_at?->format('Y-m-d H:i') ?? '不明',
            'ParentID' => $file->ledger_id,
            'ParentTitle' => $file->ledger?->define?->title ?? '不明',
            'Folder' => $this->getFolderPath($file->ledger),
        ];

        return $this->format($metadata, $content);
    }

    /**
     * メタデータと本文を結合し、キーワード抽出を行って最終テキストを生成する
     */
    private function format(array $metadata, string $content): string
    {
        // 本文からキーワード抽出
        $keywords = $this->keywordGenerator->extract($content, [
            'max_keywords' => 15,
            'target_types' => ['固有名詞', '名詞', '記号', '数'],
        ]);

        // メタデータにキーワードを追加
        if (! empty($keywords)) {
            // 上位10件程度を採用
            $topKeywords = array_slice(array_keys($keywords), 0, 10);
            $metadata['Keywords'] = implode(', ', $topKeywords);
        }

        // メタデータセクションの構築
        $headerLines = ['[Metadata]'];
        foreach ($metadata as $key => $value) {
            if (empty($value)) {
                continue;
            }
            $headerLines[] = "{$key}: {$value}";
        }

        // 本文セクションの構築
        $bodyLines = [
            '',
            '[Body]',
            $content,
        ];

        return implode("\n", array_merge($headerLines, $bodyLines));
    }

    private function getFolderPath(?Ledger $ledger): string
    {
        if (! $ledger || ! $ledger->define || ! $ledger->define->folder) {
            return 'ルート';
        }

        $folder = $ledger->define->folder;
        // ancestorを使ってパスを構築（キャッシュされている場合あり）
        // ここでは簡易的にフォルダ名だけ、または親を辿る
        // 頻繁に呼ぶならN+1注意だが、Job内なので許容範囲
        $ancestors = $folder->ancestors()->get();
        $path = $ancestors->pluck('title')->push($folder->title)->implode('/');

        return $path;
    }
}
