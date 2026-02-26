# GitHub Issue #13 サブタスク作成用リスト

このファイルを使用して、GitHub上で手動で各サブタスクを作成してください。

---

## サブタスク #13-1: バリデーション状態管理の構築

**Title:** `[Subtask #13-1] バリデーション状態管理の構築`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
Livewireで各フィールドのバリデーション状態を一元管理するプロパティと更新ロジックを実装します。

## 実装内容
- `CreateColumn.php` に以下プロパティを追加
  - `$validationErrors`: エラーメッセージの辞書管理
  - `$errorsByGroup`: グループごとのエラーカウント管理
  - `$errorsByField`: フィールドごとのエラー状態フラグ
- エラー取得と更新メソッドの実装

## 実装ファイル
- `app/Livewire/Ledger/CreateColumn.php`
- `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php` (新規)

## 依存関係
- 前提タスク: なし
- 後続タスク: #13-2, #13-3

## 受け入れ基準
- [ ] バリデーション状態管理プロパティの実装完了
- [ ] エラー取得メソッド実装完了
- [ ] Livewireテストで正確性を確認
- [ ] コード整形（Pint）合格

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-2: エラーサマリーコンポーネントの実装

**Title:** `[Subtask #13-2] エラーサマリーコンポーネントの実装`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
フォーム上部に全エラーを集約表示するサマリーコンポーネントを実装します。

## 実装内容
- 新しいBladeコンポーネント作成：`resources/views/components/validation-error-summary.blade.php`
- 以下の表示要素：
  - エラーの総数表示
  - エラーを分類表示（必須項目不足、形式エラー等）
  - 各エラー項目（折りたたみ可能）
- CSS/Tailwind: エラースタイル（赤、警告色）

## 実装ファイル
- `resources/views/components/validation-error-summary.blade.php` (新規)
- `resources/views/livewire/ledger/create-column.blade.php`

## 依存関係
- 前提タスク: #13-1
- 後続タスク: #13-7, #13-9

## 受け入れ基準
- [ ] Bladeコンポーネント実装完了
- [ ] エラーサマリーが正確に表示される
- [ ] 見た目がUI/UXに適合している
- [ ] Blade/Alpine.jsテスト通過

## 予定工数
3-4時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-3: エラーグループ展開ロジックの実装

**Title:** `[Subtask #13-3] エラーグループ展開ロジックの実装`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
バリデーションエラーがあるグループを自動的に展開するロジックを実装します。

## 実装内容
- `CreateColumn.php` に `expandErrorGroups()` メソッド実装
- グループ状態管理プロパティの追加（`$expandedGroups`）
- エラー発生時の自動展開ロジック
- 手動での展開/折りたたみとの統合

## 実装ファイル
- `app/Livewire/Ledger/CreateColumn.php`
- `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`

## 依存関係
- 前提タスク: #13-1
- 後続タスク: #13-4

## 受け入れ基準
- [ ] メソッド実装完了
- [ ] エラー発生時に正確にグループが展開される
- [ ] 手動での展開/折りたたみが機能する
- [ ] Livewireテスト通過

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-4: エラーグループバッジ表示

**Title:** `[Subtask #13-4] エラーグループバッジ表示`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
エラーがあるグループにバッジで警告アイコンとエラーカウント表示を実装します。

## 実装内容
- グループのheader要素にバッジ追加
- バッジのデザイン（色、サイズ、フォント）
- エラー数が0になったときの自動削除ロジック
- Alpine.js: リアルタイムバッジ更新

## 実装ファイル
- `resources/views/livewire/ledger/create-column.blade.php`
- `resources/js/components/group-error-badge.js` (新規)

## 依存関係
- 前提タスク: #13-1, #13-3
- 後続タスク: なし

## 受け入れ基準
- [ ] バッジの表示/非表示が正確に機能する
- [ ] リアルタイム更新が動作する
- [ ] UIデザインがプロジェクト規約に適合している
- [ ] Blade/Alpine.jsテスト通過

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-5: エラーフィールドハイライト表示

**Title:** `[Subtask #13-5] エラーフィールドハイライト表示`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
バリデーションエラーのあるフィールドをビジュアル強調する機能を実装します。

## 実装内容
- フィールド周囲に赤いボーダー/背景色の追加
- エラーアイコン（❌）の表示
- インラインエラーメッセージの表示位置調整
- リアルタイムバリデーション時の状態更新

## 実装ファイル
- `resources/views/livewire/ledger/create-column.blade.php`
- `resources/css/validation-highlight.css` (新規)

## 依存関係
- 前提タスク: #13-1
- 後続タスク: #13-11

## 受け入れ基準
- [ ] エラーフィールドがハイライト表示される
- [ ] エラーアイコンが表示される
- [ ] インラインメッセージが正確に表示される
- [ ] UIテスト通過

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-6: プログレスバー統合

**Title:** `[Subtask #13-6] プログレスバー統合`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
フォーム完成度を示すプログレスバーにエラー状態を反映させます。

