# QRコードおよび事前入力リンクモーダルの統合・調和計画

## 1. 背景と課題

現在、LedgerLeapには類似した機能を持つ2つのダイアログ（モーダル）が存在しています。

1. **事前入力リンクダイアログ (`prefill-link-modal`)**:
   - **呼び出し元**: 台帳編集・作成画面のフッター（`HandlesPrefillLinks` トレイトを使用）
   - **機能**: 生成された長大なURLの表示、クリップボードへのコピー、SVG形式のQRコードの表示とダウンロード。
   - **UI**: 画面幅を広く使い、左側にURLと注意事項、右側にQRコードを配置するリッチな2カラムレイアウト（MaryUIベース）。長いURLに対する警告表示や、フォールバック付きのコピー機能など、UXが細かく作り込まれています。

2. **ページQRコードダイアログ (`PageQrCode`)**:
   - **呼び出し元**: グローバルナビゲーションバーのQRコードアイコンなど
   - **機能**: 現在のページURL（または指定URL）のQRコード表示と、URLのインラインコピー。
   - **UI**: 中央寄せのシンプルなレイアウト。MaryUI環境（`page-qr-code.blade.php`）とFilament環境（`page-qr-code-modal-content.blade.php`）の両方で動作するように設計されています。

**課題**:
- ユーザーにURLやQRコードを共有するという目的において機能が重複している。
- UIの雰囲気（表現方法）が異なり、システム全体としての統一感（一貫性）に欠ける。
- 特に`PageQrCode`側はコピー完了のフィードバックやレイアウトが簡素であり、`prefill-link-modal`で実装されているようなリッチな体験（QRコードのダウンロード機能など）が利用できない。

## 2. 対応アプローチの提案

これらを解決するために、以下の2つのアプローチが考えられます。今回は「両方のダイアログを活かす（ロジックや呼び出し元は分けつつ、見た目の雰囲気を統一する）」というご要望に沿って、**アプローチB（UIコンポーネントの共通化・調和）** を推奨します。

### アプローチA: 完全統合（単一の汎用コンポーネント化）
`QrCodeShareModal`のような単一のLivewireコンポーネントを作成し、事前入力リンク用とページ共有用の両方のUseCaseをカバーする。
* **メリット**: コードの重複が完全になくなり、保守性が高まる。
* **デメリット**: Filament環境での呼び出し（Action）とMaryUI環境での呼び出しの違いを吸収するための条件分岐が複雑になる。また、事前入力特有のロジック（長いURLの警告など）を汎用コンポーネントに持たせる必要がある。

### アプローチB: UI/UXの調和（推奨） ★
呼び出しロジック（`PageQrCode.php`と`HandlesPrefillLinks.php`）やコンポーネント自体は維持しつつ、**モーダル内の「見た目（ビュー）」を共通のBladeコンポーネントに寄せる**ことで、ユーザー体験を統一する。

* **メリット**: 現在のアーキテクチャ（Filamentとの互換性など）を破壊せずに、安全かつ素早く見た目を統一できる。
* **具体的な変更内容**:
  1. `page-qr-code.blade.php` のレイアウトを `prefill-link-modal.blade.php` のリッチなUI（アイコン付きのタイトル、URL表示用TextArea、コピーボタン、QRコードダウンロードボタン）に近い雰囲気に改修する。
  2. `page-qr-code-modal-content.blade.php`（Filament用）も同様にTailwindクラスを調整し、MaryUI版と同じ雰囲気を持たせる。
  3. 「長いURLに対する警告」などの事前入力特有の表示は、`PageQrCode`側では非表示（または不要）とする。
  4. URLコピー時のクリップボードAPIのフォールバック機能やToast通知のUXを両者で統一する。

## 3. 再確認後のステータス

2026-03-15 の再確認結果を反映した現時点のステータスです。

- [x] **Step 1: UI共通部分のBladeコンポーネント化（またはクラスの移植）**
  - `resources/views/components/common/qr-share-layout.blade.php` に URL / QR / コピー / ダウンロード UI を集約済み。
- [x] **Step 2: `PageQrCode` 側の機能強化**
  - MaryUI 側は共通レイアウトを利用し、QR ダウンロードとコピー UX を統一済み。
