<?php

namespace App\Services;

use App\Models\AutoLink;
use App\Models\Ledger;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\ColumnDefine;
use Illuminate\Support\Facades\Cache;
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
        // 0. 入力が空の場合はそのまま返す
        if (empty($text)) {
            return '';
        }

        // 1. auto_number カラムの特別処理
        if ($column && $column->getType() === 'auto_number') {
            $url = url('/ledgers?query=' . urlencode($text));
            return '<a href="' . e($url) . '" target="_blank" class="font-bold text-primary-500 hover:underline">' . e($text) . '</a>';
        }

        // 2. カスタム定義によるリンク変換
        $autoLinks = $this->getAutoLinksForContext($context);

        if ($autoLinks->isEmpty()) {
            // 適用するリンク定義がない場合は、Markdown変換のみ行って返す
            return $this->markdownRenderer->toHtml($text);
        }

        $htmlishText = $this->markdownRenderer->toHtml($text);

        // DOMDocumentを使用してHTMLを安全に処理
        $dom = new \DOMDocument();
        // HTML5タグを正しく解釈させ、エラー出力を抑制
        libxml_use_internal_errors(true);
        // UTF-8を明示的に指定し、HTMLエンティティが文字化けするのを防ぐ
        $dom->loadHTML('<?xml encoding="UTF-8">' . $htmlishText, LIBXML_NOBLANKS);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);
        // テキストノードのみを検索対象とする (scriptやstyleタグ内は除外)
        $textNodes = $xpath->query('//text()[not(ancestor::script) and not(ancestor::style)]');

        foreach ($textNodes as $textNode) {
            $originalText = $textNode->nodeValue;
            $convertedText = $originalText;

            foreach ($autoLinks as $autoLink) {
                // パターンに 'u' (UTF-8) フラグを追加して、マルチバイト文字に正しく対応
                $pattern = $autoLink->pattern . (str_contains($autoLink->pattern, 'u') ? '' : 'u');

                $convertedText = preg_replace_callback(
                    $pattern,
                    function ($matches) use ($autoLink) {
                        $url = $autoLink->url_template;
                        for ($i = 1, $iMax = count($matches); $i < $iMax; $i++) {
                            $url = str_replace('$' . $i, urlencode($matches[$i]), $url);
                        }
                        $target = $autoLink->open_in_new_tab ? ' target="_blank"' : '';
                        return '<a href="' . e($url) . '"' . $target . ' class="font-bold text-primary-500 hover:underline">' . e($matches[0]) . '</a>';
                    },
                    $convertedText
                );
            }

            // テキストが変更された場合、ノードを置換
            if ($originalText !== $convertedText) {
                $fragment = $dom->createDocumentFragment();
                // @でエラーを抑制しつつ、HTML文字列をフラグメントに読み込ませる
                @$fragment->appendXML($convertedText);
                $textNode->parentNode->replaceChild($fragment, $textNode);
            }
        }

        // bodyタグ内のコンテンツを返す
        $body = $dom->getElementsByTagName('body')->item(0);
        $innerHtml = '';
        if ($body) {
            foreach ($body->childNodes as $child) {
                $innerHtml .= $dom->saveHTML($child);
            }
        }

        // 余分な空白や改行を除去
        $innerHtml = trim($innerHtml);
        $innerHtml = preg_replace('/>\s+</', '><', $innerHtml); // タグ間の空白を除去

        return $innerHtml;
    }

    /**
     * コンテキストに応じたAutoLink定義を取得する（キャッシュ利用）
     *
     * @param mixed $context
     * @return \Illuminate\Database\Eloquent\Collection
     */
    private function getAutoLinksForContext($context)
    {
        $cacheKey = $this->getCacheKeyForContext($context);
        return Cache::tags(['auto_links'])->remember($cacheKey, now()->addMinutes(60), function () use ($context) {
            $query = AutoLink::where('is_enabled', true);

            if ($context) {
                $query->where(function ($q) use ($context) {
                    $q->whereDoesntHave('scopes'); // グローバルな定義

                    $folder = null;
                    if ($context instanceof Folder) {
                        $folder = $context;
                    } elseif ($context instanceof LedgerDefine) {
                        $folder = $context->folder;
                    } elseif ($context instanceof Ledger) {
                        $folder = $context->define->folder;
                    }

                    if ($folder) {
                        $folderIds = $folder->descendantsAndSelf($folder)->pluck('id');
                        $q->orWhereHas('scopes', function ($subQuery) use ($folderIds) {
                            $subQuery->where('scopeable_type', (new Folder)->getMorphClass())
                                ->whereIn('scopeable_id', $folderIds);
                        });
                    }
                });
            }

            return $query->orderBy('priority', 'asc')->get();
        });
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

        if ($context instanceof Ledger) {
            // Ledgerの場合は、それが属するFolderのIDに基づいてキーを生成する
            if ($context->define && $context->define->folder) {
                return 'auto_links_folder_' . $context->define->folder->id;
            }
        }

        return 'auto_links_global';
    }
}
