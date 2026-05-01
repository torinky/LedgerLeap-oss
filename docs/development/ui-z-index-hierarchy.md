# LedgerLeap UI Z-Index 階層管理

UIコンポーネント間の重なり順（z-index）を管理し、モーダルがパネルの裏に隠れる現象を防止します。

## 主要な Z-Index 値

| 階層 | 要素 | ファイル/用途 | 備考 |
|------|------|--------------|------|
| **10000** | トースト通知 (`.toast`) | `app.blade.php` | 最前面。`x-teleport="body"` で body 直下に配置 |
| **9999** | DaisyUI 重要モーダル・ダイアログ | `close-window-button.blade.php`, `column-options.blade.php`, `delete-column-modal.blade.php` | `x-teleport="body"` 使用。スタッキングコンテキスト問題を回避 |
| **~80** | MaryUI モーダル (box) | `page-qr-code.blade.php` (`box-class="max-w-4xl z-80"`) | 個別に高い z-index を指定したケース |
| **~70** | MaryUI モーダル (backdrop) | `page-qr-code.blade.php` (`class="backdrop-blur z-70"`) | backdrop 用 |
| **60** | ファイルインスペクターパネル | | |
| **55** | ファイルインスペクターの背景 (backdrop) | | |
| **50** | バリデーションエラーサマリーのフローティングバッジ | | |
| **50** | 全体通知アラート（sticky/critical/warning） | `<x-admin.announcement-banner>` (`sticky top-0 z-50`) | 重要な運用通知を最前面に。物理位置 `top-0` |
| **45** | 秘密区分スタンプ（`confidentiality-stamp`） | `components/ledger/confidentiality-stamp.blade.php` | navbar dropdown（30）より上、sticky announcement（50）より下。物理位置 `top-16` で navbar の下に配置し、sticky announcement とは垂直方向で重ならない |
| **40** | 全体通知アラート（非sticky / info） | `<x-admin.announcement-banner>` (`relative z-40`) | 通常フロー内の通知 |
| **40** | フォルダツリードロワー（drawer-side） | `appWithDrawer.blade.php` (`drawer-side z-40`) | サイドバー。xl 以上で常時表示、xl 未満でオーバーレイ |
| **35** | バリデーションエラーサマリーのスティッキーヘッダー | | |
| **30** | ナビゲーションドロップダウン | `daisyuiNavigation.blade.php` (`dropdown-content z-[30]`) | navbar 内のメニュー |
| **30** | ツールチップ（DaisyUI デフォルト） | | `before:z-[30]` 相当 |

### モーダル系の補足

- **MaryUI `<x-mary-modal>`**: DaisyUI の `modal` クラスをラップ。デフォルトでは `z-50` 以上（DaisyUI 標準）。プロジェクト内では重要なダイアログで `z-[9999]` を明示的に指定するケースあり。
- **DaisyUI `.modal`**: デフォルトで `position: fixed` + `z-index` 持ち。 backdrop は同じスタッキングコンテキスト内で低い値、modal-box が高い値。
- **権限ダイアログ / 活動履歴ダイアログ**: `records-table.blade.php` 内で `<x-mary-modal>` 使用。backdrop-blur 付き。z-index は MaryUI/DaisyUI デフォルトに依存。

## レイヤー分けの原則

```
最前面:  トースト (10000) > 重要モーダル (9999) > 通常モーダル (~50-80)
         > sticky announcement (50) > スタンプ (45) > drawer-side (40)
         > navbar dropdown (30)
```

- **スタンプ (45)** は drawer-side (40) より上なので、ドロワー展開時もスタンプは前面に見える。
- **スタンプ (45)** は sticky announcement (50) より下だが、物理位置 `top-16` vs `top-0` で垂直方向に分離しているため視覚的に競合しない。
- **モーダル (50+ / 9999)** は全てスタンプより上に来るため、モーダル表示時はスタンプが自然に背後に回る。

## 修正履歴
- 2026-01-17: `close-window-button.blade.php` のモーダルが `z-[35]` のバリデーションパネルの裏に回ることがあったため、`z-[50]` から `z-[100]` に引き上げ。
- 2026-01-17: 整合性の追求のため `column-options.blade.php` も `z-[100]` に修正。
- 2026-01-17 (追加修正): `x-teleport="body"` を導入し、z-index を `z-[9999]` に一括引き上げ。
- 2026-01-17 (追加修正): トースト通知がテレポートされたモーダルの裏に隠れる問題を解決するため、トーストを `x-teleport="body"` 化し、z-index を `10000` に設定。
- 2026-01-26: `delete-column-modal.blade.php` がスティッキーなプレビューパネルの裏に隠れる問題を解決するため、`x-teleport="body"` を導入し z-index を `z-[9999]` に設定。
- 2026-05-01: 秘密区分スタンプ (`z-[45]`) と全体通知アラート (`z-40`/`z-50`) を追加。フォルダツリードロワー (`z-40`)、page-qr-code モーダル (`z-70`/`z-80`)、MaryUI/DaisyUI モーダルの補足を追記。
