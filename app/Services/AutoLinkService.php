<?php

namespace App\Services;

use App\Models\AutoLink;
use App\Models\Ledger;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\ColumnDefine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\HtmlString;

class AutoLinkService
{
    /**
     * テキストを自動リンクに変換する
     *
     * @param string $text 変換対象の文字列
     * @param ColumnDefine|null $column オプション。ColumnDefine オブジェクト。auto_number タイプの場合に特別処理を行うために使用
     * @param mixed $context オプション。適用範囲を絞り込むためのコンテキスト情報（例: Folder モデルのインスタンス、LedgerDefine モデルのインスタンスなど）
     * @return string 変換後のHTML文字列
     */
    public function convert(string $text, ?ColumnDefine $column = null, $context = null): string
    {
        // 1. auto_number カラムの特別処理
        if ($column && $column->getType() === 'auto_number') {
            // 台帳内検索へのリンクを生成
            $url = url('/ledgers?query=' . urlencode($text));
            return '<a href="' . e($url) . '" target="_blank" class="font-bold text-primary-500 hover:underline">' . e($text) . '</a>';
        }

        // 2. カスタム定義によるリンク変換
        $cacheKey = $this->getCacheKeyForContext($context);
        $autoLinks = Cache::remember($cacheKey, now()->addMinutes(60), function () use ($context) {
            $query = AutoLink::where('is_enabled', true);

            if ($context) {
                // ポリモーフィックリレーションシップを使用して適用範囲をフィルタリング
                // 現状はFolderとLedgerDefineを想定
                if ($context instanceof Folder || $context instanceof LedgerDefine) {
                    $query->whereHas('scopes', function ($q) use ($context) {
                        $q->where('scopeable_id', $context->id)
                            ->where('scopeable_type', $context->getMorphClass());
                    });
                }
            }

            return $query->orderBy('priority', 'asc')->get();
        });

        $convertedText = $text;

        foreach ($autoLinks as $autoLink) {
            // preg_replace_callback を使用して、マッチした部分をリンクに置換
            // 一度マッチした文字列は後続のルールの対象外とするため、変換結果を次のループに渡す
            $convertedText = preg_replace_callback($autoLink->pattern, function ($matches) use ($autoLink) {
                $url = $autoLink->url_template;
                foreach ($matches as $key => $value) {
                    // URLエンコードしてから置換
                    $url = str_replace('$' . $key, urlencode($value), $url);
                }
                $target = $autoLink->open_in_new_tab ? ' target="_blank"' : '';
                return '<a href="' . e($url) . '"' . $target . ' class="font-bold text-primary-500 hover:underline">' . e($matches[0]) . '</a>';
            }, $convertedText);
        }

        return $convertedText;
    }

    /**
     * コンテキストに応じたキャッシュキーを生成する
     *
     * @param mixed $context
     * @return string
     */
    protected function getCacheKeyForContext($context): string
    {
        if ($context instanceof Folder) {
            return 'auto_links_folder_' . $context->id;
        } elseif ($context instanceof LedgerDefine) {
            return 'auto_links_ledger_define_' . $context->id;
        } else {
            return 'auto_links_global';
        }
    }
}