# キーワード検索と画像プレビューのパフォーマンス測定ガイド

**作成日:** 2025年12月31日  
**目的:** キーワード検索と画像プレビューの問題を解決するための測定  
**親ドキュメント:** [Phase 5詳細計画](./2025-12-30_phase5_detailed_plan.md)

---

## 📊 実装完了

### 新しいパフォーマンスメトリクス

以下の測定機能を追加しました：

1. **search_keyword_update** - サーバー側の検索キーワード更新処理時間
2. **search_render** - フロントエンド側の検索結果レンダリング時間
3. **image_preview_load** - 画像プレビューの読み込み時間

---

## 🔍 測定方法

### 準備

1. **パフォーマンス測定を有効化（既に有効）:**
```bash
# .envを確認
grep PERFORMANCE .env
```

期待される設定:
```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=both
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=true
PERFORMANCE_METRIC_SEARCH=true
PERFORMANCE_METRIC_IMAGE_PREVIEW=true
```

2. **ログファイルをクリア:**
```bash
./vendor/bin/sail exec laravel-1 rm -f storage/logs/performance_stats.json
./vendor/bin/sail exec laravel-1 touch storage/logs/performance_stats.json
```

3. **ブラウザコンソールを開く:**
- Chrome DevTools（F12）
- Console タブ

---

## 📝 測定手順

### A. キーワード検索のパフォーマンス測定

#### 手順

1. **FileInspectorを開く**
   - 任意のファイルをクリック

2. **検索キーワードを入力**
   - 検索ボックスに「test」と入力
   - 0.5秒待つ

3. **ブラウザコンソールを確認**

期待されるログ:
```javascript
[FileInspector Performance] Search started { keyword: "test" }
[FileInspector Performance] Search completed { duration_ms: "XXX.XX", keyword: "test" }
```

4. **Laravelログを確認**
```bash
./vendor/bin/sail logs -f | grep "search"
```

期待されるログ:
```
[FileInspector Performance] search_keyword_update {"metric":"search_keyword_update","duration_ms":X.XX,"file_id":16,"tab":"content","keyword":"test","keyword_length":4}
[FileInspector Performance] search_render {"metric":"search_render","duration_ms":XXX.XX,"file_id":16,"tab":"content","keyword":"test","keyword_length":4}
```

#### 測定データ

| 項目 | 実測値 | 期待値 | 判定 |
|-----|-------|-------|------|
| search_keyword_update（サーバー） | ___ms | <50ms | - |
| search_render（フロントエンド） | ___ms | <500ms | - |
| 合計体感時間 | ___ms | <1000ms | - |

---

### B. 画像プレビューのパフォーマンス測定

#### 手順

1. **画像ファイルを開く（1回目）**
   - FileInspectorで画像ファイルを選択

2. **ブラウザコンソールを確認**

期待されるログ:
```javascript
[FileInspector Performance] Image preview loaded {
  duration_ms: "XXXX.XX",
  url: "http://localhost/...",
  cached: false
}
```

3. **ドロワーを閉じる**

4. **同じファイルを再度開く（2回目）**

期待されるログ:
```javascript
[FileInspector Performance] Image preview (cache hit) {
  url: "http://localhost/...",
  cached: true
}
```

5. **Laravelログを確認**
```bash
./vendor/bin/sail logs -f | grep "image_preview"
```

期待されるログ（1回目のみ）:
```
[FileInspector Performance] image_preview_load {"metric":"image_preview_load","duration_ms":XXXX.XX,"file_id":16,"tab":"content","url":"...","from_cache":false}
```

#### 測定データ

| 項目 | 1回目 | 2回目 | 期待値 | 判定 |
|-----|-------|-------|-------|------|
| 画像読み込み時間 | ___ms | （キャッシュヒット） | <100ms | - |
| ローディング表示 | ___（有/無） | - | 有 | - |
| sessionStorage保存 | ___（有/無） | - | 有 | - |

