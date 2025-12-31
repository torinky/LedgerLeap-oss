# キーワード検索と画像プレビューのパフォーマンス分析レポート

**分析日:** 2025年12月31日  
**データソース:** performance_stats.json  
**総データ数:** 57件

---

## 🔴 重大な問題を発見

### キーワード検索のパフォーマンス問題

#### 測定データ

| タイムスタンプ | キーワード | search_keyword_update | search_render | 合計体感時間 |
|---------------|-----------|----------------------|---------------|-------------|
| 03:58:05 | "test" | 0ms | **500ms** | 500ms |
| 03:59:10 | "tes" | 0ms | **5276ms** | 5276ms |
| 03:59:13 | "test" | 0ms | **501ms** | 501ms |
| 03:59:29 | "tes" | 0ms | **5458ms** | 5458ms |
| 03:59:32 | "" (クリア) | 0ms | **512ms** | 512ms |
| 03:59:42 | "a" | 0ms | **4943ms** | 4943ms |
| 03:59:53 | "a b" | 0ms | **511ms** | 511ms |

#### 問題の特定

**パターン1: 異常に遅い検索（4943ms～5458ms）**
- "tes" → **5276ms**, **5458ms**
- "a" → **4943ms**
- **共通点:** 短いキーワード（1-3文字）

**パターン2: 正常な検索（500-528ms）**
- "test" → **500-501ms**
- "" (クリア) → **512-528ms**
- "a b" → **511ms**
- **共通点:** 4文字以上、または空文字

### 根本原因の仮説

#### 仮説1: Livewireの再レンダリングが重複している

検索が遅いケースを見ると：
```
03:59:10 - keyword_update: "test" → 0ms
03:59:10 - render: "tes" → 5276ms  ← 前の状態？
03:59:13 - render: "test" → 501ms
```

**タイムスタンプが同じ（03:59:10）** なのに、キーワードが異なる（"test" vs "tes"）

**原因:** 
- ユーザーが「test」と入力
- Livewireが2回レンダリングされている
  1. "tes"の状態でレンダリング（5276ms）← 遅い
  2. "test"の状態でレンダリング（501ms）← 正常

#### 仮説2: wire:model.change のデバウンスが機能していない

`wire:model.change`は入力完了後にサーバーリクエストを送るはずだが、**入力途中の状態でもリクエストが送られている**可能性。

#### 仮説3: Alpine.jsの$watchとLivewireの競合

- Alpine.jsの`$watch`が入力のたびに発火
- Livewireの`wire:model.change`も同時に発火
- 複数のレンダリングが競合

---

## 📊 ドロワー開閉のパフォーマンス

### 測定データ（最新20件）

| タイムスタンプ | ファイルID | タブ | 時間 | 判定 |
|---------------|----------|------|------|------|
| 03:57:11 | 16 | content | 2082ms | ⚠️ 遅い |
| 03:57:51 | 17 | details | 1768ms | ⚠️ 遅い |
| 03:58:38 | 15 | content | 1701ms | ⚠️ 遅い |
| 03:58:48 | 16 | content | 2648ms | ❌ 非常に遅い |
| 04:00:19 | 16 | history | 2536ms | ❌ 非常に遅い |
| 04:00:33 | 15 | history | 2010ms | ⚠️ 遅い |
| 04:00:43 | 15 | history | 1700ms | ⚠️ 遅い |
| 04:00:55 | 16 | history | 1826ms | ⚠️ 遅い |
| 04:01:09 | 16 | history | 1843ms | ⚠️ 遅い |
| 04:01:24 | 17 | history | 1705ms | ⚠️ 遅い |
| 04:01:35 | 17 | history | 1786ms | ⚠️ 遅い |

**平均:** 1955ms  
**最小:** 1700ms  
**最大:** 2648ms  
**目標:** 300ms以下  
**達成率:** 0%

**結論:** PHP 8.4警告を修正しても、ドロワー開閉時間は改善していない。

---

## 📊 タブ切り替えのパフォーマンス

### 測定データ

| From | To | 時間 | 判定 |
|------|-----|------|------|
| content | details | 8ms | ✅ 高速 |
| details | history | 24ms | ✅ 高速 |
| history | **permissions** | **6761ms** | ❌ 致命的 |
| permissions | content | 29ms | ✅ 高速 |
| content | details | 20ms | ✅ 高速 |
| details | permissions | 42ms | ✅ 高速 |
| permissions | content | 24ms | ✅ 高速 |
| content | details | 29ms | ✅ 高速 |
| details | content | 76ms | ✅ 高速 |
| content | details | 28ms | ✅ 高速 |
| details | content | 7ms | ✅ 高速 |
| content | history | 78ms | ✅ 高速 |

**Permissionsタブへの切り替えのみ6.7秒** - これは修正前と変わらず

