# Issue #205 パフォーマンス調査レトロスペクティブ

**作成日**: 2026-05-05
**対象 Issue**: [#205](https://github.com/torinky/LedgerLeap/issues/205)
**調査対象**: `auto_number` カラムの AutoLink 処理でスパイクが発生するケース

---

## 調査フロー

```
1. HAR + パフォーマンスログ分析
   └─ analyze_perf_log.py で auto_number スパイクを検出

2. 実データ確認
   └─ DBから ledger_id=2, col_id=8 の定義と実値を確認

3. ベンチマーク実行
   └─ AutoLinkService::convert() の単体計測
   └─ マッチ数別の処理時間を計測

4. ボトルネック特定
   └─ Blade::render() のマッチ単位呼び出しが主因

5. 最適化実装
   └─ アイコンHTMLキャッシュ追加
   └─ 90%削減を達成
```

---

## 原因

`AutoLinkService::createCustomLink()` 内で、マッチごとに `Blade::render()` を呼び出していた。

```php
// 問題のコード
$iconHtml = Blade::render("<x-mary-icon name='{$iconName}' class='inline-block h-4 w-4 mr-1 -mt-1' />");
```

- 1回あたり **1.17ms**
- マッチ数に比例して線形増加
- 100マッチで **130ms**、200マッチで **270ms**

---

## 再現条件

テキスト中に `auto_number` 形式の文字列が多数含まれる場合：
- `DAILY-0001`, `EXP-0001`, `INSP-0001` など
- 営業日報の「日報番号」カラムなど、テキスト中で他のレコードを参照するケース

---

## 対策

`AutoLinkService` にアイコンHTMLキャッシュを追加：

```php
private static array $iconHtmlCache = [];

private function getCachedIconHtml(string $iconName): string
{
    if (! isset(self::$iconHtmlCache[$iconName])) {
        self::$iconHtmlCache[$iconName] = Blade::render("<x-mary-icon name='{$iconName}' ... />");
    }
    return self::$iconHtmlCache[$iconName];
}
```

---

## 性能改善結果

| マッチ数 | 改善前 | 改善後 | 削減率 |
|---------|--------|--------|--------|
| 1 | 1.97ms | 0.64ms | 67% |
| 10 | 14.33ms | 1.84ms | 87% |
| 50 | 65.46ms | 7.41ms | 89% |
| **100** | **129.77ms** | **13.11ms** | **90%** |
| **200** | **267.41ms** | **25.44ms** | **90%** |

---

## 学び

### 良かったこと
- `analyze_perf_log.py` で迅速にスパイクを検出できた
- ベンチマークスクリプトで原因を特定できた
- 最小限の変更（15行）で大きな改善を実現

### 悪かったこと
- 最初は正規表現のバックトラックを疑ったが、実際は `Blade::render()` が原因
- `preg_match` の計測で時間を使ったが、実際は問題がないことが判明

### 上書き指示されたこと
- スキルに従って調査 → 実装 → 報告の流れを守った
- Issue ボディを随時更新して最新の状態を保持

---

## 再利用可能なパターン

1. **パフォーマンスログ + HAR の組み合わせ分析**
   - `analyze_perf_log.py` でボトルネックを特定
   - HAR の `wait ≈ total` でサーバサイド処理が主因を確認

2. **ベンチマーク駆動の原因特定**
   - マッチ数別の計測で線形増加を検出
   - 各処理（正規表現、DOM操作、Blade）を分離して計測

3. **Blade::render() のキャッシュ化**
   - ループ内で `Blade::render()` を呼ぶ場合はキャッシュを検討
   - 静的なアイコンHTMLはリクエスト内で再利用可能

---

## 関連ファイル

- `app/Services/AutoLinkService.php`
- `scripts/benchmark_autolink.php`
- `scripts/benchmark_autolink_detailed.php`
- `docs/harnesses/browser-har-analysis/scripts/analyze_perf_log.py`

## 証跡

- [Sprint 1 完了報告](https://github.com/torinky/LedgerLeap/issues/205#issuecomment-4376457806)
- [Sprint 2 完了報告](https://github.com/torinky/LedgerLeap/issues/205#issuecomment-4376509003)

## Freshness

- status: confirmed
- last_confirmed_at: 2026-05-05
- recheck_after: 90d
- recheck_trigger: AutoLinkService の変更、Blade コンポーネントの追加、パフォーマンスログの新たなスパイク検出
