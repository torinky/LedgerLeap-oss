# Ledger Detail UI Modernization Walkthrough

台帳詳細画面（Ledger Show）の UI/UX モダナイゼーションと、それに伴うデザインスキル・ガイドラインの更新を完了しました。

## 実施した主な作業

### 1. 「すべて展開/折りたたむ」機能の復元と高度化
- `ledgerState` Alpine ストアに `__global__` フラグを導入し、個別の開閉状態を一括制御するロジックを実装。
- 台帳編集画面と統一感のある、カプセル型のコンパクトなトグル UI をヘッダーに配置。
- `LedgerDiffViewer` の `checkStorage` ロジックを修正し、ページリロードなしで即座に状態が反映されるリアクティビティを確保。

### 2. 詳細画面ヘッダーの統合と最適化
- ヘッダー全体を `<x-mary-card>` に統合し、背景色を `bg-primary/30` に設定することで、ブランドの視認性とページ内での重厚感を強化。
- メタ情報（バージョン、最終更新者、日時）をパンくずリストと同じ行に整理し、情報の階層を整理。
- 長文のガイドラインや説明文を `x-collapse` で折りたたみ、初期表示時の情報密度を最適化。

### 3. 必須項目インジケーターの刷新
- グループヘッダーの「必須」というテキストバッジを廃止。
- フォルダアイコンの右肩に赤いドットを表示する **Indicator Pattern** を採用し、ツールチップで詳細（「必須項目を含む」）を表示するモダンな表現へ変更。

### 4. デザインスキル・ガイドラインへの定着
- [design.instructions.md](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/.github/instructions/design.instructions.md) を更新。
    - `Unified Detail Header` (bg-primary/30 統合ヘッダー) の定義
    - `Required Indicator Pattern` の標準化
    - `Component Attribute Optimization` (余計な span を排除し属性に集約) の追加
- [ledger-detail-header/SKILL.md](file:///Users/kazutaka/PhpstormProjects/LedgerLeap/.github/skills/ledger-detail-header/SKILL.md) を新規作成。
    - 今回実装したモダンなヘッダー構造とグローバルな開閉状態管理の実装ベストプラクティスをドキュメント化。

## 検証結果
- [x] 「すべて展開/折りたたむ」トグルの操作が、下位のすべてのグループに即時（リロードなしで）反映されることを確認。
- [x] ヘッダーカード内での説明文の開閉操作が正しく動作することを確認。
- [x] 必須項目が含まれる場合、アイコン上の赤いドットとツールチップが表示されることを確認。
- [x] 既存の古いアコーディオン実装と機能が重複していないことを確認。

> [!IMPORTANT]
> 今後の詳細画面の実装では、`bg-primary/30` を用いた統合ヘッダーカードと、インジケータードットによる必須項目表示が標準となります。詳細は更新されたデザインガイドラインを参照してください。
