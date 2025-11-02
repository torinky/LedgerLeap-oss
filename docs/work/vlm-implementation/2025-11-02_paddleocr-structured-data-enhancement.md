# PaddleOCR 2.8安定版での構造化データ抽出強化

**作成日:** 2025年11月2日  
**ステータス:** 実装完了・テスト済み  
**担当:** GitHub Copilot CLI

## 概要

PaddleOCR 2.8安定版で最大限の構造化データを取得するため、最新のドキュメント調査に基づき設定を最適化し、テストを実施しました。

## 実施内容

### 1. PaddleOCRの設定最適化

#### 追加したパラメータ

```python
engine = PaddleOCR(
    use_angle_cls=True,           # 既存
    lang='japan',                 # 既存
    use_gpu=use_gpu,              # 既存
    use_space_char=True,          # ✨NEW: スペース文字の認識
    det_db_score_mode='slow',     # ✨NEW: 高精度検出（速度よりも精度優先）
    det_limit_side_len=960,       # ✨NEW: 検出画像の最大辺長（高解像度対応）
    return_word_box=True,         # ✨NEW: 単語レベルのbbox取得
    show_log=False                # ✨NEW: ログ抑制
)
```

#### パラメータの効果

| パラメータ | 効果 | 理由 |
|-----------|------|------|
| `use_space_char=True` | スペース文字の認識 | 日本語文書の単語区切りや構造認識に有効 |
| `det_db_score_mode='slow'` | 検出精度向上 | 複雑なレイアウトや小さい文字の検出改善 |
| `det_limit_side_len=960` | 高解像度対応 | デフォルト値より大きく、詳細な検出が可能 |
| `return_word_box=True` | 単語単位のbbox | 構造化データ抽出に必須（LLM統合時に有用） |

### 2. 構造化データ抽出の強化

#### 追加した情報

```python
# テキストブロック情報の強化
text_blocks.append({
    "type": "text",
    "content": text,
    "bbox": [[float(p[0]), float(p[1])] for p in bbox],  # ✨ 座標情報
    "confidence": float(confidence),                      # ✨ 信頼度
    "line_index": idx                                     # ✨ 行番号
})

# キー・バリューペアの自動抽出
if ':' in text or '：' in text:
    parts = text.replace('：', ':').split(':', 1)
    if len(parts) == 2:
        key_value_pairs.append({
            "key": key,
            "value": value,
            "confidence": float(confidence),              # ✨ 信頼度
            "bbox": [[float(p[0]), float(p[1])] for p in bbox]  # ✨ 座標情報
        })
```

### 3. テスト結果

#### テスト環境
- **日時:** 2025年11月2日
- **VLM Model:** PaddleOCR 2.8.1 (stable)
- **デバイス:** CPU
- **テストファイル:** 4種類（請求書PDF、領収書JPG、手書きPNG、議事録PDF）

#### 結果サマリ

| ファイル | 処理時間 | テキストブロック数 | Key-Valueペア数 | 状態 |
|---------|---------|-----------------|----------------|------|
| invoice_simple.pdf | 9.22s | 69 | 5 | ✅ |
| receipt_01.jpg | 3.56s | 30 | 1 | ✅ |
| hand_writing_01.png | 2.76s | 6 | 0 | ✅ |
| meeting_notes.pdf | 15.99s | 143 | 0 | ✅ |

**成功率:** 4/4 (100%)

#### 抽出された構造化データの例（invoice_simple.pdf）

