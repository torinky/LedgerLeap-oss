# CSV エクスポート設計書

## 概要

LedgerLeap の台帳データ CSV エクスポート機能のセキュリティ設計、キャッシュ戦略、およびファイル命名規則についてまとめる。

## セキュリティ設計

### 認可チェック付きダウンロード

CSV ファイルは `public` disk に保存されるが、直接アクセスさせない。すべてのダウンロードは `LedgerExportDownloadController` を経由し、`Gate::authorize('ledgerView', $ledgerDefine)` で閲覧権限を確認する。

```
User ──► GET /{tenant}/ledger/export/{ledgerDefineId}/download/{filename}
              ▼
         LedgerExportDownloadController
              ▼
         Gate::authorize('ledgerView', $ledgerDefine)
              ▼
         Storage::disk('public')->download()
```

- 認可失敗 → 403 Forbidden
- ファイル不在 → 404 Not Found
- 認可成功 → CSV を `Content-Disposition: attachment` で返却

### ダウンロードファイル名

ストレージ上のファイル名（`ledger-export-{id}-{hash}.csv`）と、ユーザーに提示するダウンロード名は別々に管理する。

ダウンロード名の構成：

```
{台帳名}-{フォルダ階層}-{生成日時}.csv
```

- **台帳名**: `$ledgerDefine->title`
- **フォルダ階層**: ルートフォルダを除いた祖先フォルダを ` › ` で結合
  - 例：`取引 › 2024年度`
- **生成日時**: ファイルの `lastModified` を `Y-m-d_H-i-s` 形式で付与
  - エビデンスとして「いつのデータか」が一目で分かる

例：

```
取引管理-取引 › 2024年度-2024-05-10_14-30-22.csv
```

ファイル名に使用できない文字（`\`, `/`, `:`, `*`, `?`, `"`, `<`, `>`, `|`, `\0`）は `-` に置換し、200文字で切る。

## キャッシュ戦略

### ファイル命名規則（ストレージ側）

```
ledger-export-{ledgerDefineId}-{hash}.csv
hash = md5(json_encode([keywords, filter]))
```

同じ台帳定義 ID + 同じ検索条件なら同じファイル名が生成される。

### 再利用（重複生成の防止）

`Export::export()` は dispatch 前に `Storage::disk('public')->exists($filename)` を確認する。

- ファイルが存在 → `$exportFinished = true` を即セットし、バッチ dispatch をスキップ
- ファイルが不在 → 新規に `Bus::batch([new ExportJob(...)])` を dispatch

これにより、複数ユーザーが同じ検索条件でクリックしても、1回目だけ生成され、2回目以降は即ダウンロード可能になる。

### 無効化（データ鮮度の担保）

台帳データや定義が変更された場合、対象台帳定義の全 CSV を削除する。

| トリガー | Observer | 対象 |
|---|---|---|
| 台帳レコード作成 | `LedgerObserver::created` | `$ledger->ledger_define_id` の全 CSV |
| 台帳レコード更新（content / content_attached 変更） | `LedgerObserver::updated` | `$ledger->ledger_define_id` の全 CSV |
| 台帳レコード削除 | `LedgerObserver::deleted` | `$ledger->ledger_define_id` の全 CSV |
| カラム定義変更 | `LedgerDefineObserver::saved` | `$ledgerDefine->id` の全 CSV |
| 台帳定義削除 | `LedgerDefineObserver::deleted` | `$ledgerDefine->id` の全 CSV |

削除対象は `ExportCacheService::clearByLedgerDefineId()` で決定し、`ledger-export-{ledgerDefineId}-*.csv` にマッチするファイルをすべて削除する。

### 注意点

`Ledger::withoutEvents()` で保存される処理経路では Observer が発火しない。その場合、CSV の鮮度は「次回エクスポート時に上書きされる」という緩い保証に留まる。これは現状の RAG インデックス更新と同じパターン（Observer フック + ベストエフォート）で統一している。

## 関連ファイル

- `app/Http/Controllers/Ledger/LedgerExportDownloadController.php` — 認可チェック付きダウンロード
- `app/Services/Ledger/ExportCacheService.php` — ファイル名生成・存在チェック・一括削除
- `app/Livewire/Ledger/Export.php` — Livewire コンポーネント（再利用ロジック）
- `app/Jobs/Ledger/ExportJob.php` — CSV 生成ジョブ
- `app/Observers/LedgerObserver.php` — 台帳 CRUD 時のキャッシュ無効化
- `app/Observers/LedgerDefineObserver.php` — 定義変更時のキャッシュ無効化
