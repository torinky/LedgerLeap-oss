# Phase 0: VLM動作検証PoC 実施記録・最終レポート

**最終更新日:** 2025年10月26日
**ステータス:** ✅ **PoC完了**
**関連ドキュメント:** 
- [Phase 0: VLM動作検証PoC計画書](./2025-10-25_phase0-vlm-poc-plan.md)
- [Phase 0: VLM追加調査計画書](./2025-10-26_phase0-vlm-additional-investigation-plan.md)

---

## 1. キャッチアップ方法 (最終まとめ)

### 1.1. 忙しい人向け (30秒で理解)

1.  **目的:** PDFからMarkdown形式で構造化データを抽出できる、オンプレミス動作可能なOSS VLMを見つけること。
2.  **`PaddleOCR`での挑戦:** 当初、`PaddleOCR`の構造化抽出機能(`PP-Structure`)を試したが、**日本語の文字認識との両立が技術的に極めて困難**であることが判明し、この路線は断念。
3.  **`Marker`への方針転換:** 次に、PDF→Markdown変換に特化した`Marker`を試したが、`PaddleOCR`との**Python依存関係の競合（依存関係地獄）**により、同一コンテナ内での共存に失敗。
4.  **最終構成:** 依存関係問題を解決するため、**コンテナを分離**。現在は`Marker`専用の`vlm`コンテナが稼働中。`PaddleOCR`用コンテナの定義も`docker-compose.yml`に`paddle`サービスとして残しており、必要に応じて切り替え可能。
5.  **次のステップ:** `Marker`コンテナの動作検証を続ける。

### 1.2. 詳細を追いたい人向け

このドキュメントのセクション3以降を参照してください。`PaddleOCR`での試行錯誤の詳細、`YomiToku`のライセンス問題、`Marker`への挑戦と依存関係の壁、そして最終的なコンテナ分離構成に至った全経緯と思考プロセスが記録されています。

---

## 2. 新しいDocker構成と運用方法

依存関係の競合を解決するため、VLMモデルごとにDockerコンテナを分離する構成に変更しました。

### 2.1. ディレクトリ構造

-   `docker/vlm/`: **現在有効な**VLMモデルの定義を配置します。(現在は`Marker`用)
-   `docker/paddle/`: 将来利用する可能性のある`PaddleOCR`の定義をここに分離して保管します。

### 2.2. コンテナの切り替え方法

`docker-compose.yml`ファイルを編集することで、使用するVLMコンテナを切り替えられます。

-   **Markerを有効にする場合 (現在):**
    -   `vlm`サービスの`build.context`が`./docker/vlm`になっていることを確認します。
    -   `paddle`サービスがコメントアウトされていることを確認します。

-   **PaddleOCRに切り替える場合:**
    1.  現在の`vlm`サービスをコメントアウトします。
    2.  `paddle`サービスのコメントアウトを解除します。
    3.  必要に応じて、`ports`の設定を`"8001:8000"`に変更します。
    4.  `vendor/bin/sail up -d --build paddle` を実行します。

---

## 3. PoC実施経緯 (時系列ログ)

### 3.1. `PaddleOCR`によるプレーンテキスト抽出 (成功)

-   **目的:** PoC計画書に基づき、まずは基本的なOCR機能（プレーンテキスト抽出）の動作を確認する。
-   **結果:** ✅ **成功**
-   **トラブルシューティング:**
    -   `libgl1-mesa-glx` -> `libgl1` に変更 (Dockerfile)
    -   起動コマンドを`uvicorn`に変更 (Dockerfile)
    -   `PyMuPDF`のバージョンを`1.19.0`に固定 (requirements.txt)
-   **成果:** この時点で、JPGおよびPDFから日本語のプレーンテキストを高精度で抽出できるコンテナが完成した。詳細は[PaddleOCRVL API実装完了記録](./2025-10-26_paddleocrvl-implementation-log.md)を参照。

### 3.2. `PaddleOCR`による構造化データ抽出 (失敗)

-   **目的:** `PaddleOCR`の`PP-Structure`機能を使い、テーブル情報をHTML形式で抽出する。
-   **結果:** ❌ **失敗**
-   **試行錯誤の経緯:**
    1.  **`use_structure=True`:** `lang='japan'`ではレイアウトモデルがサポートされておらず起動失敗。
    2.  **`lang='ch'`での代用:** レイアウトは認識されたが、OCR結果が中国語に文字化け。
    3.  **OCRエンジン注入:** `lang='ch'`の`PP-Structure`に`lang='japan'`の`PaddleOCR`を注入したが、結果は変わらず。