- [x] **Step 3: Filament用ビューの見た目調整**
  - `resources/views/livewire/common/page-qr-code-modal-content.blade.php` を 2 カラム構成へ寄せ、コピーのフォールバック・動的ダウンロード名・ヘッダー/フッター構成を共通レイアウトへ近づけて調整済み。
  - 追加調整として、Filament 標準モーダルヘッダーと本文ヘッダーの二重表示を解消し、見出しはモーダルシェル側へ一本化した。
- [x] **Step 4: ユーザー動線へ配慮した呼び出し制御の追加（台帳編集画面）**
  - `ledger.edit` / `ledger.create` では `PageQrCode` から `open-prefill-modal` を優先発火する分岐を維持。
  - 追加修正として `ledger.duplicate`（既存レコード流用の新規作成画面）も prefill 優先分岐へ含めた。
- [x] **Step 5: QRコードダウンロードのファイル名改善（カスタマイズ）**
  - `app/Services/QrCodeDownloadFileNameService.php` を追加し、MaryUI / Filament / Prefill で同じ命名規則を共有。
- [x] **Step 5.1: 翻訳キーの共通化**
  - `ledger.prefill.*` に寄っていた共通ラベルを `ledger.qr_share.*` へ整理し、日本語のコード埋め込みを除去。
- [ ] **Step 6: 手動・画面スモーク検証**
  - この再確認ではコード確認・Lint・対象 Feature テストまでは完了。
  - 実ブラウザでの MaryUI / Filament 両画面のクリック確認は、今回の作業記録上は未証跡のため未完了扱いとする。

## 4. 実装仕様（再確認後の確定事項）

### 4.1. ファイル名の命名規則

#### ページ共有 QR (`PageQrCode`)

形式:

```text
[文脈名_]画面種別用QR_YYYYMMDD_HHMMSS.svg
```

文脈名の解決ルール:

- `ledger.show`, `ledger.edit`
  - `Ledger` から `define` リレーションを辿り、**台帳定義タイトル** (`LedgerDefine.title`) を採用
- `ledger.create`, `ledgersByDefineId`, `ledger.import.show`
  - ルートパラメータの `ledgerDefineId` / `defineId` から **台帳定義タイトル** を採用
- `ledgersByFolderId`
  - `folderId` から **フォルダタイトル** を採用
- それ以外
  - 文脈名なしで `画面共有用QR_...svg`

画面種別の解決ルール:

- `ledger.index`, `ledgersByFolderId`, `ledgersByDefineId` → `台帳一覧`
- `ledger.show` → `台帳詳細`
- `ledger.edit` → `台帳編集`
- `ledger.create` → `台帳作成`
- `ledger.import.show` → `台帳インポート`
- その他 → `画面共有`

#### 事前入力 QR (`HandlesPrefillLinks`)

形式:

```text
[LedgerDefine.title]_事前入力用QR_YYYYMMDD_HHMMSS.svg
```

### 4.2. サニタイズ仕様

ダウンロード名は共通サービスで以下を適用する。

- `\\ / : * ? " < > |` は `_` に置換
- 連続空白は `_` に正規化
- 連続する `_` は 1 つに圧縮
- 先頭/末尾の `_`・`.`・空白は除去
- 1 セグメント 80 文字までに切り詰め

### 4.3. ラベル/翻訳キーの責務分離

- 共通ダイアログで使うラベルは `ledger.qr_share.*` に集約
  - 例: `url_label`, `copy_to_clipboard`, `copy_success`, `download_qr`, `qr_code_title`
- 事前入力専用の文言は `ledger.prefill.*` を維持
  - 例: `modal_title`, `description`, `long_url_warning`, `generate_link`, `qr_code_description`
- ページ共有専用の文言は `ledger.page_qr_code.*` を維持
  - 例: `modal_title`, `description`

### 4.4. 今回の再確認で補正したギャップ

- `prefill-link-modal.blade.php` に固定ファイル名 `prefill-qr-code.svg` が残っていた
  - → `:downloadName="$this->prefillDownloadFileName"` に統一
- `page-qr-code-modal-content.blade.php` に固定ファイル名 `page-qr-code.svg` が残っていた
  - → `PageQrCode` から動的 `downloadName` を受け渡すよう修正
