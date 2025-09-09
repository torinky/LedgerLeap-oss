<?php

namespace App\Services;

use App\Models\AutoLink;
use App\Models\Ledger;
use App\Models\Folder;
use App\Models\LedgerDefine;
use App\Models\ColumnDefine;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

class AutoLinkService
{
    public function __construct(private HtmlProcessorService $htmlProcessorService)
    {
    }

    /**
     * テキストを自動リンクに変換する
     *
     * @param string $text 変換対象のHTML文字列
     * @param ColumnDefine|null $column オプション。ColumnDefine オブジェクト。auto_number タイプの場合に特別処理を行うために使用
     * @param mixed $context オプション。適用範囲を絞り込むためのコンテキスト情報（例: Folder モデルのインスタンス、LedgerDefine モデルのインスタンスなど）
     * @return string 変換後のHTML文字列
     */
    public function convert(string $text, ?ColumnDefine $column = null, $context = null): string
    {
        if (empty($text)) {
            return '';
        }

        return $this->htmlProcessorService->processTextNodes(
            $text,
            function (\DOMText $textNode, \DOMDocument $dom) use ($column, $context) {
                $originalText = $textNode->nodeValue;

                // auto_number カラムの特別処理
                if ($column && $column->getType() === 'auto_number') {
                    $linkHtml = $this->createAutoNumberLink($originalText);
                    $this->replaceTextNodeWithHtml($textNode, $linkHtml, $dom);
                    return; // auto_numberは他のカスタムリンクと重複させない
                }

                // カスタム定義によるリンク変換
                $autoLinks = $this->getAutoLinksForContext($context);
                if ($autoLinks->isEmpty()) {
                    return;
                }

                $this->applyCustomLinks($textNode, $autoLinks, $dom);
            }
        );
    }

    private function createAutoNumberLink(string $text): string
    {
        // tenantが初期化されているか確認
        if (!tenancy()->initialized) {
            // テナントが特定できない場合は、リンク化せずに元のテキストを返す
            return e($text);
        }

        $url = route('ledger.lookup', [
            'tenant' => tenancy()->tenant->getTenantKey(), // <<<--- ()を削除して修正
            'query' => $text
        ]);
        $iconName = config('ledgerleap.auto_links.link_types.default.icon', 'o-link');
        $tooltip = __('auto_links.tooltip_auto_number', ['value' => $text]);
        $iconHtml = Blade::render("<x-mary-icon name='{$iconName}' class='inline-block h-4 w-4 mr-1 -mt-1' />");

        return '<div class="tooltip mx-2" data-tip="' . e($tooltip) . '"><a href="' . e($url) . '" target="_blank" class="font-bold text-primary-500 hover:underline">' . $iconHtml . ' ' . e($text) . '</a></div>';
    }

    private function applyCustomLinks(\DOMText $textNode, $autoLinks, \DOMDocument $dom): void
    {
        $currentNode = $textNode;

        foreach ($autoLinks as $autoLink) {
            $pattern = $autoLink->pattern . (str_contains($autoLink->pattern, 'u') ? '' : 'u');
            $text = $currentNode->nodeValue;

            $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE | PREG_SPLIT_NO_EMPTY);
            if (count($parts) <= 1) {
                continue;
            }

            $fragment = $dom->createDocumentFragment();
            $matches = [];
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
            $matchIndex = 0;

            foreach ($parts as $part) {
                if ($matchIndex < count($matches) && $part === $matches[$matchIndex][0]) {
                    $linkHtml = $this->createCustomLink($autoLink, $matches[$matchIndex]);
                    $linkFragment = $dom->createDocumentFragment();
                    @$linkFragment->appendXML($linkHtml);
                    $fragment->appendChild($linkFragment);
                    $matchIndex++;
                } else {
                    $fragment->appendChild($dom->createTextNode($part));
                }
            }

            if ($fragment->hasChildNodes()) {
                $currentNode->parentNode->replaceChild($fragment, $currentNode);
                // The current node is replaced, so we can't continue operating on it.
                // For simplicity, we stop after the first matching autolink rule.
                // To handle multiple rules, a more complex node traversal would be needed.
                break;
            }
        }
    }

    private function createCustomLink(AutoLink $autoLink, array $matches): string
    {
        $url = $autoLink->url_template;
        for ($i = 1, $iMax = count($matches); $i < $iMax; $i++) {
            $url = str_replace('$' . $i, urlencode($matches[$i]), $url);
        }

        // URLが / で始まり、 // で始まらない（プロトコル相対URLではない）場合にテナントIDを付与
        if (str_starts_with($url, '/') && !str_starts_with($url, '//')) {
            // テナントが初期化されている場合のみ、IDを取得して付与する
            if (tenancy()->initialized) {
                $url = '/' . tenant()->getTenantKey() . $url;
            }
        }

        $target = $autoLink->open_in_new_tab ? ' target="_blank"' : '';
        $iconName = config('ledgerleap.auto_links.link_types.' . $autoLink->link_type . '.icon', 'o-link');
        $tooltip = $autoLink->label;
        $iconHtml = Blade::render("<x-mary-icon name='{$iconName}' class='inline-block h-4 w-4 mr-1 -mt-1' />");

        return '<div class="tooltip mx-2" data-tip="' . e($tooltip) . '"><a href="' . e($url) . '"' . $target . ' class="font-bold text-primary-500 hover:underline">' . $iconHtml . ' ' . e($matches[0]) . '</a></div>';
    }

    private function replaceTextNodeWithHtml(\DOMText $textNode, string $html, \DOMDocument $dom): void
    {
        $fragment = $dom->createDocumentFragment();
        @$fragment->appendXML($html);
        $textNode->parentNode->replaceChild($fragment, $textNode);
    }

    private function getAutoLinksForContext($context)
    {
        $cacheKey = $this->getCacheKeyForContext($context);
        return Cache::tags(['auto_links'])->remember($cacheKey, now()->addMinutes(60), function () use ($context) {
            $query = AutoLink::where('is_enabled', true);

            if ($context) {
                $query->where(function ($q) use ($context) {
                    $q->whereDoesntHave('scopes');

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
                            $subQuery->where('scopeable_type', (new Folder)->getMorphClass())->whereIn('scopeable_id', $folderIds);
                        });
                    }
                });
            }

            return $query->orderBy('priority', 'asc')->get();
        });
    }

    protected function getCacheKeyForContext($context): string
    {
        if ($context instanceof Folder) {
            return 'auto_links_folder_' . $context->id;
        }
        if ($context instanceof LedgerDefine) {
            return 'auto_links_ledger_define_' . $context->id;
        }
        if ($context instanceof Ledger) {
            if ($context->define && $context->define->folder) {
                return 'auto_links_folder_' . $context->define->folder->id;
            }
        }
        return 'auto_links_global';
    }
}
