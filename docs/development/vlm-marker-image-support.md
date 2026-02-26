# Marker画像対応クイックリファレンス

**作成日:** 2025年10月25日  
**対象:** 今後のLedgerLeap開発者向けガイド

---

## 使い方

### 基本的な呼び出し（curl）

```bash
# PDFファイル
curl -X POST http://localhost:8001/extract/markdown \
  -F "file=@path/to/document.pdf"

# JPG画像
curl -X POST http://localhost:8001/extract/markdown \
  -F "file=@path/to/image.jpg"

# PNG画像
curl -X POST http://localhost:8001/extract/markdown \
  -F "file=@path/to/image.png"
```

### レスポンス形式

```json
{
  "success": true,
  "markdown": "...",
  "processing_time_s": 143.43,
  "file_size_bytes": 2509,
  "max_pages_limit": null
}
```

---

## サポートされる形式

| 形式 | 拡張子 | 備考 |
|-----|--------|------|
| PDF | .pdf | ネイティブサポート |
| JPEG | .jpg, .jpeg | 自動PDF変換 |
| PNG | .png | 透明背景対応 |

---

## 技術仕様

### 画像→PDF変換の流れ

1. 画像ファイルを検出（拡張子チェック）
2. PIL (Pillow) で画像を開く
3. カラーモードをRGBに統一
   - RGBA/LA/P → 白背景付きRGB
4. PDF形式で保存（resolution=100 DPI）
5. Markerで処理

### 処理時間の目安

- シンプルな画像: 40-60秒
- 複雑な画像（レシート等）: 120-180秒
- PDF（1ページ、表付き）: 300-400秒

### メモリ要件

- シンプルな文書: デフォルトメモリで動作
- 複雑な文書: 10GB推奨
- 設定場所: `docker-compose.yml` の `memory: 10G`

---

## トラブルシューティング

### エラー: "Unsupported file format"

**原因:** サポートされていないファイル形式  
**対応:** PDF/JPG/PNG以外の形式は事前に変換が必要

### 処理タイムアウト

**原因:** 大きなファイルまたは複雑な内容  
**対応:** 
- `max_pages` パラメータで処理ページ数を制限
- メモリを10GB以上に増やす

### 画像の向きが間違っている

**現状:** 自動回転機能はなし  
**対応:** 事前に画像を正しい向きに回転させる

---

## 実装の場所

### コード
- `docker/marker/app.py`: メインAPIロジック
- `docker/marker/requirements.txt`: 依存関係（Pillow含む）
- `docker/marker/Dockerfile`: コンテナ定義

### ドキュメント
- `docs/work/vlm-implementation/2025-10-25_phase0-vlm-poc-execution-log.md`: 実装経緯
- `storage/test/vlm-poc/results/image_support_implementation.md`: 技術詳細

### テストデータ
- `storage/test/vlm-poc/`: サンプルファイル
- `storage/test/vlm-poc/results/`: 処理結果

---

## Laravel統合時の注意点

### 推奨アーキテクチャ

```
ユーザーリクエスト
    ↓
Laravelコントローラ
    ↓
ジョブをキューに登録
    ↓
キューワーカー（非同期）
    ↓
MarkerAPIコール (http://vlm:8000/extract/markdown)
    ↓
結果をDBに保存
```

### 実装例（Laravel側）

```php
// ジョブディスパッチ
ProcessDocumentJob::dispatch($ledger, $filePath);

// ジョブ内でAPI呼び出し
$response = Http::timeout(600)
    ->attach('file', file_get_contents($filePath), basename($filePath))
    ->post('http://vlm:8000/extract/markdown');

// 結果を保存
$ledger->update([
    'content_extracted' => $response->json()['markdown']
]);
```

---

## 今後の改善予定

- [ ] 画像解像度の動的調整
- [ ] 複数画像のバッチ処理
- [ ] 画像前処理（コントラスト、ノイズ除去）
- [ ] EXIF情報の活用（回転補正）
- [ ] WebP形式のサポート

---

**参考:** Phase 0 PoC実施記録を必ず確認してください  
`../work/vlm-implementation/2025-10-25_phase0-vlm-poc-execution-log.md`
