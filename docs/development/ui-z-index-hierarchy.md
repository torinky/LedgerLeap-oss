# LedgerLeap UI Z-Index 階層管理

UIコンポーネント間の重なり順（z-index）を管理し、モーダルがパネルの裏に隠れる現象を防止します。

## 主要な Z-Index 値
- **10000+**: 極めて優先度の高い要素
    - **10000**: トースト通知 (`.toast`) - 他の全てのパネルやモーダルよりも前面に表示する必要があります。
- **9999**: ツールチップ (`before:z-[9999]`)
- **9999 (Teleported)**: 重要なモーダル・ダイアログ
    - `close-window-button.blade.php`, `column-options.blade.php`, `delete-column-modal.blade.php` は `x-teleport="body"` を使用してスタッキングコンテキストの問題を根本解決しています。
- **60**: ファイルインスペクターパネル
- **55**: ファイルインスペクターの背景 (backdrop)
- **50**: バリデーションエラーサマリーのフローティングバッジ
- **50**: 全体通知アラート（sticky/critical/warning 時の `<x-admin.announcement-banner>`）- 重要な運用通知を最前面に表示
- **45**: 秘密区分スタンプ（`confidentiality-stamp`）- navbar dropdown（30）より上、sticky announcement（50）より下。物理位置は `top-16` で navbar の下に配置し、sticky announcement（`top-0`）とは垂直方向で重ならない
- **40**: 全体通知アラート（非sticky / info 時の `<x-admin.announcement-banner>`）
- **35**: バリデーションエラーサマリーのスティッキーヘッダー
- **30**: ナビゲーションおよび一般的なドロップダウン（navbar dropdowns）

## 修正履歴
- 2026-01-17: `close-window-button.blade.php` のモーダルが `z-[35]` のバリデーションパネルの裏に回ることがあったため、`z-[50]` から `z-[100]` に引き上げ。
- 2026-01-17: 整合性の追求のため `column-options.blade.php` も `z-[100]` に修正。
- 2026-01-17 (追加修正): `x-teleport="body"` を導入し、z-index を `z-[9999]` に一括引き上げ。
- 2026-01-17 (追加修正): トースト通知がテレポートされたモーダルの裏に隠れる問題を解決するため、トーストを `x-teleport="body"` 化し、z-index を `10000` に設定。
- 2026-01-26: `delete-column-modal.blade.php` がスティッキーなプレビューパネルの裏に隠れる問題を解決するため、`x-teleport="body"` を導入し z-index を `z-[9999]` に設定。