-   **結論:** `PaddleOCR`ライブラリの現行仕様では、日本語OCRと構造化抽出の両立は極めて困難と判断し、このアプローチを断念。

### 3.3. 代替OSSの調査

-   **`YomiToku`:** 日本語に特化しており技術的に非常に有望だったが、ライセンスが**AGPL-3.0**であり、商用利用にはリスクが高すぎると判断し採用を見送り。
-   **`Marker`:** PDF→Markdown変換に特化しており、ライセンスもMITで問題ないため、最有力候補として検証を決定。

### 3.4. `Marker`と`PaddleOCR`の共存 (失敗)

-   **目的:** 迅速な比較検証のため、1つのコンテナに`PaddleOCR`と`Marker`を同居させる。
-   **結果:** ❌ **失敗**
-   **原因:** `marker-pdf`が必要とする`torch`や`transformers`と、`paddleocr`が必要とする`paddlepaddle`の間で、下位の依存ライブラリ（`numpy`, `opencv-python`等）のバージョン要件が衝突（**依存関係地獄**）。`pip`でのインストールが不可能だった。

### 3.5. `Marker`単独での検証 (APIの試行錯誤)

-   **目的:** 依存関係問題を解決するため、コンテナを`Marker`専用に切り替えて検証する。
-   **結果:** ❌ **失敗 (複数回)**
-   **原因:** `marker-pdf`ライブラリのAPI仕様が頻繁に変更されており、公式ドキュメントやWeb上のサンプルコードが古くなっていたため。`marker.models.MarkerModel`, `marker.MarkerModel`, `PdfConverter`など、様々な方法を試したが、すべて`AttributeError`で失敗。

### 3.6. `Marker` CLIラッパー方式での検証 (成功)

-   **目的:** ライブラリのAPI変更に影響されない、安定した方法を確立する。
-   **アプローチ:** Pythonの`subprocess`を使い、`marker-pdf`が提供するコマンドラインツール`marker_single`を直接呼び出すラッパーAPIを実装。
-   **結果:** ✅ **成功**
-   **実装内容:**
    -   `PYTHONUNBUFFERED=1`でPythonのバッファリングを無効化し、リアルタイムでログ出力
    -   `subprocess.run()`で`marker_single`を実行し、進捗をリアルタイム表示
    -   Markerは出力をサブディレクトリ内に生成するため、ディレクトリ構造を探索して`.md`ファイルを取得
-   **テスト結果:**
    -   ✅ **invoice_simple.pdf**: 成功（処理時間: 350秒、Markdown長: 1,641文字）
        -   日本語の請求書を正確に表形式を含めてMarkdown化
        -   結果: `storage/test/vlm-poc/results/invoice_simple_result.md`
    -   ✅ **meeting_notes.pdf**: 成功（処理時間: 341秒、Markdown長: 1,273文字）⚡**再テスト成功**
        -   自治会の議事録PDFを階層構造を保持してMarkdown化
        -   メモリ10GB設定で問題なく処理完了
        -   結果: `storage/test/vlm-poc/results/meeting_notes_result.md`
    -   ✅ **receipt_01.jpg**: 成功（処理時間: 143秒、Markdown長: 2,509文字）⚡**画像対応後に成功**
        -   JPG形式のレシート画像を処理成功
        -   結果: `storage/test/vlm-poc/results/receipt_01_result.md`

### 3.7. メモリ制限の最適化 (追加検証)

-   **課題:** 初回テストでmeeting_notes.pdfがメモリ不足（Exit code -9）で失敗
-   **対策:** `docker-compose.yml`で`memory: 10G`を設定
-   **結果:** ✅ **成功** - 341秒で正常に処理完了
-   **結論:** 
    -   シンプルなPDF（1ページ、表1つ）: デフォルトメモリで動作可能
    -   複雑なPDF（1ページ、長文+階層構造）: 10GB推奨
    -   本番環境では安全マージンを考慮して10GB以上を推奨

### 3.8. 画像ファイル対応の実装 (2025年10月25日追加)