## 実装内容
- 既存のプログレスバーにエラー状態クラス（`progress-error`）追加
- 色分け表示：
  - 正常：緑色
  - エラー発生：赤色
- プログレス率の計算ロジック（必須項目完成率）

## 実装ファイル
- `resources/views/livewire/ledger/create-column.blade.php`

## 依存関係
- 前提タスク: #13-1
- 後続タスク: なし

## 受け入れ基準
- [ ] プログレスバーがエラー状態で色が変わる
- [ ] プログレス率が正確に計算される
- [ ] UIテスト通過

## 予定工数
1-2時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-7: エラーサマリークリック時の自動スクロール

**Title:** `[Subtask #13-7] エラーサマリークリック時の自動スクロール`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
エラーサマリー内のエラー項目をクリック→対応フィールドへ自動スクロールする機能を実装します。

## 実装内容
- Alpine.js: スクロール処理実装
- フィールドを一意に特定するID/classの付与
- スムーズスクロール（`scroll-behavior: smooth`）
- スクロール後のハイライト処理との連携

## 実装ファイル
- `resources/js/components/validation-error-navigator.js` (新規)
- `resources/views/components/validation-error-summary.blade.php`

## 依存関係
- 前提タスク: #13-2
- 後続タスク: #13-8, #13-10

## 受け入れ基準
- [ ] エラー項目クリック時にスクロールが動作する
- [ ] 対象フィールドに正確にスクロールされる
- [ ] スムーズスクロールが動作する
- [ ] ブラウザテスト通過

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-8: フィールド到達時の一時ハイライト

**Title:** `[Subtask #13-8] フィールド到達時の一時ハイライト`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
スクロール後にフィールドを一時的にハイライト表示するアニメーションを実装します。

## 実装内容
- フィールド周囲にパルスアニメーション（1-2秒）
- または枠線を点滅
- ハイライト終了後は通常表示に戻る
- CSS: Tailwindアニメーション活用（`animate-pulse` 等）

## 実装ファイル
- `resources/js/components/validation-error-navigator.js`
- `resources/css/field-highlight-animation.css` (新規)

## 依存関係
- 前提タスク: #13-7
- 後続タスク: なし

## 受け入れ基準
- [ ] アニメーション実装完了
- [ ] 1-2秒のアニメーション時間が正確である
- [ ] アニメーション終了後は通常表示に戻る
- [ ] ブラウザテスト通過

## 予定工数
1-2時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-9: キーボードショートカット実装

**Title:** `[Subtask #13-9] キーボードショートカット実装`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
Ctrl+E でエラーサマリーを開閉するキーボードショートカットを実装します。

## 実装内容
- Alpine.js: キーボードイベント監視
- `@keydown.ctrl.e` ディレクティブ使用
- サマリー開閉トグル処理
- テキスト入力中は発動しないようにフォーカス判定

## 実装ファイル
- `resources/views/livewire/ledger/create-column.blade.php`

## 依存関係
- 前提タスク: #13-2
- 後続タスク: なし

## 受け入れ基準
- [ ] Ctrl+E でサマリー開閉が動作する
- [ ] テキスト入力中は発動しない
- [ ] Livewire/Alpine.jsテスト通過

## 予定工数
1-2時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-10: エラー間ナビゲーション（前後ボタン）

**Title:** `[Subtask #13-10] エラー間ナビゲーション（前後ボタン）`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
「前のエラーへ」「次のエラーへ」ボタンを実装してエラー間のナビゲーションを実現します。

## 実装内容
- ボタン要素：次/前の矢印アイコン
- Alpine.js: エラースタック管理と順序追跡
- クリック時の自動スクロール + ハイライト
- 最後のエラーで前に戻る（ループ機能 or 無効化）

## 実装ファイル
- `resources/views/components/validation-error-summary.blade.php`
- `resources/js/components/validation-error-navigator.js`

## 依存関係
- 前提タスク: #13-7, #13-8
- 後続タスク: なし

## 受け入れ基準
- [ ] ボタンの実装完了
- [ ] 前後のナビゲーションが動作する
- [ ] ループ機能が期待通り動作する
- [ ] ブラウザテスト通過

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-11: フィールド修正時の成功チェックマーク表示

**Title:** `[Subtask #13-11] フィールド修正時の成功チェックマーク表示`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
ユーザーがエラーフィールドを修正すると即座にチェックマーク表示する機能を実装します。

## 実装内容
- `wire:change` または `@input` イベント時に修正を検知
- 簡易バリデーション実行
- エラーが消えたフィールドに「✓」アイコン表示
- 緑のボーダー/背景に変更
- 2秒後にアイコンが消える（フェードアウト）

## 実装ファイル
- `app/Livewire/Ledger/CreateColumn.php`
- `resources/views/livewire/ledger/create-column.blade.php`
- `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`

## 依存関係
- 前提タスク: #13-1, #13-5
- 後続タスク: #13-12

## 受け入れ基準
- [ ] 修正検知が正確に動作する
- [ ] チェックマーク表示/非表示が動作する
- [ ] アニメーション実装完了
- [ ] Livewireテスト通過

