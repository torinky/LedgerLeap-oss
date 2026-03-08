# Issue #57: 全画面QRコード生成ボタン 実装計画書

**作成日:** 2026年3月8日
**ステータス:** 📝 計画段階
**関連Issue:** [#57 画面に直接アクセスできるQRコード生成](https://github.com/torinky/LedgerLeap/issues/57)
**関連ドキュメント:**
- [ペルソナ・ユースケース・シナリオ](../../../function/PersonaUseCaseScenario.md)
- [共通ヘッダー「設定」メニュー実装計画書](./2025-09-08_centralized-settings-menu-implementation-plan.md)

---

## 1. 目的と背景

LedgerLeapはPCでのデスクワークだけでなく、タブレットやスマートフォンを用いた現場での利用も想定されています。現在、PCで閲覧している画面（特定の検索条件で絞り込んだ台帳リストや、入力途中のフォームなど）を、現場持ち出し用のモバイル端末にスムーズに引き継ぐ手段がURLのコピー＆ペーストに限られており、非効率です。

本計画では、**現在のURL（クエリパラメータを含む）を即座にQRコード化して表示するボタン**を全画面に配置することで、デバイス間のシームレスな連携を実現します。

## 2. ペルソナとユースケース

`docs/function/PersonaUseCaseScenario.md` で定義されたペルソナに基づき、本機能が解決する具体的なシナリオを定義します。

### 2.1. 実務担当者 (Operational Staff) & 現場リーダー (Team Leader)
*   **シナリオ: 検索結果の現場持ち出し**
    *   **状況:** 事務所のPCで「来週点検予定」かつ「未完了」の設備台帳を検索・絞り込んだ。このリストを持ったまま現場へ移動し、タブレットで点検結果を入力したい。
    *   **課題:** URLを手入力したり、メールで自分宛に送るのは手間がかかる。
    *   **解決策:** PC画面でQRコードボタンを押し、タブレットのカメラで読み取るだけで、同じ検索条件のリスト画面がタブレットで開く。

### 2.2. 現場リーダー/作業班長 (Team Leader / Foreman)
*   **シナリオ: 朝礼での情報共有**
    *   **状況:** 大型モニターに映した「本日の重要注意事項」や「作業手順書（添付ファイル）」を、メンバー各自のスマホで手元に表示させたい。
    *   **解決策:** 画面上のQRコードを表示し、メンバーがそれを読み取ることで、即座に全員が同じドキュメントにアクセスできる。

### 2.3. 管理者 (Administrator)
*   **シナリオ: 設定画面のモバイル確認**
    *   **状況:** PCで設定した権限やレイアウトが、モバイル端末でどのように表示されるか実機で検証したい。
    *   **解決策:** 管理画面でもQRコードを表示し、即座に実機で確認を行う。

## 3. 機能要件

1.  **全画面配置**:
    *   フロントエンド（MaryUI/DaisyUI）のナビゲーションバー。
    *   管理画面（Filament）のグローバルヘッダー。
2.  **QRコード生成**:
    *   現在のページURL（GETパラメータ等のクエリを含む完全なURL）をエンコードする。
    *   非同期（Livewire）で生成し、モーダルで表示する。
3.  **モーダル機能**:
    *   QRコードの表示（SVG形式推奨）。
    *   URLテキストの表示と「クリップボードにコピー」ボタン。
    *   タイトル：「この画面をモバイルで開く」。

## 4. 実装詳細

### 4.1. コンポーネント設計

**Livewireコンポーネント: `App\Livewire\Common\PageQrCode`**

*   **プロパティ**:
    *   `$triggerType`: ボタンのスタイル切り替え用（'mary' | 'filament'）。
    *   `$url`: クライアント側から渡されるか、リクエストから取得。※Livewire 3の `request()->fullUrl()` は初期レンダリング時のURLを指すため、動的なパラメータ変更（検索など）への追従には注意が必要。ボタンクリック時にJSから `window.location.href` を渡すか、Livewireのナビゲート完了をフックする必要があるが、今回はシンプルにサーバーサイドの `request()->fullUrl()` を基本としつつ、必要ならJSでURLを渡す方式を検討。
*   **機能**:
    *   `simple-qrcode` ライブラリを使用してQRコードを生成。
    *   `render` でモーダルを表示。

### 4.2. UI配置

1.  **フロントエンド (`resources/views/layouts/daisyuiNavigation.blade.php`)**:
    *   `navbar-end` エリア、テーマ切り替えボタンの左隣に配置。
    *   アイコン: `o-qr-code` (Heroicons) / `fa-qrcode` (FontAwesome)。

2.  **管理画面 (`app/Providers/Filament/AdminPanelProvider.php`)**:
    *   `renderHook('panels::global-search.after')` を使用して配置。
    *   Filament用のスタイル（`x-filament::button` 等）を適用したビューを使用。

## 5. 実装WBS (スプリント計画)

### Sprint 1: 基盤実装とフロントエンド適用 (工数: 2h)
- [ ] **Task 1.1**: 翻訳ファイルの更新 (`lang/ja/ledger.php`)。
    - `page_qr_code` セクションの追加。
- [ ] **Task 1.2**: Livewireコンポーネント `App\Livewire\Common\PageQrCode` の作成。
    - QRコード生成ロジックの実装。
- [ ] **Task 1.3**: Bladeビュー `resources/views/livewire/common/page-qr-code.blade.php` の作成。
    - MaryUI モーダルとトリガーボタンの実装。
- [ ] **Task 1.4**: `daisyuiNavigation.blade.php` への組み込み。

### Sprint 2: 管理画面適用とテスト (工数: 2h)
- [ ] **Task 2.1**: Filament用ビューの調整（必要に応じて分岐または別ファイル化）。
- [ ] **Task 2.2**: `AdminPanelProvider` への `renderHook` 登録。
- [ ] **Task 2.3**: Feature Test の作成 (`tests/Feature/Livewire/Common/PageQrCodeTest.php`)。
    - コンポーネントのレンダリング確認。
    - QRコード生成の確認。

## 6. リスクと対策

*   **URLの長さ**: 非常に長い検索クエリの場合、QRコードが細かくなりすぎて読み取りにくくなる可能性がある。
    *   *対策*: `size(250)` 程度を確保し、モーダル内を大きく使う。必要であれば `errorCorrection('L')` で密度を下げる検討をする。
*   **Filamentのスタイル競合**: MaryUIコンポーネントがFilament内で正しく表示されない場合がある。
    *   *対策*: Filament内ではFilamentネイティブのコンポーネント (`x-filament::modal`, `x-filament::icon-button` 等) を使用し、通知には `FilamentNotification` (JS) を使用する。`resources/views/livewire/common/page-qr-code.blade.php` 内で `triggerType` に応じて条件分岐を行う。

---
