# Phase 2.6 効果測定ガイド

**作成日:** 2025年11月16日  
**目的:** Phase 2.6実装の効果を実データで測定  

---

## 1. 測定項目

### 主要指標

| 指標 | 測定内容 | 期待値 |
|------|---------|--------|
| **初回検索可能時間** | Tika完了→ベクトル化完了 | **<5秒** |
| **オフィスファイル最適化** | Tikaのみで完了する割合 | **>90%** |
| **段階的品質向上** | Tika→OCR→VLMのアップグレード | **確認** |
| **ベクトル化完了率** | 全ファイルのベクトル化率 | **>95%** |

---

## 2. 測定手順

### Step 1: デモデータ作成

```bash
# Phase 2.6測定用のデモデータを作成
./vendor/bin/sail artisan db:seed --class=Phase26DemoSeeder
```

**作成されるデータ:**
- オフィスファイル: 3件（Word, Excel, PowerPoint）
- 画像ファイル: 3件（請求書、契約書、領収書）
- PDFファイル: 2件（テキストPDF、スキャンPDF）
- パフォーマンス測定用: 20件（混合）

**合計:** 28ファイル

### Step 2: キュー処理開始

```bash
# 別ターミナルでキューワーカーを起動
./vendor/bin/sail artisan queue:work --queue=default,vlm,ocr
```

**処理フロー:**
```
1. ProcessAttachedFile（Tika処理）
   ↓
2. VectorizeAttachedFile（即座にベクトル化）★Phase2.6の改善
   ↓
3. VLM/OCR並列処理（バックグラウンド）
   ↓
4. VectorizeAttachedFile（品質向上）★段階的アップグレード
```

### Step 3: リアルタイム測定

```bash
# リアルタイム監視モード（3秒間隔で更新）
./vendor/bin/sail artisan phase26:measure --refresh --interval=3
```

**表示内容:**
- 全体統計（ファイナライズ済み率）
- ファイルタイプ別ステータス分布
- ベクトル化状況（チャンク数）
- 処理時間統計
- 品質向上状況

### Step 4: スナップショット測定

```bash
# 現時点のスナップショットを表示
./vendor/bin/sail artisan phase26:measure
```

---

## 3. 測定結果の見方

### 3.1. 初回検索可能時間

**改善前（Phase 2.5まで）:**
```
Tika完了 → VLM/OCR完了待ち → ベクトル化
         ~~~~~~~~~~~~~~~~  ← 60秒待機
```

**改善後（Phase 2.6）:**
```
Tika完了 → 即座にベクトル化
         ← 2秒で検索可能！✅
```

**確認方法:**
```sql
SELECT 
    id,
    original_filename,
    TIMESTAMPDIFF(SECOND, tika_processed_at, processing_finalized_at) as seconds,
    finalized_source
FROM attached_files
WHERE finalized_source = 'tika'
ORDER BY seconds;
```

**期待値:** 平均5秒以内

### 3.2. ファイルタイプ別最適化

**オフィスファイル（Word, Excel, PPT）:**
```
期待: FINALIZED_BY_TIKA で停止
理由: Tikaのネイティブ抽出が最高品質
```

**画像ファイル（JPG, PNG）:**
```
期待: TIKA → OCR → VLM と段階的向上
```

**確認方法:**
```sql
SELECT 
    CASE
        WHEN mime LIKE 'application/vnd.openxmlformats%' THEN 'オフィス'
        WHEN mime LIKE 'image/%' THEN '画像'
        WHEN mime = 'application/pdf' THEN 'PDF'
    END as file_type,
    status,
    COUNT(*) as count
FROM attached_files
GROUP BY file_type, status;
```

### 3.3. ベクトル化完了率

**確認方法:**
```sql
SELECT 
    COUNT(*) as total_files,
    SUM(CASE WHEN processing_finalized_at IS NOT NULL THEN 1 ELSE 0 END) as finalized,
    ROUND(SUM(CASE WHEN processing_finalized_at IS NOT NULL THEN 1 ELSE 0 END) * 100.0 / COUNT(*), 2) as completion_rate
FROM attached_files;
```

**期待値:** >95%

### 3.4. ベクトル品質