## 予定工数
2-3時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-12: エラー解決トースト通知

**Title:** `[Subtask #13-12] エラー解決トースト通知`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
ユーザーが複数のエラーを修正したとき、トースト通知で達成感を演出する機能を実装します。

## 実装内容
- エラー数追跡プロパティ（`$previousErrorCount`）
- エラーが減少したタイミングで通知発火
- 通知メッセージ例：「X個のエラーを修正しました！」
- 成功色（緑）のトースト表示
- Livewireの `dispatch('mary-toast')` 使用

## 実装ファイル
- `app/Livewire/Ledger/CreateColumn.php`
- `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`

## 依存関係
- 前提タスク: #13-1
- 後続タスク: #13-13

## 受け入れ基準
- [ ] エラー減少時に通知が発火する
- [ ] 通知メッセージが正確である
- [ ] 通知の色が正しい
- [ ] Livewireテスト通過

## 予定工数
1-2時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-13: 全エラー解決時のサマリー自動非表示

**Title:** `[Subtask #13-13] 全エラー解決時のサマリー自動非表示`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
すべてのエラーが解決されたら、エラーサマリーを自動的に非表示にする機能を実装します。

## 実装内容
- エラーカウント = 0 の判定
- Alpine.js: スムーズなフェードアウトアニメーション
- または自動スクロール（フォームトップへ）
- ユーザーが手動で再度表示できるオプション

## 実装ファイル
- `resources/views/components/validation-error-summary.blade.php`

## 依存関係
- 前提タスク: #13-2, #13-12
- 後続タスク: なし

## 受け入れ基準
- [ ] エラー = 0 時にサマリーが非表示になる
- [ ] フェードアウトアニメーションが動作する
- [ ] ユーザーが再度表示できる
- [ ] UIテスト通過

## 予定工数
1-2時間
```

**Labels:** `enhancement`, `validation-ux`

---

## サブタスク #13-14: Livewireユニット/フィーチャーテスト

**Title:** `[Subtask #13-14] Livewireユニット/フィーチャーテスト`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
各フェーズの機能をLivewireテストで検証する包括的なテストスイートを実装します。

## 実装内容
- テストファイル： `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php`
- テストケース：
  - バリデーション状態管理の正確性
  - エラーカウントの更新
  - グループ自動展開
  - トースト通知の発火
  - 修正時の成功チェックマーク

## 実装ファイル
- `tests/Feature/Livewire/Ledger/CreateColumnValidationTest.php` (新規)

## 依存関係
- 前提タスク: #13-1～#13-13
- 後続タスク: なし

## 受け入れ基準
- [ ] 全テストケース実装完了
- [ ] 全テスト通過
- [ ] テストカバレッジ >= 80%

## 予定工数
4-6時間
```

**Labels:** `enhancement`, `validation-ux`, `testing`

---

## サブタスク #13-15: ブラウザE2Eテスト（Dusk）

**Title:** `[Subtask #13-15] ブラウザE2Eテスト（Dusk）`

**Description:**
```
## 親Issue
#13 台帳入力フォームのバリデーションUX改善

## 説明
スクロール、ハイライト、キーボードショートカットなど、UIの実装をE2Eで検証する統合テストを実装します。

## 実装内容
- テストファイル： `tests/Browser/Ledger/CreateColumnValidationTest.php`
- テストシナリオ：
  1. フォーム表示確認
  2. 必須項目を未入力のままフォーム送信
  3. エラーサマリーが表示される確認
  4. サマリーのエラー項目をクリック→スクロール＆ハイライト動作確認
  5. キーボードショートカット(Ctrl+E)でサマリー開閉確認
  6. フィールドを修正→成功チェックマーク表示確認
  7. すべてのエラー解決→サマリー非表示確認

## 実装ファイル
- `tests/Browser/Ledger/CreateColumnValidationTest.php` (新規)

## 依存関係
- 前提タスク: #13-1～#13-13
- 後続タスク: なし

## 受け入れ基準
- [ ] 全テストシナリオ実装完了
- [ ] 全テスト通過
- [ ] Dusk環境で動作確認

## 予定工数
6-8時間
```

**Labels:** `enhancement`, `validation-ux`, `testing`, `e2e`

---

## 作成方法

1. GitHub上で **Issues** タブを開く
2. **New Issue** ボタンをクリック
3. 上記の **Title** と **Description** をコピペしてIssueを作成
4. **Labels** に `enhancement`, `validation-ux` を追加
5. **Assignee** を設定（必要に応じて）
6. **Milestone** を `バリデーションUX改善` に設定（なければ作成）

---

## 実装順序（推奨）

**Week 1:** #13-1 → #13-2 → #13-3  
**Week 2:** #13-4 → #13-5 → #13-6  
**Week 3:** #13-7 → #13-8 → #13-9 → #13-10  
**Week 4:** #13-11 → #13-12 → #13-13  
**Week 5:** #13-14 → #13-15


