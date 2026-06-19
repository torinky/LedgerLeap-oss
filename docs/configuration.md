# 設定ガイド

LedgerLeap の主要な設定項目は `.env` ファイルで管理します。以下にカテゴリ別の設定一覧とデフォルト値を示します。

## VLM/OCR 設定

> **自動検出**: `bin/setup.sh` が環境を自動判定し、最適な設定を適用します。  
> 特別な要件がない限り、`.env.example` のデフォルト値のままで動作します。

### バックエンド選択

`VLM_MODEL` で使用する VLM バックエンドを指定します。

| 値 | 対象環境 | 説明 |
|---|---|---|
| `paddleocr` | 汎用 | 従来の PaddleOCR（OCR のみ、安定） |
| `paddleocr-vl` | x86 + NVIDIA GPU | PaddleOCR-VL（GPU 加速、最高品質） |
| `paddleocr-vl-cpu` | x86 + CPU | PaddleOCR-VL-1.6（CPU 最適化） |
| `paddleocr-vl-mlx` | Mac Apple Silicon | MLX-VLM 経由（Metal GPU ホスト直接実行） |
| `marker` | 汎用 | Marker（PDF → Markdown） |
| `mineru` | 汎用 | MinerU（PDF → Markdown） |
| `auto` | **推奨** | 環境を自動判定（Mac → MLX、x86+GPU → VL、x86+CPU → VL-CPU） |

> `auto` は `bin/setup.sh` が自動設定します。手動設定する場合は、上記の値を直接指定してください。

### デバイス選択

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `PADDLEOCR_DEVICE` | `auto` | `auto` → ホスト上の NVIDIA GPU を自動検出。手動指定は `gpu` / `cpu` |

> `auto` の場合、`nvidia-smi` が利用可能であれば自動的に `gpu` に設定されます。

### VLM 接続設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `VLM_ENABLED` | `true` | VLM 機能の有効/無効 |
| `VLM_URL` | `http://vlm:8000` | VLM API の URL。Mac MLX 時は `http://host.docker.internal:8000` に自動設定 |
| `VLM_DEFAULT_MODEL` | `PaddleOCR-VL-1.6` | 使用するデフォルトモデル名 |
| `VLM_TIMEOUT` | `300` | VLM 処理のタイムアウト（秒） |
| `VLM_MAX_FILE_SIZE` | `10485760` | 処理可能な最大ファイルサイズ（バイト、デフォルト 10MB） |

### Mac Apple Silicon (MLX-VLM)

Mac M1/M2/M3/M4 の場合、`bin/setup.sh` が自動的に MLX-VLM をセットアップします。

```bash
# セットアップ完了後、別ターミナルで MLX サーバーを起動
./scripts/start-vlm-mlx.sh

# 環境チェックのみ
./scripts/start-vlm-mlx.sh --check

# 依存関係のインストールのみ
./scripts/start-vlm-mlx.sh --install
```

MLX-VLM は Docker 内では動作しません（Apple Metal GPU に直接アクセスする必要があるため）。  
Mac ホスト上で `unified_api.py` が起動し、Laravel (Sail) は `host.docker.internal:8000` 経由で接続します。

### 環境変数の自動設定フロー

```
bin/setup.sh 実行
  ├── PADDLEOCR_DEVICE=auto → nvidia-smi 検出 → gpu/cpu に自動設定
  ├── x86_64 + GPU → VLM_MODEL=paddleocr-vl
  ├── x86_64 + CPU → VLM_MODEL=paddleocr-vl-cpu
  ├── Mac + arm64   → MLX-VLM 導入試行
  │     ├── 成功 → VLM_MODEL=auto, VLM_URL=host.docker.internal:8000
  │     └── 失敗 → Docker vlm にフォールバック
  └── ARM64 Linux   → VLM_MODEL=paddleocr
```

ユーザーが `.env` で明示的に値を設定している場合は、自動検出をスキップしてその値が優先されます。

---

## アプリケーション基本設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `APP_NAME` | `LedgerLeap` | アプリケーション名 |
| `APP_ENV` | `local` | 実行環境 (`local` / `production`) |
| `APP_DEBUG` | `true` | デバッグモード（本番では必ず `false`） |
| `APP_URL` | `http://localhost` | アプリケーションのベース URL |

## ブランディング設定

