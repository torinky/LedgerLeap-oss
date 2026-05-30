<?php

declare(strict_types=1);

namespace App\Services\Util;

use DOMDocument;
use DOMXPath;

class HtmlProcessorService
{
    /**
     * HTMLフラグメント内のテキストノードをコールバック関数で処理する
     *
     * @param  string  $htmlFragment  処理対象のHTML文字列
     * @param  callable  $callback  各テキストノードに適用するコールバック関数
     * @return string 処理後のHTML文字列
     */
    public function processTextNodes(string $htmlFragment, callable $callback): string
    {
        if (empty(trim($htmlFragment))) {
            return $htmlFragment;
        }

        $dom = new DOMDocument;

        // 部分的なHTMLを正しく扱うための設定
        // UTF-8エンコーディングと、HTMLエンティティの文字化け対策
        $encodedHtml = mb_convert_encoding($htmlFragment, 'HTML-ENTITIES', 'UTF-8');
        // html, bodyタグの自動補完を抑制
        @$dom->loadHTML('<div>'.$encodedHtml.'</div>', LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);

        $xpath = new DOMXPath($dom);
        // テキストノードのみを検索対象とする
        $textNodes = $xpath->query('//text()');

        foreach ($textNodes as $textNode) {
            // コールバック関数を適用してノードを置換
            $callback($textNode, $dom);
        }

        // Get the div wrapper we added
        $div = $dom->getElementsByTagName('div')->item(0);
        $innerHtml = '';
        if ($div) {
            foreach ($div->childNodes as $child) {
                $innerHtml .= $dom->saveHTML($child);
            }
        }

        return $innerHtml;
    }
}