-   **目的:** JPG/PNG形式の画像ファイルもMarkerで処理できるようにする
-   **アプローチ:** 画像ファイルをPDFに変換してから処理する方式を実装
-   **実装内容:**
    1.  `docker/marker/app.py`を修正し、画像→PDF変換機能を追加
    2.  PILライブラリを使用してJPG/PNG→PDF変換を実装
    3.  RGBAモードの透明背景を白背景に変換する処理を追加
    4.  サポート形式を`.pdf`, `.jpg`, `.jpeg`, `.png`に拡張
-   **実装の詳細:**
    ```python
    # 画像ファイルの場合はPDFに変換
    if is_image:
        img = Image.open(tmp_img_path)
        # RGBモードに変換（PNGのアルファチャンネル対策）
        if img.mode in ("RGBA", "LA", "P"):
            background = Image.new("RGB", img.size, (255, 255, 255))
            background.paste(img, mask=img.split()[-1])
            img = background
        elif img.mode != "RGB":
            img = img.convert("RGB")
        # PDFとして保存
        img.save(tmp_converted_pdf.name, "PDF", resolution=100.0)
    ```
-   **テスト結果:**
    -   ✅ **receipt_01.jpg**: 成功（処理時間: 143秒、Markdown長: 2,509文字）
        -   日本語レシート画像を構造化データに変換成功
        -   日付「2022年11月19日」、タイトル「領収書」を正確に認識
        -   結果: `storage/test/vlm-poc/results/receipt_01_result.md`
    -   ✅ **hand_writing_01.png**: 成功（処理時間: 42秒、Markdown長: 569文字）
        -   PNG形式の手書きメモを処理成功
        -   透明背景も正しく白背景に変換されて処理完了
        -   結果: `storage/test/vlm-poc/results/hand_writing_01_result.md`
-   **性能比較:**
    -   画像ファイル（JPG/PNG）: 40-143秒（1ページ相当）
    -   PDFファイル: 340秒以上（複雑な文書）
    -   画像変換のオーバーヘッドは最小限（1秒未満）
-   **結論:** ✅ **画像対応完了** - JPG/PNG形式もPDF経由で正常に処理可能

### 3.9. 最終評価と次のステップ

#### ✅ **PoC達成項目**
1.  **PDF→Markdown変換の実証**: Markerを使用して日本語PDFをMarkdown形式で構造化抽出することに成功
2.  **画像対応の実装**: JPG/PNG形式の画像をPDF経由で処理可能に ✅ **NEW**
3.  **オンプレミス動作**: Dockerコンテナ内で完全に動作し、外部APIへの依存なし
4.  **OSSライセンス**: MITライセンスで商用利用可能
5.  **リアルタイムログ出力**: 処理進捗が確認できる仕組みを実装
6.  **APIラッパー実装**: FastAPIで`/extract/markdown`エンドポイントを提供

#### ⚠️ **判明した制約事項（更新版）**
1.  **処理時間**: 
    -   PDFファイル: 1ページ約5-6分（初回はモデルダウンロードで+1分）
    -   画像ファイル: 1枚約40-150秒（画像の複雑さによる）
2.  **メモリ使用量**: 複雑なPDFでは10GB推奨（シンプルなものはデフォルトでも可）✅ **解決済み**
3.  **対応フォーマット**: PDF、JPG、PNG ✅ **拡張済み**
4.  **日本語精度**: 基本的に高精度だが、一部の記号・特殊文字は文字化けの可能性

#### 📋 **次のステップ (Phase 1へ)**
1.  ✅ **メモリ制限の調整**: `docker-compose.yml`で10GBに設定済み
2.  ✅ **画像フォーマット対応**: JPG/PNG対応完了
3.  処理時間短縮のための最適化（バッチサイズ、解像度調整）
4.  エラーハンドリングの強化（タイムアウト、リトライ機構）
5.  LaravelからのAPI呼び出し実装（キュー処理で非同期化）

---
**PoC完了日:** 2025年10月25日  
**最終更新日:** 2025年10月25日（画像対応機能追加）  
**結論:** MarkerはLedgerLeapのVLM機能として技術的に実用可能。処理時間とメモリ使用量に注意しながらPhase 1での統合を推奨。PDF/JPG/PNGの全フォーマットに対応完了。
