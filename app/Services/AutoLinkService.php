<?php

namespace App\Services;

use App\Models\AutoLink;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

class AutoLinkService
{
    /** @var array<int, Collection> リクエスト内フォルダ子孫キャッシュ */
    private array $folderDescendantsCache = [];

    public function __construct(
        private HtmlProcessorService $htmlProcessorService,
        private AutoNumberPatternService $autoNumberPatternService,
    ) {}

    /**
     * テキストを自動リンクに変換する
     *
     * @param  string  $text  変換対象のHTML文字列
     * @param  ColumnDefine|null  $column  オプション。ColumnDefine オブジェクト。auto_number タイプの場合に特別処理を行うために使用
     * @param  mixed  $context  オプション。適用範囲を絞り込むためのコンテキスト情報（例: Folder モデルのインスタンス、LedgerDefine モデルのインスタンスなど）
     * @return string 変換後のHTML文字列
     */
    public function convert(string $text, ?ColumnDefine $column = null, $context = null, ?string $highlight = null): string
    {
        if (empty($text)) {
            return '';
        }

        return $this->htmlProcessorService->processTextNodes(
            $text,
            function (\DOMText $textNode, \DOMDocument $dom) use ($context, $highlight) {
                $originalText = $textNode->nodeValue;

                // カスタム定義（仮想リンクを含む）によるリンク変換
                $autoLinks = $this->getAutoLinksForContext($context);
                if ($autoLinks->isEmpty()) {
                    return;
                }

                $this->applyCustomLinks($textNode, $autoLinks, $dom, $highlight);
            }
        );
    }

    /**
     * 自動ナンバリングカラムの設定から、パターンマッチング用の正規表現を生成する
     *
     * @param  object  $options  auto_number カラムの options (prefix, digits, revision)
     * @param  bool  $isUnique  unique フラグ
     * @return string 正規表現パターン
     */
    private function generateAutoNumberPattern(object $options, bool $isUnique): string
    {
        return $this->autoNumberPatternService->generatePattern($options, $isUnique);
    }

    private function getVirtualAutoNumberLinks(): Collection
    {
        // テナントIDをキャッシュキーに含めてテナント間の混在を防ぐ
        $tenantId = tenant()?->id ?? 'global';
        $cacheKey = "auto_links_virtual_auto_numbers:{$tenantId}";

        return Cache::tags(['auto_links'])->remember($cacheKey, now()->addMinutes(60), function () {
            $virtualLinks = collect();

            // AutoNumberPatternService からパターン情報を取得
            $patterns = $this->autoNumberPatternService->getPatterns();

            foreach ($patterns as $entry) {
                // 仮想 AutoLink オブジェクトを生成
                $virtualLink = new AutoLink([
                    'label' => "自動リンク: {$entry['define_title']} - {$entry['column_name']}",
                    'pattern' => $entry['pattern'],
                    'url_template' => '/l/$1',
                    'priority' => -1000, // 最高優先度（負の値で既存より優先）
                    'is_enabled' => true,
                    'open_in_new_tab' => true,
                    'link_type' => 'default',
                ]);

                // データベースに保存しないため、id は設定しない
                $virtualLinks->push($virtualLink);
            }

            return $virtualLinks;
        });
    }