- ファイル名解決が現在の Livewire リクエスト文脈に依存しやすかった
  - → 対象 URL を `router()->match(Request::create(...))` で再解決する共通サービスへ集約
- `LedgerDefine` の表示名に `name` を使う前提が混在していた
  - → 実データに合わせて `title` 基準へ修正
- 共通レイアウトが `ledger.prefill.url_label` など事前入力専用キーをデフォルト利用していた
  - → 共通キー `ledger.qr_share.*` を新設し、Prefill 側だけ明示的に専用ラベルを渡す構成へ変更
- Filament 側ビューが共通レイアウトより情報構造・フッター構成で乖離していた
  - → ヘッダー、URL領域、QR領域、モバイル向けヒント、アクション配置を共通レイアウトへ寄せて再調整
- Filament 側でモーダルタイトルと本文表題が重複して不自然だった
  - → `PageQrCode` の Filament Action に `modalHeading` / `modalDescription` を持たせ、本文側のヘッダーブロックを削除
- QRコード画像カードの余白バランスが不自然だった
  - → MaryUI / Filament 両方で QR カードを `w-fit + mx-auto + my-3 + px-4 py-5` ベースへ調整し、左右余白を減らして上下余白を追加
- 通常画面側の通知がクリックしないと消えない UX だった
  - → `mary-toast` の payload に `timeout: 2400` を追加し、自動消去されるよう変更
- 既存レコード流用の新規作成画面でメニューQRが通常共有ダイアログへ落ちていた
  - → 原因は `PageQrCode` の prefill 分岐対象が `ledger.create` / `ledger.edit` のみで、実導線の route 名 `ledger.duplicate` を含めていなかったこと
  - → `shouldUsePrefillModal()` に `ledger.duplicate` を追加し、回帰テスト `it_uses_prefill_modal_on_duplicate_create_screen()` を追加

## 5. 作業エビデンス

### 5.1. 関連ファイル

- `app/Services/QrCodeDownloadFileNameService.php`
- `app/Livewire/Common/PageQrCode.php`
- `app/Livewire/Traits/HandlesPrefillLinks.php`
- `lang/ja/ledger.php`
- `resources/views/components/ledger/prefill-link-modal.blade.php`
- `resources/views/livewire/common/page-qr-code-modal-content.blade.php`
- `resources/views/components/common/qr-share-layout.blade.php`
- `tests/Feature/Livewire/Common/PageQrCodeTest.php`
- `tests/Feature/Livewire/Ledger/PrefillParametersTest.php`

### 5.2. 自動検証ログ

実行コマンド:

```bash
./vendor/bin/sail pint app/Services/QrCodeDownloadFileNameService.php app/Livewire/Common/PageQrCode.php resources/views/components/common/qr-share-layout.blade.php resources/views/components/ledger/prefill-link-modal.blade.php resources/views/livewire/common/page-qr-code-modal-content.blade.php tests/Feature/Livewire/Common/PageQrCodeTest.php lang/ja/ledger.php
./vendor/bin/sail test tests/Feature/Livewire/Common/PageQrCodeTest.php tests/Feature/Livewire/Ledger/PrefillParametersTest.php
```

結果:

- Pint: **7 files checked, no violations**
- Test: **20 passed / 47 assertions**

### 5.3. 回帰テストで確認した代表ケース

- `契約/台帳:2026` を持つ編集画面 URL → `契約_台帳_2026_台帳編集用QR_20260315_133000.svg`
- 未知の URL → `画面共有用QR_20260315_133000.svg`
- `見積 / 依頼 : 2026  draft` を持つ事前入力 QR → `見積_依頼_2026_draft_事前入力用QR_20260315_133000.svg`
- MaryUI の `PageQrCode` モーダル → `共有URL` ラベルを表示し、`事前入力URL` は表示しない
- Filament モーダルコンテンツ → `ledger.qr_share.*` を利用し、`ledger.prefill.url_label` に依存しない
- MaryUI モーダル → 通知 payload に `timeout: 2400` を含み、自動消去設定が入る
- Filament モーダルコンテンツ → 本文内に `ledger.page_qr_code.modal_title` を重複表示しない
- `ledger.duplicate` 画面 → `PageQrCode` が `open-prefill-modal` 優先分岐となり、通常共有モーダルへ落ちない

