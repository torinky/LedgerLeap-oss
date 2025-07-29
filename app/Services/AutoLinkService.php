<?php

namespace App\Services;

use App\Models\AutoLink;
use App\Models\Ledger;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\ColumnDefine;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\HtmlString;
use Spatie\LaravelMarkdown\MarkdownRenderer;

class AutoLinkService
{
    private MarkdownRenderer $markdownRenderer;

    public function __construct(MarkdownRenderer $markdownRenderer)
    {
        $this->markdownRenderer = $markdownRenderer;
    }

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
                $query->where(function ($q) use ($context) {
                    // 1. スコープが設定されていないグローバルな定義を対象にする
                    $q->whereDoesntHave('scopes');

                    // 2. コンテキストに応じたスコープを持つ定義を対象に追加する
                    $folder = null;
                    if ($context instanceof Folder) {
                        $folder = $context;
                    } elseif ($context instanceof LedgerDefine) {
                        $folder = $context->folder;
                    } elseif ($context instanceof Ledger) {
                        $folder = $context->define->folder;
                    }

                    // フォルダのコンテキストがある場合、その階層に紐づく定義を取得
                    if ($folder) {
                        $folderIds = $folder->descendantsAndSelf($folder->id)->pluck('id');
                        $q->orWhereHas('scopes', function ($subQuery) use ($context, $folderIds) {
                            $subQuery->where(function ($s) use ($context, $folderIds) {
                                // スコープがフォルダ階層のいずれかに設定されている
                                $s->where('scopeable_type', (new Folder)->getMorphClass())
                                  ->whereIn('scopeable_id', $folderIds);

                                // もしコンテキストが台帳定義なら、それに直接紐づくスコープも考慮
                                if ($context instanceof LedgerDefine) {
                                    $s->orWhere(function($orS) use ($context) {
                                        $orS->where('scopeable_id', $context->id)
                                            ->where('scopeable_type', $context->getMorphClass());
                                    });
                                }
                            });
                        });
                    }
                    Log::debug('AutoLinkService: Retrieved AutoLinks for folder ', [
                        'contextId'=> $context?->id ,
                        'folderId'=>$folder->id,
                        'folderIds'=>$folderIds ?? null,
                    ]);
                });
            }

            return $query->orderBy('priority', 'asc')->get();
        });
//        $autoLinks = AutoLink::where('is_enabled', true)->orderBy('priority', 'asc')->get();

        Log::debug('AutoLinkService: Retrieved AutoLinks', [
            'cacheKey' => $cacheKey,
            'autoLinksCount' => count($autoLinks),
            'column' => $column?->type ,
            'contextId'=> $context?->id ,
            'autoLinks' => $autoLinks->toArray()
        ]);

        $convertedHtml = $text;

        foreach ($autoLinks as $autoLink) {
//            Log::debug('AutoLinkService: Processing AutoLink', ['pattern' => $autoLink->pattern, 'url_template' => $autoLink->url_template]);
//            Log::debug('AutoLinkService: Before preg_replace_callback', ['convertedText' => $convertedHtml]);
            // preg_replace_callback を使用して、マッチした部分をリンクに置換
            // 一度マッチした文字列は後続のルールの対象外とするため、変換結果を次のループに渡す
            $convertedHtml = preg_replace_callback($autoLink->pattern, function ($matches) use ($autoLink) {
                $url = $autoLink->url_template;
                // $1, $2 などのキャプチャグループを置換
                // $matches[0] は全体マッチなのでスキップ
                for ($i = 1, $iMax = count($matches); $i < $iMax; $i++) {
                    // URLエンコードしてから置換
                    $url = str_replace('$'. $i, urlencode($matches[$i]), $url);
                }
                $target = $autoLink->open_in_new_tab ? ' target="_blank"' : '';
                return '<a href="' . e($url) . '"' . $target . ' class="font-bold text-primary-500 hover:underline">' . e($matches[0]) . '</a>';
            }, $convertedHtml);
//            Log::debug('AutoLinkService: After preg_replace_callback', ['convertedText' => $convertedHtml]);
        }

        // MarkdownをHTMLに変換
        return $this->markdownRenderer->toHtml($convertedHtml);
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
        }

        if ($context instanceof LedgerDefine) {
            return 'auto_links_ledger_define_' . $context->id;
        }

        return 'auto_links_global';
    }

}