**確認方法:**
```sql
SELECT 
    l.id as ledger_id,
    af.original_filename,
    af.finalized_source,
    COUNT(lc.id) as chunk_count,
    AVG(LENGTH(lc.chunk_text)) as avg_chunk_length
FROM ledgers l
JOIN attached_files af ON af.ledger_id = l.id
LEFT JOIN ledger_chunks lc ON lc.ledger_id = l.id
GROUP BY l.id, af.original_filename, af.finalized_source;
```

---

## 4. トラブルシューティング

### 問題1: キューが処理されない

**症状:**
```bash
./vendor/bin/sail artisan phase26:measure
# → 全て「処理待ち」のまま
```

**解決策:**
```bash
# キューワーカーが起動しているか確認
ps aux | grep "queue:work"

# 再起動
./vendor/bin/sail artisan queue:restart
./vendor/bin/sail artisan queue:work --queue=default,vlm,ocr
```

### 問題2: Tika処理が失敗

**症状:**
```
status = 'tika_failed'
```

**解決策:**
```bash
# Tikaサービスの状態確認
./vendor/bin/sail exec tika curl -I http://localhost:9998/tika

# Tika再起動
./vendor/bin/sail restart tika
```

### 問題3: VLM/OCRが失敗

**症状:**
```
vlm_failed_at IS NOT NULL
```

**解決策:**
```bash
# VLMサービス確認
./vendor/bin/sail exec vlm curl http://localhost:5050/health

# OCRサービス確認
./vendor/bin/sail exec ocr curl http://localhost:8000/health

# ログ確認
./vendor/bin/sail logs vlm
./vendor/bin/sail logs ocr
```

---

## 5. 期待される測定結果

### ベストケース

```
=== Phase 2.6 効果測定レポート ===

📊 全体統計
┌─────────────────────┬──────┬────────┐
│ 指標                │ 件数 │ 割合   │
├─────────────────────┼──────┼────────┤
│ 総ファイル数        │ 28   │ 100%   │
│ ファイナライズ済み  │ 28   │ 100%   │
│ Tika完了            │ 28   │ 100%   │
│ OCR完了             │ 20   │ 71.4%  │
│ VLM完了             │ 20   │ 71.4%  │
└─────────────────────┴──────┴────────┘

�� ファイルタイプ別ステータス分布
  オフィス:
    - Tika完了: 8件 ✅（OCR/VLMで上書きされない）
  画像:
    - VLM完了: 3件 ✅（段階的向上）
  PDF:
    - VLM完了: 2件 ✅

⏱️ 処理時間統計
  Tika: 平均2.3秒 ✅（即座に検索可能）
  OCR: 平均25.5秒
  VLM: 平均45.2秒

📈 品質向上状況
┌─────────────────────────────────┬──────┐
│ アップグレードパターン          │ 件数 │
├─────────────────────────────────┼──────┤
│ Tikaのみで完了（オフィス）      │ 8    │ ✅
│ Tika → OCR                      │ 12   │ ✅
│ OCR → VLM                       │ 8    │ ✅
└─────────────────────────────────┴──────┘
```

---

## 6. データクリーンアップ

測定完了後、デモデータを削除:

```bash
# Phase26Demoシーダーは自動クリーンアップ機能付き
# 再実行すると古いデータを削除
./vendor/bin/sail artisan db:seed --class=Phase26DemoSeeder

# または手動削除
./vendor/bin/sail artisan tinker
>>> Organization::where('name', 'LIKE', 'Phase26Demo%')->first()->delete();
```

---

## 7. まとめ

### Phase 2.6の成功基準

✅ **初回検索可能時間:** <5秒（Tika完了後）  
✅ **オフィスファイル最適化:** Tikaのみで完了  
✅ **段階的品質向上:** Tika→OCR→VLM確認  
✅ **ベクトル化完了率:** >95%  

### 次のアクション

測定結果をもとに、Phase 3.1（ハイブリッド検索）の実装に進む。

---

**作成者:** GitHub Copilot CLI  
**Phase:** 2.6 効果測定  
**関連ドキュメント:**
- [Phase 2.6 実装完了報告](./2025-11-16_enhanced-vector-indexing-strategy_phase-2.6-implementation-completed.md)