> **実装**: Issue [#222](https://github.com/torinky/LedgerLeap/issues/222) にて config 化（2026-05-30）

`config/ledgerleap.php` の `branding` セクションで一元管理され、以下のすべての項目が `.env` からオーバーライド可能です。未設定の場合はシステムデフォルトが使用されます。

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `APP_NAME` | `LedgerLeap` | アプリケーション名。ブラウザタブのタイトル、ナビゲーションバー、Filament 管理画面のブランド名に表示 |
| `APP_SHORT_NAME` | （空欄時は `APP_NAME` の先頭2文字） | 狭い画面での省略表記 |
| `APP_LOGO` | `images/icon.svg` | ナビゲーションバーおよび Filament 管理画面のロゴ画像パス |
| `APP_LOGO_DARK` | （空欄） | ダークモード用ロゴ画像パス。未設定時は `APP_LOGO` を使用 |
| `APP_FAVICON` | `favicon.ico` | ブラウザタブ・ブックマークに表示されるファビコン |
| `APP_COPYRIGHT_OWNER` | `APP_NAME` の値 | フッターの著作権表記の権利者名 |
| `APP_COPYRIGHT_YEAR` | 現在の西暦 | 著作権表記の開始年（例: `2025` → `© 2025–2026`） |
| `APP_TAGLINE` | （空欄） | キャッチフレーズ（将来の用途のため予約） |
| `APP_SUPPORT_URL` | （空欄） | サポートページの URL。設定時はフッターにリンク表示 |
| `APP_SUPPORT_EMAIL` | （空欄） | サポートメールアドレス。設定時はフッターに `mailto:` リンク表示 |
| `APP_FORUM_URL` | （空欄） | フォーラムの URL。設定時はフッターにリンク表示 |

### 設定例（`.env`）

```bash
APP_NAME="My Company Ledger"
APP_SHORT_NAME="MCL"
APP_LOGO=images/my-logo.svg
APP_COPYRIGHT_OWNER="My Company, Inc."
APP_COPYRIGHT_YEAR=2024
APP_SUPPORT_URL=https://support.example.com
APP_SUPPORT_EMAIL=support@example.com
APP_FORUM_URL=https://community.example.com
```

### 表示される箇所

| 画面 | 使用する設定 |
|---|---|
| ナビゲーションバー | `APP_NAME` / `APP_SHORT_NAME` / `APP_LOGO` / `APP_LOGO_DARK` |
| ブラウザタブタイトル | `APP_NAME` |
| フッター（一般画面） | `APP_COPYRIGHT_OWNER` / `APP_COPYRIGHT_YEAR` / `APP_SUPPORT_URL` / `APP_SUPPORT_EMAIL` / `APP_FORUM_URL` |
| Filament 管理画面 | `APP_NAME` / `APP_LOGO` / `APP_FAVICON` / フッター（共通） |

## データベース設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `DB_CONNECTION` | `mysql` | データベース接続種別 |
| `DB_HOST` | `127.0.0.1` | データベースホスト |
| `DB_PORT` | `3306` | ポート番号 |
| `DB_DATABASE` | `ledgerleap` | データベース名 |
| `DB_USERNAME` | `sail` | ユーザー名 |
| `DB_PASSWORD` | `password` | パスワード |

> [!NOTE]
> LedgerLeap は全文検索に **Mroonga** を使用します。Mroonga が有効な MySQL/MariaDB 環境が必須です。

## ファイル処理設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `FILE_PROCESSING_TIMEOUT_HOURS` | `24` | ファイル処理ジョブのタイムアウト時間 |

## スコアリングシステム設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `SCORING_SCHEDULE_FREQUENCY` | `daily` | スコア計算の実行頻度（`daily` / `hourly` / `weekly`） |

## パフォーマンスモニタリング設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `PERFORMANCE_MONITORING_ENABLED` | `local` 環境では `true` | パフォーマンス測定の有効化 |
| `PERFORMANCE_LOG_DESTINATION` | `both` | ログ出力先（`log` / `json` / `both` / `none`） |

## 自動リンク設定

| 変数名 | 既定値 | 説明 |
|---|---|---|
| `AUTO_LINK_BASE_URL` | `http://localhost` | テナント識別方式に応じた自動リンクのベース URL |

---

> 設定項目の詳細は `config/ledgerleap.php` を参照してください。ブランディング機能は Issue [#222](https://github.com/torinky/LedgerLeap/issues/222) で実装されました。
