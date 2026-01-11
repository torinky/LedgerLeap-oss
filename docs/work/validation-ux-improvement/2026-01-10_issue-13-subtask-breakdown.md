# Issue #13「台帳入力フォームのバリデーションUX改善」サブタスク細分化計画

**作成日:** 2026年1月10日  
**対象Issue:** #13 台帳入力フォームのバリデーションUX改善  
**対象コンポーネント:** `app/Livewire/Ledger/CreateColumn.php` と関連Bladeテンプレート  

---

## 概要

Issue #13は現在「Phase 1-4」の4つのフェーズに分割されていますが、機能が混在し、粒度が大きすぎます。
以下に**15個の細粒度サブタスク**に再分割し、1-2日で完結する粒度に調整したものを示します。

---

## 実装ロードマップ（推奨順序）

### グループA: 基盤実装（Week 1-2）

#### 【優先度: ⭐⭐⭐】

##### **Subtask #13-1: バリデーション状態管理の構築**
- **説明:** Livewireで各フィールドのバリデーション状態を一元管理するプロパティと更新ロジック
- **作業内容:**
  - `CreateColumn.php` に以下プロパティを追加
    - `$validationErrors`: エラーメッセージの辞書管理
    - `$errorsByGroup`: グループごとのエラーカウント管理
    - `$errorsByField`: フィールドごとのエラー状態フラグ
  - エラー取得と更新メソッドの実装
- **依存関係:** なし
- **テスト:** Livewireテスト（バリデーション状態の正確性）
- **所要時間:** 2-3時間
- **ファイル:**
  - `app/Livewire/Ledger/CreateColumn.php`
  - `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php` (新規)

---

##### **Subtask #13-2: エラーサマリーコンポーネントの実装**
- **説明:** フォーム上部に全エラーを集約表示するサマリーコンポーネント
- **作業内容:**
  - 新しいBladeコンポーネント作成：`resources/views/components/validation-error-summary.blade.php`
  - 以下の表示要素：
    - エラーの総数表示
    - エラーを分類表示（必須項目不足、形式エラー等）
    - 各エラー項目（折りたたみ可能）
  - CSS/Tailwind: エラースタイル（赤、警告色）
- **依存関係:** Subtask #13-1
- **テスト:** Blade/Alpine.jsコンポーネント表示テスト
- **所要時間:** 3-4時間
- **ファイル:**
  - `resources/views/components/validation-error-summary.blade.php` (新規)
  - `resources/views/livewire/ledger/create-column.blade.php`

---

##### **Subtask #13-3: エラーグループ展開ロジックの実装**
- **説明:** バリデーションエラーがあるグループを自動的に展開する
- **作業内容:**
  - `CreateColumn.php` に `expandErrorGroups()` メソッド実装
  - グループ状態管理プロパティの追加（`$expandedGroups`）
  - エラー発生時の自動展開ロジック
  - 手動での展開/折りたたみとの統合
- **依存関係:** Subtask #13-1
- **テスト:** Livewireテスト（エラー時のグループ自動展開）
- **所要時間:** 2-3時間
- **ファイル:**
  - `app/Livewire/Ledger/CreateColumn.php`
  - `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`

---

### グループB: 視覚的フィードバック（Week 2-3）

#### 【優先度: ⭐⭐⭐】

##### **Subtask #13-4: エラーグループバッジ表示**
- **説明:** エラーがあるグループにバッジで警告アイコンとエラーカウント表示
- **作業内容:**
  - グループのheader要素にバッジ追加
  - バッジのデザイン（色、サイズ、フォント）
  - エラー数が0になったときの自動削除ロジック
  - Alpine.js: リアルタイムバッジ更新
- **依存関係:** Subtask #13-1, #13-3
- **テスト:** Blade表示テスト、Alpine.jsバッジ更新テスト
- **所要時間:** 2-3時間
- **ファイル:**
  - `resources/views/livewire/ledger/create-column.blade.php`
  - `resources/js/components/group-error-badge.js` (新規)

---

##### **Subtask #13-5: エラーフィールドハイライト表示**
- **説明:** バリデーションエラーのあるフィールドをビジュアル強調
- **作業内容:**
  - フィールド周囲に赤いボーダー/背景色の追加
  - エラーアイコン（❌）の表示
  - インラインエラーメッセージの表示位置調整
  - リアルタイムバリデーション時の状態更新
- **依存関係:** Subtask #13-1
- **テスト:** CSS/UIコンポーネント表示テスト
- **所要時間:** 2-3時間
- **ファイル:**
  - `resources/views/livewire/ledger/create-column.blade.php`
  - `resources/css/validation-highlight.css` (新規)

---