## 6. 発生した問題と解決の手引き (トラブルシューティング)

今回の統合実装において、以下の問題が発生し、解決策を講じました。後から本機能をメンテナンスする際や同様の実装を行う際の参考にしてください。

### 6.1. Livewireプロパティ名とBladeバインディングの不一致
*   **事象**: `HandlesPrefillLinks.php` で `getPrefillQRCodeProperty()` として定義されたComputed Propertyが、Blade (`prefill-link-modal.blade.php`) で正しく呼び出されず、QRコード（SVG）が生成されない。
*   **調査**: Livewire v3では、旧来の `getProperty()` 形式で定義されたComputed Property（例: `getPrefillQRCodeProperty`）にアクセスする際、プロパティの命名規則により `$this->prefillQRCode` としてアクセスするのが正解です。しかし、一時的な実装において `$this->prefill_q_r_code` や `$this->qrCode` といった不整合が生じていました。
*   **解決と判断理由**: コントローラとBladeのプロパティ参照を `$this->prefillQRCode` に統一することで解決しました。

### 6.2. LivewireのDOM更新（Morphdom）とAlpine.jsの状態の乖離
*   **事象**: QRコード利用可能判定フラグである `qrCodeAvailable` をAlpine.jsの `x-data` キャッシュステートとして持たせ、Bladeの `@if` と混用した結果、LivewireによってDOMが更新された後もAlpine.jsのステートがリセットされず、UI（ダウンロードボタンの活性化など）が更新されたDOMと一致しない状態が発生。
*   **調査**: LivewireのMorphdomとAlpine.jsの密結合により、Alpine側のリアクティブな判定変数にLivewire起因のPHP変数をマージすると、更新サイクルがずれて意図しない挙動（ボタンがDisabledのまま、あるいは表示が消えるなど）になることが一般的です。
*   **解決と判断理由**: Alpine.js側の `qrCodeAvailable` ステート変数を削除し、純粋にBladeの属性バインディング `:disabled="!$qrCodeAvailable"` に依存するシンプルな構成にリファクタリングしました。これによりDOMの状態差分とAlpine.jsステートの乖離を根本から防いでいます。

### 6.3. Alpine.js の `$el` と `$root` コンテキストの違いによるDOM探索失敗
*   **事象**: ダウンロードボタンをクリックした際、JavaScript内で `QRコードを利用できません` エラートーストが表示され、生成・表示されているはずのSVGが見つからない。
*   **調査**: Alpine.js v3環境において、コンポーネントメソッド内（`async downloadQRCode() { ... }`）で `this.$el` を参照すると、場合により**イベントのトリガーとなったDOMノード**（この場合は `<button>` 要素自身）がコンテキストとして入り込む仕様（または不確実にバインドされる制約）があります。これにより、`this.$el.querySelector('.qr-svg-container svg')` の探索範囲が限定され、ボタン内の子要素しか検索されないため `null` が返されていました。
*   **解決と判断理由**: コンポーネントツリーの最上位（ルート要素）から安全に要素を探索するため、`this.$el` を `this.$root` に置換しました。これによりダウンロードボタンが発火した際でも、確実にコンポーネント全体から対象のSVGを探索・取得できるようになりました。

## 7. より良い仕様への提案

1. **ファイル名に route category を明示的に残す**
   - 例: `契約台帳_台帳編集_page-share_...svg`, `契約台帳_prefill_...svg`
   - 日本語だけでも運用可能だが、将来的な API / 外部連携を考えると機械可読な suffix を追加すると扱いやすい。
2. **長すぎるファイル名への追加対策**
   - 現在はセグメント単位で 80 文字制限。OS 互換性をより強めるなら、最終ファイル名全体でも 120〜150 文字程度に丸めると安全。
3. **手動スモークの証跡化**
   - issue / docs へ「MaryUI 画面」「Filament 画面」「台帳編集画面で prefill に分岐」の 3 スクリーンショットまたは短い操作ログを毎回残す。
4. **将来的なユーザー指定ファイル名**
   - 共有先が多い運用では、台帳定義タイトルだけでなく `tenant` や `folder` を先頭辞へ入れる需要がある。設定化は follow-up issue に切る価値がある。