---

## 🔍 画像プレビューのパフォーマンス

### 問題: ログが記録されていない

performance_stats.jsonに`image_preview_load`のデータが**1件もない**。

**原因の可能性:**
1. 画像ファイルを開いていない
2. Alpine.jsのコードが動作していない
3. `$wire.call('logPerformance', ...)`が失敗している

---

## 🎯 優先度付き問題リスト

### 🔴 最優先: キーワード検索の5秒遅延

**問題:** 
- 短いキーワード（1-3文字）で検索すると4943ms～5458msかかる
- 4文字以上だと500ms程度で正常

**原因（仮説）:**
1. Livewireの重複レンダリング
2. `wire:model.change`とAlpine.js `$watch`の競合
3. 入力途中の状態でサーバーリクエストが発生

**対策案:**
1. `wire:model.change`を`wire:model.blur`に変更（フォーカス外れた時のみ）
2. Alpine.jsの`$watch`のデバウンスを長くする（500ms → 1000ms）
3. Livewireのレンダリングをスキップする条件を追加

### 🔴 高: Permissionsタブの6.7秒遅延

**問題:** 依然として6761ms（PHP 8.4修正後も変わらず）

**対策案:**
- 権限チェックのロジックを最適化
- 遅延ロードを実装

### 🟡 中: ドロワー開閉の2秒遅延

**問題:** 平均1955ms（目標300msの6.5倍）

**対策案:**
- Bladeテンプレートの簡素化
- activitiesの遅延ロード

### 🟢 低: 画像プレビュー測定

**問題:** データが取得できていない

**対策案:**
- 実装を確認
- ブラウザコンソールログを確認

---

## 🔧 即実施すべき修正

### 修正1: 検索のデバウンス設定を最適化

**現状:**
```blade
wire:model.change="searchKeyword"
x-init="setTimeout(() => { searching = false; }, 500);"
```

**問題:** `change`イベントは入力途中でも発火する可能性

**修正案A: blurイベントに変更**
```blade
wire:model.blur="searchKeyword"
```
→ フォーカスが外れた時のみサーバーリクエスト

**修正案B: デバウンスを長くする**
```blade
wire:model.live.debounce.1000ms="searchKeyword"
```
→ 1秒間入力がない場合にサーバーリクエスト

### 修正2: Alpine.jsの測定タイミング修正

**問題:** `search_render`の測定タイミングが早すぎる可能性

**修正案:** Livewireのレンダリング完了を待つ
```javascript
$watch('$wire.searchKeyword', (value) => {
    searchStartTime = performance.now();
    
    // Livewireのレンダリング完了を待つ
    this.$nextTick(() => {
        const duration = performance.now() - searchStartTime;
        console.log('[Search completed]', duration);
    });
});
```

---

## 📊 統計サマリー

### 検索パフォーマンス

```
search_keyword_update（サーバー側）:
- 平均: 0ms
- 最小: 0ms
- 最大: 0ms
→ サーバー側は問題なし

search_render（フロントエンド側）:
- 平均: 2337ms
- 最小: 500ms
- 最大: 5458ms
- 中央値: 511ms
→ 外れ値が3件（4943ms, 5276ms, 5458ms）
```

### ドロワー開閉

```
drawer_open:
- 平均: 1955ms
- 最小: 1700ms
- 最大: 2648ms
→ PHP 8.4修正後も改善なし
```

### タブ切り替え

```
tab_switch（Permissions除く）:
- 平均: 33ms
- 最小: 7ms
- 最大: 78ms
→ 正常

tab_switch（Permissionsのみ）:
- 6761ms（1件のみ測定）
→ 致命的な問題
```

---

## 🚀 次のアクション

### 1. 検索の修正（最優先）

```blade
<!-- content.blade.php -->
<!-- 修正前 -->
<x-mary-input wire:model.change="searchKeyword" ... />

<!-- 修正後 -->
<x-mary-input wire:model.blur="searchKeyword" ... />
```

または

```blade
<!-- デバウンスを1秒に -->
<x-mary-input wire:model.live.debounce.1000ms="searchKeyword" ... />
```

### 2. 画像プレビューの確認

ブラウザコンソールで以下を確認:
- 画像ファイルを開いた時のログ
- `[FileInspector Performance] Image preview loaded`が出るか

### 3. 再測定

修正後に以下を実施:
```bash
# ログをクリア
./vendor/bin/sail exec laravel-1 rm -f storage/logs/performance_stats.json
./vendor/bin/sail exec laravel-1 touch storage/logs/performance_stats.json

# 再測定
```

---

**分析完了日:** 2025年12月31日  
**重大な問題:** キーワード検索で5秒の遅延（Livewireの重複レンダリングが原因の可能性）  
**次のステップ:** 検索のデバウンス設定を修正