---

## 📊 JSON統計ファイルでの確認

### すべての測定データを一覧表示

```bash
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.'
```

### 検索関連のみ抽出

```bash
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.[] | select(.metric | startswith("search"))'
```

### 画像プレビュー関連のみ抽出

```bash
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.[] | select(.metric == "image_preview_load")'
```

### 平均値の計算

```bash
# 検索レンダリングの平均時間
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "search_render") | .duration_ms] | add / length'

# 画像読み込みの平均時間
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "image_preview_load") | .duration_ms] | add / length'
```

---

## 🎯 問題の特定

### 検索が遅い場合

**チェックポイント:**

1. **search_keyword_updateが遅い（>50ms）**
   - 原因: サーバー側の処理が重い
   - 対策: キャッシュの実装を確認

2. **search_renderが遅い（>500ms）**
   - 原因: Livewireのレンダリングが重い
   - 対策: フロントエンドのみの検索実装を検討

3. **ブラウザコンソールのログが出ない**
   - 原因: Alpine.jsの`$watch`が動作していない
   - 対策: Bladeテンプレートの構文を確認

### 画像プレビューが遅い場合

**チェックポイント:**

1. **2回目もログが出る（キャッシュヒットしない）**
   - 原因: sessionStorageが機能していない
   - 確認: DevTools → Application → Session Storage

2. **1回目の読み込みが遅い（>2000ms）**
   - 原因: 画像ファイルが大きい、ネットワークが遅い
   - 対策: サムネイル機能の確認

3. **ローディングスピナーが表示されない**
   - 原因: Alpine.jsの状態管理が動作していない
   - 対策: `imgLoaded`変数の動作を確認

---

## 🔧 トラブルシューティング

### ログが出力されない

```bash
# 設定を確認
./vendor/bin/sail artisan config:clear
./vendor/bin/sail artisan tinker
>>> config('ledgerleap.performance.enabled')
=> true
>>> config('ledgerleap.performance.metrics.search_render')
=> true
```

### ブラウザコンソールログが出ない

1. F12 → Console
2. フィルターが有効になっていないか確認
3. "FileInspector Performance"で検索

### Laravelログが出ない

```bash
# ログファイルの確認
./vendor/bin/sail exec laravel-1 tail -f storage/logs/laravel-$(date +%Y-%m-%d).log | grep "FileInspector Performance"
```

---

## 📋 測定結果テンプレート

### キーワード検索

**測定日時:** ___  
**ファイルID:** ___  
**キーワード:** ___

| メトリクス | 実測値 | 期待値 | 判定 | 備考 |
|-----------|-------|-------|------|------|
| search_keyword_update | ___ms | <50ms | - | サーバー側 |
| search_render | ___ms | <500ms | - | フロントエンド |
| 体感速度 | ___（速い/遅い） | 速い | - | ユーザー評価 |
| ローディングUI | ___（有/無） | 有 | - | スピナー表示 |

**問題:** ___  
**原因:** ___  
**対策:** ___

---

### 画像プレビュー

**測定日時:** ___  
**ファイルID:** ___  
**画像URL:** ___

| メトリクス | 1回目 | 2回目 | 期待値 | 判定 | 備考 |
|-----------|-------|-------|-------|------|------|
| image_preview_load | ___ms | N/A | <2000ms | - | 初回読み込み |
| キャッシュヒット | No | Yes | Yes | - | 2回目は即座 |
| sessionStorage | ___（有/無） | - | 有 | - | - |
| ローディングUI | ___（有/無） | - | 有 | - | スピナー表示 |

**問題:** ___  
**原因:** ___  
**対策:** ___

---

## 🚀 次のステップ

測定完了後:

1. **データを記録**
2. **問題箇所を特定**
3. **対策を検討**
4. **修正を実施**
5. **再測定して効果を確認**

---

**作成日:** 2025年12月31日  
**更新日:** 2025年12月31日  
**測定実施者:** ___

