# WBS 5.2.0 実測ガイド

**作成日:** 2025年12月31日  
**対象:** FileInspectorのパフォーマンス問題特定  
**親ドキュメント:** [Phase 5詳細計画](./2025-12-30_phase5_detailed_plan.md)

---

## 準備完了

### ✅ 測定機能の有効化

```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=both
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=true
```

### ✅ 追加ログの実装

以下のログが追加されています：

**FileInspector.php:**
- `updatedSearchKeyword()`: 検索キーワード更新時のログ
- `hasKeywordHit()`: 検索実行時のログ（キャッシュヒット/ミス）
- `getPreviewText()`: プレビューテキスト取得時のログ

---

## 実測手順

### 1. ログの確認準備

**ターミナル1（Laravelログ）:**
```bash
./vendor/bin/sail logs -f | grep -E "(FileInspector Performance|Search Debug)"
```

**ターミナル2（JSON統計ファイル）:**
```bash
# 初期化
./vendor/bin/sail exec laravel-1 rm -f storage/logs/performance_stats.json
./vendor/bin/sail exec laravel-1 touch storage/logs/performance_stats.json
```

### 2. ブラウザの準備

1. Chrome DevTools（F12）を開く
2. **Console タブ**: ブラウザログを確認
3. **Network タブ**: 画像読み込みを確認
4. **Performance タブ**: プロファイリング用

---

## 測定 A: 画像プレビュー（2回目の速度）

### 手順

1. **1回目の測定:**
   - FileInspectorで画像ファイルを開く
   - Network タブで画像リクエストを確認
   - 時間を記録: ___ms
   - sessionStorage を確認（Application → Session Storage）
   - キー `img-loaded-{URL}` の値: ___

2. **ドロワーを閉じる**

3. **2回目の測定:**
   - 同じファイルを再度開く
   - Network タブで画像リクエストを確認
   - 時間を記録: ___ms
   - ステータスコード: `200` or `304`?
   - サイズ: `from cache` と表示される?

### 確認項目

- [ ] sessionStorageにキーが保存されているか？
- [ ] 2回目の画像URLは1回目と同じか？
- [ ] ブラウザキャッシュが効いているか（304 or from cache）?
- [ ] Livewireの再レンダリングでsessionStorageがクリアされていないか？

### 予想される問題

**問題1:** sessionStorageにキーがない
- **原因:** Alpine.jsの`init()`が動作していない
- **対策:** Livewireのキー設定を確認

**問題2:** URLが毎回変わる
- **原因:** `previewUrl`にタイムスタンプが含まれている
- **対策:** URLの生成ロジックを修正

**問題3:** ブラウザキャッシュが効いていない
- **原因:** HTTPヘッダーの設定
- **対策:** キャッシュヘッダーを追加

---

## 測定 B: テキスト検索（速度とUIフィードバック）

### 手順

1. **Performance タブでプロファイリング開始**

2. **検索キーワードを入力:** "テスト"

3. **ログを確認:**
   ```
   [Search Debug] Keyword updated
   [Search Debug] hasKeywordHit (cache miss)
   [Search Debug] hasKeywordHit (cache hit)  // ← 2回目は出るはず
   ```

4. **ローディングスピナーの確認:**
   - 検索入力時にスピナーが表示されるか？
   - 表示時間: ___ms

5. **検索キーワードを変更:** "別のキーワード"

6. **再度ログを確認:**
   ```
   [Search Debug] Keyword updated
   [Search Debug] hasKeywordHit (cache miss)  // ← キャッシュクリアされるはず
   ```

### 確認項目

- [ ] ローディングスピナーが表示されるか？
- [ ] `hasKeywordHit()`のキャッシュヒットが2回目に発生するか？
- [ ] キーワード変更時にキャッシュがクリアされるか？
- [ ] 検索時間（cache miss）: ___ms
- [ ] 検索時間（cache hit）: ___ms

### 予想される問題

**問題1:** ローディングスピナーが表示されない
- **原因:** Alpine.jsの`$watch`が動作していない
- **対策:** Livewireのバインディング確認

**問題2:** キャッシュが効いていない
- **原因:** `wire:model.live`が毎回サーバーリクエストを送っている
- **対策:** デバウンス時間の調整、または`wire:model.lazy`に変更

**問題3:** 検索が7-8秒かかる
- **原因:** `getPreviewText()`が重い、ハイライト処理が重い
- **対策:** テキスト長の制限、ハイライトの最適化

---

## 測定 C: タブ切り替え（activitiesのクエリ）

### 手順

1. **JSON統計ファイルをクリア**

2. **FileInspectorを開く**

3. **各タブに切り替え:**
   - Content → Details
   - Details → History
   - History → Permissions

4. **ブラウザコンソールでログを確認:**
   ```
   [FileInspector Performance] Tab switch: content -> details, XX.XX ms
   [FileInspector Performance] Tab switch: details -> history, XX.XX ms
   ```

5. **JSON統計ファイルを確認:**
   ```bash
   ./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.[] | select(.metric == "tab_switch")'
   ```

### 確認項目

- [ ] Contentタブ切り替え時間: ___ms
- [ ] Detailsタブ切り替え時間: ___ms
- [ ] **Historyタブ切り替え時間: ___ms** ← ここが遅いはず
- [ ] Permissionsタブ切り替え時間: ___ms

### Laravel Debugbarでクエリ確認（オプション）

```bash
# Debugbarを一時的に有効化
./vendor/bin/sail artisan debugbar:enable
```

ブラウザで各タブを開いてクエリ数を確認：
- Content: ___回
- Details: ___回
- History: ___回 ← activitiesクエリが含まれる
- Permissions: ___回

### 予想される問題

**問題1:** Historyタブが遅い
- **原因:** `activities`が`loadData()`で毎回読み込まれている
- **対策:** 遅延ロード（Historyタブ選択時のみ読み込み）

**問題2:** N+1問題
- **原因:** `activities.causer`のEager Loading不足
- **対策:** `with('activities.causer')`の確認

---

## 測定結果の記録

### 画像プレビュー

| 項目 | 1回目 | 2回目 | 目標 | 判定 |
|-----|-------|-------|------|------|
| 読み込み時間 | ___ms | ___ms | <100ms | - |
| sessionStorage | ___（有/無） | - | 有 | - |
| ブラウザキャッシュ | ___（200/304） | ___（200/304） | 304 | - |

### テキスト検索

| 項目 | 実測値 | 目標 | 判定 |
|-----|-------|------|------|
| ローディングUI表示 | ___（有/無） | 有 | - |
| 検索時間（cache miss） | ___ms | <500ms | - |
| 検索時間（cache hit） | ___ms | <50ms | - |
| キャッシュ動作 | ___（有/無） | 有 | - |

### タブ切り替え

| タブ | 時間 | クエリ数 | 目標時間 | 目標クエリ | 判定 |
|-----|------|---------|---------|-----------|------|
| Content | ___ms | ___回 | <100ms | - | - |
| Details | ___ms | ___回 | <100ms | - | - |
| History | ___ms | ___回 | <100ms | 削減必要 | - |
| Permissions | ___ms | ___回 | <100ms | - | - |

---

## 次のステップ

測定結果を元に問題原因を特定し、`2025-12-31_phase5-2-0_performance_analysis_report.md` を作成します。

**レポートに含める内容:**
1. 各測定項目の実測値
2. 問題の根本原因
3. 対策案（WBS 5.2.1, 5.2.2, 5.2.3への引き継ぎ）
4. 優先度付け

---

**測定実施日:** ___  
**測定者:** ___  
**環境:** Laravel Sail / Chrome ___

