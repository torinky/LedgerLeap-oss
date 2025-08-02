# 複数行入力カラムのMarkdown対応と自動リンク機能強化 計画

## 1. 目的と背景

現状の複数行入力カラムは、単純なテキスト入力しかサポートしておらず、業務記録や申し送り事項、仕様変更の経緯といった複雑な情報を構造化して記録するには表現力が不足しています。

本計画では、複数行入力カラムにMarkdown記法を導入し、さらに既存の自動リンク機能を適用することで、情報の可読性とアクセシビリティを向上させ、システム全体のナレッジベースとしての価値を高めることを目的とします。

## 2. ユーザーシナリオと機能要件

### 2.1. ユーザーシナリオ

**シナリオ1：現場の保守担当者（田中さん）**

*   **状況**: 日々の点検報告で、異常があった箇所を他の担当者にも分かりやすく伝えたい。また、その日の作業内容を箇条書きで整理し、後から見返したときにすぐに理解できるようにしたい。
*   **Markdown対応による解決策**:
    *   `**異常箇所**` のように記述することで、重要な部分を**太字**で強調できる。
    *   `- 作業項目A` のように記述することで、作業内容を箇条書きリストで整理できる。

**シナリオ2：ソフトウェア開発者・リーダー（佐藤さん）**

*   **状況**: 仕様変更の経緯を記録する際、変更前後のコードスニペットを明確に示したい。また、障害対応の報告書として、ログファイルの一部を引用して貼り付けたい。
*   **Markdown対応による解決策**:
    *   バッククォート3つ(```)でコードを囲むことで、シンタックスハイライト付きの `code` ブロックとして表示できる。
    *   `>` を行頭に付けることで、ログを `quote` (引用) として体裁よく表示できる。

**シナリオ3：システム間連携（田中さん・佐藤さん共通）**

*   **状況**: 田中さんが作業日報に「詳細はRedmineチケット `#12345` を参照」と記録する。佐藤さんが仕様書に「関連チケット: `TICKET-789`」と記載する。
*   **自動リンク機能による解決策**:
    *   Markdownとして表示される際に、既存の自動リンク機能が適用され、`#12345` や `TICKET-789` といった文字列が、外部のチケット管理システムへのハイパーリンクに自動的に変換される。これにより、システムを横断した情報追跡がシームレスになる。

### 2.2. 機能要件

上記のシナリオに基づき、機能要件を以下のように整理します。

1.  **データ形式**:
    *   `textarea`タイプのカラムに入力されたデータは、Markdown形式のテキストとしてデータベースに保存する。

2.  **データ入力UI (`CreateColumn`, `ModifyColumn`)**:
    *   `textarea`タイプのカラムには、Markdownを入力できるテキストエリアを提供する。
    *   **（推奨）** Markdown記法に不慣れなユーザーを補助するため、太字やリスト、コードブロックなどを簡単に入力できるツールバーを設置する。
    *   **（推奨）** 入力中に、レンダリング結果をリアルタイムで確認できるプレビュー機能を提供する。

3.  **データ表示 (`ColumnHtmlService` を利用する箇所)**:
    *   台帳の詳細画面や一覧画面で`textarea`タイプのカラムの値を表示する際、保存されたMarkdownをHTMLに変換して表示する。
    *   このHTML変換処理の**後**に、`AutoLinkService`を適用し、テキスト内の特定パターンを自動的にリンク化する。
    *   `LedgerDefine`の`description`表示で利用されている既存のMarkdownパーサーライブラリ (`Spatie\LaravelMarkdown\MarkdownRenderer`) と `AutoLinkService` を組み合わせ、表示スタイルとリンクの挙動に一貫性を持たせる。

## 3. 実装計画 (ステップ・バイ・ステップ)

### ステップ 1: `ColumnHtmlService` の改修 (詳細設計)

*   **目的:** `textarea` タイプのカラム値を表示する際に、Markdown変換と自動リンク処理を適用する。この実装は、既存の台帳定義説明文の表示ロジックを踏襲し、一貫性を保つ。
*   **対象ファイル:** `app/Services/Ledger/ColumnHtmlService.php`
*   **詳細設計:** 
    1.  **依存性の注入:** 
        *   `ColumnHtmlService` のコンストラクタに、`Spatie\LaravelMarkdown\MarkdownRenderer` を追加でインジェクトする。`AutoLinkService` は既に注入済みであることを確認する。
        ```php
        // app/Services/Ledger/ColumnHtmlService.php (コンストラクタのイメージ)
        public function __construct(
            protected AutoLinkService $autoLinkService,
            protected \Spatie\LaravelMarkdown\MarkdownRenderer $markdownRenderer // これを追加
        ) {
        }
        ```
    2.  **`show()` メソッドのロジック修正:** 
        *   `show()` メソッドの内部で、カラムタイプをチェックする条件分岐を追加する。
        *   `textarea` 型の場合は、まず `markdownRenderer` でHTMLに変換し、次にその結果を `autoLinkService` に渡してリンクを適用する。
        *   その他の型は、既存の `htmlspecialchars` によるエスケープ処理を維持する。
        *   キーワードハイライト処理は、全ての処理の最後に適用する。
        ```php
        // app/Services/Ledger/ColumnHtmlService.php の show() メソッド内のロジックイメージ
        
        // ...（既存の初期化処理）... 

        if (is_null($this->initialValue)) {
            return null;
        }

        $html = '';
        // textarea型の場合の特別処理
        if ($this->columnDefineData->type === 'textarea') {
            // 1. MarkdownをHTMLに変換
            $html = $this->markdownRenderer->toHtml((string) $this->initialValue);
            
            // 2. 自動リンクを適用
            $html = $this->autoLinkService->convert($html, $this->columnDefineData, $record);

        } else if ($this->columnDefineData->type === 'files') {
            // ... (既存のファイル処理) ...
            $html = $this->getFileHtml();

        } else {
            // その他の型は、これまで通りHTMLエスケープ
            $html = htmlspecialchars((string) $this->initialValue, ENT_QUOTES, 'UTF-8');
        }

        // 最後にキーワードハイライトを適用
        return $this->highlightKeywords($html);
        ```
*   **成果物:** 台帳の詳細画面や一覧画面で、複数行カラムの内容がMarkdownとしてレンダリングされ、かつ自動リンクが適用された状態で表示される。

### ステップ 2: (任意) 入力支援UIの導入

*   **目的:** Markdownに不慣れなユーザーでも簡単に入力できるよう、リッチテキストエディタを導入する。
*   **対象ファイル:** 
    *   `resources/views/livewire/ledger/create-column.blade.php`
    *   `resources/views/livewire/ledger/modify-column.blade.php`
*   **タスク:** 
    1.  `textarea` タイプのカラムを表示する箇所で、標準の `<textarea>` の代わりに、Markdown対応のリッチテキストエディタコンポーネント（例: [EasyMDE](https://github.com/Ionaru/easy-markdown-editor), またはMaryUIに組み込みのエディタがあればそれを利用）を導入する。
    2.  エディタのコンテンツを、対応するLivewireコンポーネントのプロパティ (`$content`) に `wire:model` でバインドする。
*   **成果物:** ユーザーがMarkdownを直感的に編集できるUI。

## 4. 既存機能との関連性と影響

*   **`AutoLinkService`**: 既存の自動リンク機能をそのまま活用します。`ColumnHtmlService`での処理順序（Markdown変換 → 自動リンク適用）を正しく実装することが重要です。HTML構造を破壊しないよう、`AutoLinkService`がHTML内のテキストノードのみを対象に処理することを再確認します。
*   **`ColumnHtmlService`**: このサービスは複数の箇所（詳細画面、一覧画面、差分表示画面など）で利用されているため、今回の変更が他のカラムタイプの表示に影響を与えないよう、`textarea`タイプに限定した条件分岐を確実に行います。
*   **データ構造**: データベースのスキーマ変更は不要です。`ledgers`テーブルの`content`カラムにMarkdownテキストをそのまま保存します。

## 5. ドキュメント更新計画

*   **機能仕様書 (`/docs/function/Ledger.md`)**: 台帳管理機能の説明に、複数行カラムがMarkdown入力に対応している旨と、利用可能な主な記法（太字、リスト、コードブロックなど）の例を追記します。
*   **利用者向けマニュアル (将来作成予定)**: エンドユーザー向けのマニュアルに、Markdownの入力方法に関するセクションを追加します。