```json
{
  "pages": [{
    "page_index": 0,
    "text_lines": ["請求書番号:00000000", "青求書", ...],
    "line_count": 69
  }],
  "text_blocks": [
    {
      "type": "text",
      "content": "請求書番号:00000000",
      "bbox": [[819.0, 37.0], [1035.0, 37.0], [1035.0, 60.0], [819.0, 60.0]],
      "confidence": 0.98,
      "line_index": 0
    },
    ...
  ],
  "key_value_pairs": [
    {
      "key": "請求書番号",
      "value": "00000000",
      "confidence": 0.98,
      "bbox": [[819.0, 37.0], [1035.0, 37.0], [1035.0, 60.0], [819.0, 60.0]]
    },
    {
      "key": "発行日",
      "value": "0000年00月00日",
      "confidence": 1.00,
      "bbox": [[820.0, 62.0], [1036.0, 62.0], [1036.0, 85.0], [820.0, 85.0]]
    },
    {
      "key": "登録番号",
      "value": "T1234567890123",
      "confidence": 1.00,
      "bbox": [[68.0, 273.0], [296.0, 273.0], [296.0, 296.0], [68.0, 296.0]]
    },
    {
      "key": "お支払期限",
      "value": "0000年00月00日",
      "confidence": 0.99,
      "bbox": [[66.0, 336.0], [335.0, 336.0], [335.0, 360.0], [66.0, 360.0]]
    },
    {
      "key": "振込先",
      "value": "OO銀行",
      "confidence": 0.88,
      "bbox": [[67.0, 362.0], [188.0, 362.0], [188.0, 386.0], [67.0, 386.0]]
    }
  ],
  "tables": []
}
```

### 4. 技術的知見

#### PaddleOCR 2.8の強み
1. ✅ **CPU環境で実用的な速度**（3-16秒/ページ）
2. ✅ **日本語の高精度認識**（信頼度0.83-1.00）
3. ✅ **構造化データの自動抽出**（Key-Valueペア）
4. ✅ **座標情報の取得**（LLM統合に有用）

#### 制約事項
1. ❌ **表構造の認識**は未対応（PaddleOCR-VLが必要）
2. ⚠️ **手書き文字**の精度はやや低い（0.78-0.83）
3. ⚠️ **複雑なレイアウト**では一部文字が欠ける可能性

### 5. 推奨される使い分け

| 用途 | 推奨モデル | 理由 |
|------|----------|------|
| 通常の帳票（請求書・領収書） | **PaddleOCR** | CPU環境で十分な精度と速度 |
| 表構造が重要な文書 | **PaddleOCR-VL** | 表認識機能が必須（GPU推奨） |
| PDF→Markdown変換 | **Marker/MinerU** | レイアウト保持が優秀 |
| 手書き文字が多い文書 | **PaddleOCR-VL** | 手書き認識精度が高い |

## 次のステップ

### 短期（完了済み）
- [x] PaddleOCR設定の最適化
- [x] 構造化データ抽出機能の実装
- [x] テストスクリプトの作成と実行
- [x] 結果の検証とドキュメント化

### 中期（今後の予定）
- [ ] LLM統合時のプロンプト設計（構造化データを活用）
- [ ] RAG機能との連携強化
- [ ] 表構造認識の評価（PaddleOCR-VL）
- [ ] 手書き文字認識の精度向上検討

### 長期（検討中）
- [ ] 複数VLMモデルの自動選択機能
- [ ] カスタムファインチューニングの検討
- [ ] リアルタイムOCR処理の最適化

## 参考資料

### 主要ドキュメント
- [PaddleOCR公式ドキュメント](https://www.paddleocr.ai/)
- [PaddleOCR Configuration Guide](https://www.paddleocr.ai/latest/en/version2.x/ppocr/blog/config.html)
- [VLM-OCR技術とインデックス戦略の再評価](./2025-10-23_vlm-ocr-and-indexing-strategy-review_updated_v2.md)

### 技術調査結果
- **use_space_char**: スペース認識により構造化データ精度向上
- **det_db_score_mode='slow'**: 検出精度優先モード
- **det_limit_side_len**: 高解像度画像への対応
- **return_word_box**: LLM統合時の位置情報活用

## まとめ

PaddleOCR 2.8安定版の設定を最適化し、以下の成果を達成しました：

1. ✅ **構造化データ抽出機能の実装**
   - テキストブロック（座標、信頼度、行番号）
   - Key-Valueペア自動抽出
   - JSON形式での出力

2. ✅ **実用的な性能確認**
   - CPU環境で3-16秒/ページ
   - 日本語文書の高精度認識
   - 100%のテスト成功率

3. ✅ **LLM統合への準備**
   - 座標情報の取得
   - 信頼度情報の付加
   - 構造化されたJSON出力

この改善により、LedgerLeapのOCR機能は次世代のRAG統合に向けた準備が整いました。
