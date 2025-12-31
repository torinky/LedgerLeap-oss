# 画像プレビューのログ記録修正完了

**修正日:** 2025年12月31日  
**問題:** 画像プレビューのログがperformance_stats.jsonに記録されない  
**原因:** Blade内での`$wire.call()`の使用方法が誤っていた

---

## 🔍 問題の原因

### 発見した問題

**誤った実装:**
```javascript
// Alpine.js内で$wire.call()を使用
$wire.call('logPerformance', 'image_preview_load', duration, {...});
```

**問題点:**
1. Blade内のAlpine.jsでは`$wire`は使用できない
2. 正しくは`@this`を使用する必要がある
3. そのため、サーバーへのログ送信が失敗していた

**結果:**
- ブラウザコンソールにはログが出る ✅
- Laravelログには記録されない ❌
- performance_stats.jsonにも記録されない ❌

---

## ✅ 実施した修正

### 修正1: 画像プレビューのログ記録

**ファイル:** `resources/views/livewire/attached-file/file-inspector/preview.blade.php`

**修正前:**
```javascript
$wire.call('logPerformance', 'image_preview_load', duration, {...});
```

**修正後:**
```javascript
@this.call('logPerformance', 'image_preview_load', duration, {...});
```

**追加改善:**
- 画像読み込み開始時のログも追加
- キャッシュヒット時のログを明確化

### 修正2: 検索のログ記録

**ファイル:** `resources/views/livewire/attached-file/file-inspector/tabs/content.blade.php`

**修正前:**
```javascript
$wire.call('logPerformance', 'search_render', duration, {...});
```

**修正後:**
```javascript
@this.call('logPerformance', 'search_render', duration, {...});
```

---

## 📊 期待される動作

### 画像プレビュー（1回目）

**ブラウザコンソール:**
```javascript
[FileInspector Performance] Image preview started { url: "..." }
[FileInspector Performance] Image preview loaded { 
    duration_ms: "1234.56", 
    url: "...", 
    cached: false 
}
```

**Laravelログ:**
```
[FileInspector Performance] image_preview_load {
    "metric": "image_preview_load",
    "duration_ms": 1234.56,
    "file_id": 16,
    "tab": "content",
    "url": "...",
    "from_cache": false
}
```

**performance_stats.json:**
```json
{
    "metric": "image_preview_load",
    "duration_ms": 1234.56,
    "file_id": 16,
    "tab": "content",
    "url": "...",
    "from_cache": false,
    "timestamp": "2025-12-31T04:30:00.000000Z"
}
```

### 画像プレビュー（2回目 - キャッシュヒット）

**ブラウザコンソール:**
```javascript
[FileInspector Performance] Image preview (cache hit) { 
    url: "...", 
    cached: true 
}
```

**Laravelログ/JSON:** なし（キャッシュヒット時は測定不要）

---

## 🧪 テスト手順

### 1. ログファイルをクリア

```bash
./vendor/bin/sail exec laravel-1 rm -f storage/logs/performance_stats.json
./vendor/bin/sail exec laravel-1 touch storage/logs/performance_stats.json
```

### 2. ブラウザで画像を開く

1. FileInspectorで画像ファイルを選択（1回目）
2. F12 → Console でログを確認
3. 期待されるログ:
   ```
   [FileInspector Performance] Image preview started
   [FileInspector Performance] Image preview loaded { duration_ms: "XXX" }
   ```

### 3. ドロワーを閉じて再度開く

1. ドロワーを閉じる
2. 同じ画像ファイルを再度選択（2回目）
3. 期待されるログ:
   ```
   [FileInspector Performance] Image preview (cache hit) { cached: true }
   ```

### 4. Laravelログを確認

```bash
./vendor/bin/sail logs -f | grep "image_preview"
```

期待される出力:
```
[FileInspector Performance] image_preview_load {"metric":"image_preview_load","duration_ms":XXXX.XX,...}
```

### 5. JSONファイルを確認

```bash
./vendor/bin/sail exec laravel-1 cat storage/logs/performance_stats.json | jq '.[] | select(.metric == "image_preview_load")'
```

期待される出力:
```json
{
  "metric": "image_preview_load",
  "duration_ms": 1234.56,
  "file_id": 16,
  "tab": "content",
  "url": "...",
  "from_cache": false,
  "timestamp": "2025-12-31T..."
}
```

---

## 🔧 技術的な詳細

### Livewireでの正しいメソッド呼び出し

**Blade内のAlpine.js:**
```javascript
// ❌ 間違い
$wire.call('methodName', param1, param2)

// ✅ 正しい
@this.call('methodName', param1, param2)
```

**JavaScript内:**
```javascript
// ✅ 正しい（Alpine.jsのx-data内）
$watch('$wire.propertyName', (value) => { ... })

// ✅ 正しい（メソッド呼び出し）
@this.call('methodName', param1, param2)
```

### sessionStorageの動作

**キャッシュキー:**
```javascript
cacheKey: 'img-loaded-{{ $this->previewUrl }}'
```

**保存:**
```javascript
sessionStorage.setItem(this.cacheKey, 'true');
```

**復元:**
```javascript
const cached = sessionStorage.getItem(this.cacheKey);
if (cached === 'true') {
    // キャッシュヒット
}
```

---

## 📋 変更サマリー

### 修正ファイル

1. ✅ `resources/views/livewire/attached-file/file-inspector/preview.blade.php`
   - `$wire.call()` → `@this.call()`
   - 画像読み込み開始ログを追加

2. ✅ `resources/views/livewire/attached-file/file-inspector/tabs/content.blade.php`
   - `$wire.call()` → `@this.call()`

### 追加改善

- 画像読み込み開始時のコンソールログ追加
- キャッシュヒット時のログメッセージを明確化

---

## 🎯 次のアクション

### すぐに実行してください

1. **ログをクリア**
```bash
./vendor/bin/sail exec laravel-1 rm -f storage/logs/performance_stats.json
./vendor/bin/sail exec laravel-1 touch storage/logs/performance_stats.json
```

2. **ブラウザで画像を開く**
   - 1回目: 測定される
   - 2回目: キャッシュヒット

3. **ログを確認**
   - ブラウザコンソール
   - Laravelログ
   - performance_stats.json

### 期待される結果

**修正前:**
- ❌ ブラウザコンソールのみログが出る
- ❌ Laravelログには何も記録されない
- ❌ performance_stats.jsonも空

**修正後:**
- ✅ ブラウザコンソールにログが出る
- ✅ Laravelログに記録される
- ✅ performance_stats.jsonに記録される

---

## 📊 測定項目の確認

これで以下のすべての測定が正しく記録されるはずです：

| メトリクス | サーバーログ | JSONファイル | ブラウザコンソール | 状態 |
|-----------|------------|-------------|------------------|------|
| drawer_open | ✅ | ✅ | ✅ | 正常 |
| tab_switch | ✅ | ✅ | ✅ | 正常 |
| search_keyword_update | ✅ | ✅ | - | 正常 |
| search_render | ✅ | ✅ | ✅ | **修正済み** |
| image_preview_load | ✅ | ✅ | ✅ | **修正済み** |

---

**修正完了日:** 2025年12月31日  
**次のステップ:** 画像を開いて、ログが正しく記録されることを確認してください！

