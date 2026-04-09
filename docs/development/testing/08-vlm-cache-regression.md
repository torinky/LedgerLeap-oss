# VLM キャッシュ判定回帰テスト

**最終更新:** 2026-04-09

`docker/paddle/unified_api.py` の PaddleOCR / PaddleOCR-VL 起動前キャッシュ判定を固定化するための回帰テストです。

このテストは **FastAPI や実際の OCR 初期化を起動せず**、`_is_backend_cached()` と `_resolve_offline_mode()` の純粋ロジックだけを確認します。

---

## 目的

次の不具合を再発させないことを目的にします。

- `lang='japan'` なのに `rec/ml/Multilingual_PP-OCRv3_rec_infer` を前提にしてしまう
- 実際のキャッシュが存在しても `No complete local cache found` と判定してしまう
- `VLM_OFFLINE=auto` で cache detected にならず、外部ダウンロードを試行してしまう
- `VLM_OFFLINE=1` なのにキャッシュ不足を見逃してしまう

---

## 実行コマンド

```bash
python3 -m unittest discover -s docker/paddle/tests -p "test_*.py"
```

---

## カバーするケース

- 現行日本語モデル tar レイアウト
  - `det/ml/Multilingual_PP-OCRv3_det_infer/Multilingual_PP-OCRv3_det_infer.tar`
  - `rec/japan/japan_PP-OCRv4_rec_infer/japan_PP-OCRv4_rec_infer.tar`
  - `cls/ch_ppocr_mobile_v2.0_cls_infer/ch_ppocr_mobile_v2.0_cls_infer.tar`
- 旧 multilingual tar レイアウト
  - `rec/ml/Multilingual_PP-OCRv3_rec_infer/Multilingual_PP-OCRv3_rec_infer.tar`
- 展開済み `inference.pdiparams` レイアウト
- 不完全キャッシュは拒否
- `VLM_OFFLINE=auto` の自動オフライン判定
- `VLM_OFFLINE=1` の強制オフライン判定

---

## CI 連携

GitHub Actions では `.github/workflows/vlm-cache-regression.yml` でこのテストを実行します。

- `push` / `pull_request` で `docker/paddle/**` が変更されたときに実行
- `workflow_dispatch` で手動実行可能

---

## 実装上の注意

- 依存追加は不要です
- `fastapi` はテスト内で stub 化しています
- 実コンテナ起動を伴わないため、ローカル・CI どちらでも高速に回せます
- キャッシュ判定のパスを変えた場合は、このテストの期待パスも必ず更新してください