    private function applyCustomLinks(\DOMText $textNode, $autoLinks, \DOMDocument $dom, ?string $highlight = null): void
    {
        $currentNode = $textNode;

        foreach ($autoLinks as $autoLink) {
            $pattern = $autoLink->pattern.(str_contains($autoLink->pattern, 'u') ? '' : 'u');
            $text = $currentNode->nodeValue;

            // まずマッチがあるかチェック
            if (! preg_match($pattern, $text)) {
                continue;
            }

            // マッチがあれば分割処理を実行
            $parts = preg_split($pattern, $text, -1, PREG_SPLIT_DELIM_CAPTURE);

            $fragment = $dom->createDocumentFragment();
            $matches = [];
            preg_match_all($pattern, $text, $matches, PREG_SET_ORDER);
            $matchIndex = 0;

            foreach ($parts as $part) {
                // 空文字列はスキップ
                if ($part === '') {
                    continue;
                }

                if ($matchIndex < count($matches) && $part === $matches[$matchIndex][0]) {
                    $linkHtml = $this->createCustomLink($autoLink, $matches[$matchIndex], $highlight);
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
                break;
            }
        }
    }

    private function createCustomLink(AutoLink $autoLink, array $matches, ?string $highlight = null): string
    {
        $url = $autoLink->url_template;
        for ($i = 1, $iMax = count($matches); $i < $iMax; $i++) {
            $url = str_replace('$'.$i, urlencode($matches[$i]), $url);
        }

        if (! empty($highlight)) {
            $url = $this->appendHighlightToLookupUrl($url, $highlight);
        }

        // リンク先テナントが指定されている場合のみ、完全なURLに書き換える
        if ($autoLink->tenant_id && str_starts_with($url, '/l/')) {
            $tenant = $autoLink->tenant;
            if ($tenant && $domain = $tenant->domains->first()?->domain) {
                $path = ltrim($url, '/');
                $protocol = parse_url(config('app.url'), PHP_URL_SCHEME) ?? 'http';
                $url = $protocol.'://'.$domain.'/'.$path;
            }
        }

        // 仮想リンク（tenant_idがnull）の場合、ベースURLを適用
        // これにより、テナント横断検索ルート /l/{query} が完全なURLになる
        if (! $autoLink->tenant_id && str_starts_with($url, '/')) {
            $baseUrl = config('ledgerleap.auto_links.base_url');
            if ($baseUrl) {
                // ベースURLが設定されている場合、完全なURLを生成
                $url = rtrim($baseUrl, '/').$url;
            }
            // baseUrlがnullの場合は相対URLのまま（サブドメイン方式用）
        }

        $target = $autoLink->open_in_new_tab ? ' target="_blank"' : '';
        $iconName = config('ledgerleap.auto_links.link_types.'.$autoLink->link_type.'.icon', 'o-link');
        $tooltip = $autoLink->label;
        $iconHtml = Blade::render("<x-mary-icon name='{$iconName}' class='inline-block h-4 w-4 mr-1 -mt-1' />");

        return '<div class="tooltip mx-2" data-tip="'.e($tooltip).'"><a href="'.e($url).'"'.$target.' class="font-bold text-primary-500 hover:underline">'.$iconHtml.' '.e($matches[0]).'</a></div>';
    }

    private function appendHighlightToLookupUrl(string $url, string $highlight): string
    {
        $parts = parse_url($url);

        if ($parts === false) {
            return $url;
        }

        $path = $parts['path'] ?? '';
        if ($path !== '/l' && ! str_starts_with($path, '/l/')) {
            return $url;
        }

        $baseHost = parse_url(config('ledgerleap.auto_links.base_url', config('app.url')), PHP_URL_HOST);
        if (isset($parts['host']) && $baseHost && $parts['host'] !== $baseHost) {
            return $url;
        }

        $query = [];
        if (! empty($parts['query'])) {
            parse_str($parts['query'], $query);
        }
        $query['highlight'] = $highlight;

        $rebuilt = '';
        if (isset($parts['scheme'])) {
            $rebuilt .= $parts['scheme'].'://';
        }
        if (isset($parts['host'])) {
            $rebuilt .= $parts['host'];
        }
        if (isset($parts['port'])) {
            $rebuilt .= ':'.$parts['port'];
        }

        $rebuilt .= $path;

        $queryString = http_build_query($query);
        if ($queryString !== '') {
            $rebuilt .= '?'.$queryString;
        }

        if (! empty($parts['fragment'])) {
            $rebuilt .= '#'.$parts['fragment'];
        }

        return $rebuilt;
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

        // フォルダIDを事前に取得（リクエスト内キャッシュを使用）
        $folder = null;
        $folderIds = null;

        if ($context) {
            if ($context instanceof Folder) {
                $folder = $context;
            } elseif ($context instanceof LedgerDefine) {
                $folder = $context->folder;
            } elseif ($context instanceof Ledger) {
                $folder = $context->define->folder;
            }

            if ($folder) {
                // リクエスト内キャッシュを使用
                if (! isset($this->folderDescendantsCache[$folder->id])) {
                    $this->folderDescendantsCache[$folder->id] = $folder->descendantsAndSelf($folder)->pluck('id');
                }
                $folderIds = $this->folderDescendantsCache[$folder->id];
            }
        }

        return Cache::tags(['auto_links'])->remember($cacheKey, now()->addMinutes(60), function () use ($context, $folderIds) {
            // 仮想 auto_number リンクを取得
            $virtualLinks = $this->getVirtualAutoNumberLinks();

            // 既存のカスタム定義を取得
            $query = AutoLink::where('is_enabled', true);

            if ($context && $folderIds) {
                $query->where(function ($q) use ($folderIds) {
                    $q->whereDoesntHave('scopes');
                    $q->orWhereHas('scopes', function ($subQuery) use ($folderIds) {
                        $subQuery->where('scopeable_type', (new Folder)->getMorphClass())->whereIn('scopeable_id', $folderIds);
                    });
                });
            }

            $customLinks = $query->with('tenant')->orderBy('priority', 'asc')->get();

            // 仮想リンクとカスタムリンクを結合（仮想リンクが優先）
            return $virtualLinks->concat($customLinks);
        });
    }

    protected function getCacheKeyForContext($context): string
    {
        if ($context instanceof Folder) {
            return 'auto_links_folder_'.$context->id;
        }
        if ($context instanceof LedgerDefine) {
            // LedgerDefineの場合もフォルダベースのキャッシュキーを使用
            // 同じフォルダに属する台帳定義は同じAutoLinkを共有
            if ($context->folder) {
                return 'auto_links_folder_'.$context->folder->id;
            }
        }
        if ($context instanceof Ledger) {
            if ($context->define && $context->define->folder) {
                return 'auto_links_folder_'.$context->define->folder->id;
            }
        }

        return 'auto_links_global';
    }
}