##### **Subtask #13-6: プログレスバー統合**
- **説明:** フォーム完成度を示すプログレスバーにエラー状態を反映
- **作業内容:**
  - 既存のプログレスバーにエラー状態クラス（`progress-error`）追加
  - 色分け表示：
    - 正常：緑色
    - エラー発生：赤色
  - プログレス率の計算ロジック（必須項目完成率）
- **依存関係:** Subtask #13-1
- **テスト:** UI統合テスト
- **所要時間:** 1-2時間
- **ファイル:**
  - `resources/views/livewire/ledger/create-column.blade.php`

---

### グループC: ナビゲーション機能（Week 3-4）

#### 【優先度: ⭐⭐】

##### **Subtask #13-7: エラーサマリークリック時の自動スクロール**
- **説明:** エラーサマリー内のエラー項目をクリック→対応フィールドへ自動スクロール
- **作業内容:**
  - Alpine.js: スクロール処理実装
  - フィールドを一意に特定するID/classの付与
  - スムーズスクロール（`scroll-behavior: smooth`）
  - スクロール後のハイライト処理との連携
- **依存関係:** Subtask #13-2
- **テスト:** ブラウザテスト（スクロール動作確認）
- **所要時間:** 2-3時間
- **ファイル:**
  - `resources/js/components/validation-error-navigator.js` (新規)
  - `resources/views/components/validation-error-summary.blade.php`

---

##### **Subtask #13-8: フィールド到達時の一時ハイライト**
- **説明:** スクロール後にフィールドを一時的にハイライト表示
- **作業内容:**
  - フィールド周囲にパルスアニメーション（1-2秒）
  - または枠線を点滅
  - ハイライト終了後は通常表示に戻る
  - CSS: Tailwindアニメーション活用（`animate-pulse` 等）
- **依存関係:** Subtask #13-7
- **テスト:** ブラウザテスト（ハイライトアニメーション）
- **所要時間:** 1-2時間
- **ファイル:**
  - `resources/js/components/validation-error-navigator.js`
  - `resources/css/field-highlight-animation.css` (新規)

---

##### **Subtask #13-9: キーボードショートカット実装**
- **説明:** Ctrl+E で エラーサマリーを開閉する
- **作業内容:**
  - Alpine.js: キーボードイベント監視
  - `@keydown.ctrl.e` ディレクティブ使用
  - サマリー開閉トグル処理
  - テキスト入力中は発動しないようにフォーカス判定
- **依存関係:** Subtask #13-2
- **テスト:** Livewireテスト、ブラウザテスト
- **所要時間:** 1-2時間
- **ファイル:**
  - `resources/views/livewire/ledger/create-column.blade.php`

---

##### **Subtask #13-10: エラー間ナビゲーション（前後ボタン）**
- **説明:** 「前のエラーへ」「次のエラーへ」ボタンを実装
- **作業内容:**
  - ボタン要素：次/前の矢印アイコン
  - Alpine.js: エラースタック管理と順序追跡
  - クリック時の自動スクロール + ハイライト
  - 最後のエラーで前に戻る（ループ機能 or 無効化）
- **依存関係:** Subtask #13-7, #13-8
- **テスト:** ブラウザテスト、ナビゲーション順序テスト
- **所要時間:** 2-3時間
- **ファイル:**
  - `resources/views/components/validation-error-summary.blade.php`
  - `resources/js/components/validation-error-navigator.js`

---

### グループD: 成功フィードバック（Week 4-5）

#### 【優先度: ⭐⭐】

##### **Subtask #13-11: フィールド修正時の成功チェックマーク表示**
- **説明:** ユーザーがエラーフィールドを修正すると即座にチェックマーク表示
- **作業内容:**
  - `wire:change` または `@input` イベント時に修正を検知
  - 簡易バリデーション実行
  - エラーが消えたフィールドに「✓」アイコン表示
  - 緑のボーダー/背景に変更
  - 2秒後にアイコンが消える（フェードアウト）
- **依存関係:** Subtask #13-1, #13-5
- **テスト:** Livewireテスト（修正検知）、UIテスト
- **所要時間:** 2-3時間
- **ファイル:**
  - `app/Livewire/Ledger/CreateColumn.php`
  - `resources/views/livewire/ledger/create-column.blade.php`
  - `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`

---

##### **Subtask #13-12: エラー解決トースト通知**
- **説明:** ユーザーが複数のエラーを修正したとき、トースト通知で達成感を演出
- **作業内容:**
  - エラー数追跡プロパティ（`$previousErrorCount`）
  - エラーが減少したタイミングで通知発火
  - 通知メッセージ例：「X個のエラーを修正しました！」
  - 成功色（緑）のトースト表示
  - Livewireの `dispatch('mary-toast')` 使用
