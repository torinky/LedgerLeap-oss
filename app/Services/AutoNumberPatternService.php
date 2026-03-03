<?php

namespace App\Services;

use App\Models\LedgerDefine;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * auto_number カラムのパターン生成・収集サービス
 *
 * AutoLinkService と RelatedLedgers の両方から共用される。
 * 全テナントの auto_number カラム定義からパターンを収集しキャッシュする。
 */
class AutoNumberPatternService
{
    /**
     * auto_number カラムの設定から、パターンマッチング用の正規表現を生成する
     *
     * @param  object  $options  auto_number カラムの options (prefix, digits, revision)
     * @param  bool  $isUnique  unique フラグ
     * @return string 正規表現パターン
     */
    public function generatePattern(object $options, bool $isUnique): string
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

    /**
     * 全テナントの auto_number カラム定義からパターン情報のコレクションを返す
     *
     * 60分キャッシュ済み。RelatedLedgers のパターンBマッチングで利用する。
     *
     * @return Collection<int, array{pattern: string, column_name: string, define_id: int, define_title: string}>
     */
    public function getPatterns(): Collection
    {
        // テナントIDをキャッシュキーに含めてテナント間の混在を防ぐ
        $tenantId = tenant()?->id ?? 'global';
        $cacheKey = "auto_number_patterns:{$tenantId}";

        return Cache::tags(['auto_links'])->remember($cacheKey, now()->addMinutes(60), function () {
            $patterns = collect();

            // 全テナントの台帳定義を取得（マルチテナント対応）
            $ledgerDefines = LedgerDefine::with('folder')->get();

            foreach ($ledgerDefines as $define) {
                foreach ($define->column_define as $column) {
                    if ($column->type !== 'auto_number') {
                        continue;
                    }

                    $pattern = $this->generatePattern(
                        (object) $column->options,
                        $column->unique ?? false
                    );

                    $patterns->push([
                        'pattern' => $pattern,
                        'column_name' => $column->name,
                        'define_id' => $define->id,
                        'define_title' => $define->title,
                    ]);
                }
            }

            return $patterns;
        });
    }
}
