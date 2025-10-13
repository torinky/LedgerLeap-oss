<?php

namespace App\Services;

use App\Models\AutoLink;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Services\Util\HtmlProcessorService;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Cache;

class AutoLinkService
{
    public function __construct(private HtmlProcessorService $htmlProcessorService) {}

    /**
     * テキストを自動リンクに変換する
     *
     * @param  string  $text  変換対象のHTML文字列
     * @param  ColumnDefine|null  $column  オプション。ColumnDefine オブジェクト。auto_number タイプの場合に特別処理を行うために使用
     * @param  mixed  $context  オプション。適用範囲を絞り込むためのコンテキスト情報（例: Folder モデルのインスタンス、LedgerDefine モデルのインスタンスなど）
     * @return string 変換後のHTML文字列
     */
    public function convert(string $text, ?ColumnDefine $column = null, $context = null): string
    {
        if (empty($text)) {
            return '';
        }

        return $this->htmlProcessorService->processTextNodes(
            $text,
            function (\DOMText $textNode, \DOMDocument $dom) use ($context) {
                $originalText = $textNode->nodeValue;

                // カスタム定義（仮想リンクを含む）によるリンク変換
                $autoLinks = $this->getAutoLinksForContext($context);
                if ($autoLinks->isEmpty()) {
                    return;
                }

                $this->applyCustomLinks($textNode, $autoLinks, $dom);
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
        $prefix = preg_quote($options->prefix ?? '', '/');
        $digits = max(1, (int) ($options->digits ?? 3));
        $revision = preg_quote($options->revision ?? '', '/');

        // 数字部分: 指定桁数以上の数字にマッチ
        $numberPattern = '\d{'.$digits.',}';

        if ($isUnique) {
            // unique の場合、版記号は無視（任意の文字が続いても可）
            return '/('.$prefix.$numberPattern.'.*?)/u';
        } else {
            // unique でない場合、版記号まで厳密にマッチ
            if (! empty($revision)) {
                return '/('.$prefix.$numberPattern.$revision.')/u';
            } else {
                return '/('.$prefix.$numberPattern.')(?![0-9])/u'; // 後ろに数字が続かない
            }
        }
    }

    private function getVirtualAutoNumberLinks(): \Illuminate\Support\Collection
    {
        $cacheKey = 'auto_links_virtual_auto_numbers';

        return Cache::tags(['auto_links'])->remember($cacheKey, now()->addMinutes(60), function () {
            $virtualLinks = collect();

            // 全テナントの台帳定義を取得（マルチテナント対応）
            $ledgerDefines = LedgerDefine::with('folder')->get();

            foreach ($ledgerDefines as $define) {
                foreach ($define->column_define as $column) {
                    if ($column->type !== 'auto_number') {
                        continue;
                    }

                    // パターン生成
                    $pattern = $this->generateAutoNumberPattern(
                        (object) $column->options,
                        $column->unique ?? false
                    );

                    // テナント横断検索ルートを使用（/l/{query}）
                    // このルートは全テナントを検索し、1件なら直接リダイレクト、複数件なら選択画面を表示
                    $urlTemplate = '/l/$1';

                    // 仮想 AutoLink オブジェクトを生成
                    $virtualLink = new AutoLink([
                        'label' => "自動リンク: {$define->title} - {$column->name}",
                        'pattern' => $pattern,
                        'url_template' => $urlTemplate,
                        'priority' => -1000, // 最高優先度（負の値で既存より優先）
                        'is_enabled' => true,
                        'open_in_new_tab' => true, // 別ウィンドウで開く（デバッグのため戻す）
                        'link_type' => 'default',
                    ]);

                    // データベースに保存しないため、id は設定しない
                    $virtualLinks->push($virtualLink);
                }
            }

            return $virtualLinks;
        });
    }

    private function applyCustomLinks(\DOMText $textNode, $autoLinks, \DOMDocument $dom): void
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
                break;
            }
        }
    }

    private function createCustomLink(AutoLink $autoLink, array $matches): string
    {
        $url = $autoLink->url_template;
        for ($i = 1, $iMax = count($matches); $i < $iMax; $i++) {
            $url = str_replace('$'.$i, urlencode($matches[$i]), $url);
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
            // 仮想 auto_number リンクを取得
            $virtualLinks = $this->getVirtualAutoNumberLinks();

            // 既存のカスタム定義を取得
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
            return 'auto_links_ledger_define_'.$context->id;
        }
        if ($context instanceof Ledger) {
            if ($context->define && $context->define->folder) {
                return 'auto_links_folder_'.$context->define->folder->id;
            }
        }

        return 'auto_links_global';
    }
}