- **依先関係:** Subtask #13-1
- **テスト:** Livewireテスト（通知の発火条件）
- **所要時間:** 1-2時間
- **ファイル:**
  - `app/Livewire/Ledger/CreateColumn.php`
  - `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`

---

##### **Subtask #13-13: 全エラー解決時のサマリー自動非表示**
- **説明:** すべてのエラーが解決されたら、エラーサマリーを自動的に非表示に
- **作業内容:**
  - エラーカウント = 0 の判定
  - Alpine.js: スムーズなフェードアウトアニメーション
  - または自動スクロール（フォームトップへ）
  - ユーザーが手動で再度表示できるオプション
- **依存関係:** Subtask #13-2, #13-12
- **テスト:** UIテスト（アニメーション動作）
- **所要時間:** 1-2時間
- **ファイル:**
  - `resources/views/components/validation-error-summary.blade.php`

---

### グループE: テスト実装（Week 5）

#### 【優先度: ⭐⭐】

##### **Subtask #13-14: Livewireユニット/フィーチャーテスト**
- **説明:** 各フェーズの機能をLivewireテストで検証
- **作業内容:**
  - テストファイル： `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php` (新規)
  - テストケース：
    - バリデーション状態管理の正確性
    - エラーカウントの更新
    - グループ自動展開
    - トースト通知の発火
    - 修正時の成功チェックマーク
  - 各テストは独立して実行可能
- **依存関係:** Subtask #13-1～13-13
- **テスト:** 全ユニットテスト通過
- **所要時間:** 4-6時間
- **ファイル:**
  - `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php` (新規)

---

##### **Subtask #13-15: ブラウザE2Eテスト（Dusk）**
- **説明:** スクロール、ハイライト、キーボードショートカットなど、UIの実装をE2Eで検証
- **作業内容:**
  - テストファイル： `tests/Browser/Ledger/CreateColumnValidationTest.php` (新規)
  - テストシナリオ：
    1. フォーム表示
    2. 必須項目を未入力のままフォーム送信
    3. エラーサマリーが表示される
    4. サマリーのエラー項目をクリック→スクロール＆ハイライト動作
    5. キーボードショートカット(Ctrl+E)でサマリー開閉
    6. フィールドを修正→成功チェックマーク表示
    7. すべてのエラー解決→サマリー非表示
  - ブラウザ操作のタイミング調整（待機時間）
- **依存関係:** Subtask #13-1～13-13
- **テスト:** E2Eテスト全シナリオ通過
- **所要時間:** 6-8時間
- **ファイル:**
  - `tests/Browser/Ledger/CreateColumnValidationTest.php` (新規)

---

## 実装タイムライン

| 週 | タスク | 予定 |
|-----|--------|------|
| Week 1 | #13-1, #13-2, #13-3 | 基盤実装（7-9時間） |
| Week 2 | #13-4, #13-5, #13-6 | 視覚的フィードバック（5-8時間） |
| Week 3 | #13-7, #13-8, #13-9, #13-10 | ナビゲーション機能（7-10時間） |
| Week 4 | #13-11, #13-12, #13-13 | 成功フィードバック（4-7時間） |
| Week 5 | #13-14, #13-15 | テスト実装（10-14時間） |
| **合計** | - | **33-48時間**（4-6週） |

---

## 各サブタスク作成時の情報

### GitHub Issue作成時のテンプレート

```markdown
## 親Issue
[#13 台帳入力フォームのバリデーションUX改善](#13)

## 説明
[上記タスクの説明を記載]

## 実装ファイル
- `app/Livewire/Ledger/CreateColumn.php`
- `resources/views/livewire/ledger/create-column.blade.php`
- [その他]

## テスト対象
- [ ] ユニット/フィーチャーテスト
- [ ] ブラウザテスト

## 関連タスク
- Subtask #13-X （前提タスク）
- Subtask #13-Y （後続タスク）

## 受け入れ基準
- [ ] 実装完了
- [ ] テスト通過
- [ ] コード品質（Pint）合格
- [ ] ドキュメント更新
```

---

## 補足

### 実装順序の選択肢

**Option A: 連続的実装（推奨）**
- グループA → B → C → D → E の順序
- メリット：依存関係が少なく、レビューしやすい
- デメリット：テストが最後になる

**Option B: 並列実装**
- Subtask #13-1 実装後、#13-2～#13-6 は同時進行可能
- メリット：期間短縮
- デメリット：依存関係の管理が複雑

**推奨:** Option A での連続実装

---

## 参考資料

- Livewire 3 Validation: https://livewire.laravel.com/docs/validation
- Alpine.js: https://alpinejs.dev/
- Tailwind CSS Animations: https://tailwindcss.com/docs/animation


