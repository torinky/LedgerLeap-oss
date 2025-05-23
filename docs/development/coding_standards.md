# コーディング規約

## はじめに
このドキュメントは、LedgerLeapプロジェクトにおけるコードの品質、一貫性、保守性を維持向上させることを目的としたコーディング規約を定めます。本規約に従うことで、開発者間の認識の齟齬を減らし、スムーズな開発プロセスを実現します。

## 全般

*   **フォーマット**:
    *   コードフォーマットには `laravel/pint` を利用します。コミット前に `./vendor/bin/sail pint` を実行し、コードスタイルを整形してください。
*   **PHPバージョン**: PHP ^8.4
*   **Laravelバージョン**: Laravel ^12.0

## 命名規則

一貫性のある命名は、コードの可読性を大幅に向上させます。

*   **変数名**: スネークケース (例: `$ledger_item`, `$user_list`)
*   **メソッド名**: キャメルケース (例: `getUserProfile()`, `calculateTotalAmount()`)
*   **クラス名**: パスカルケース (アッパーキャメルケース) (例: `LedgerController`, `UserService`)
*   **データベーステーブル名**: スネークケース複数形 (例: `ledger_items`, `user_profiles`)
*   **カラム名**: スネークケース (例: `item_name`, `created_at`)
*   **ルート名**: ケバブケースまたはスネークケースをプロジェクト内で統一 (例: `ledger-items.show` または `ledger_items.show`)。LedgerLeapでは **ケバブケース** (`ledger-items.show`) を推奨します。
*   **設定キー**: スネークケース (例: `database.connections.mysql.host`)
*   **環境変数キー**: アッパースネークケース (例: `DB_CONNECTION`, `APP_DEBUG`)
*   **Bladeファイル名**: ケバブケース (例: `list-items.blade.php`, `user-profile.blade.php`)
*   **Livewireコンポーネントクラス名**: パスカルケース (例: `UserProfile`, `CreateLedgerEntry`)
*   **Livewireコンポーネントビュー名**: ケバブケース (例: `user-profile.blade.php`, `create-ledger-entry.blade.php`)

## コメント

コードの意図を明確にし、将来の自分や他の開発者が理解しやすくするためにコメントを記述します。

*   **PHPDoc**: クラス、メソッド、プロパティにはPHPDoc形式のコメントを記述することを強く推奨します。特に、複雑なロジックや公開APIのメソッドには必須とします。
    ```php
    /**
     * 指定されたユーザーIDのプロファイルを取得する
     *
     * @param  int  $userId ユーザーID
     * @return \App\Models\UserProfile|null ユーザープロファイル、見つからない場合はnull
     * @throws \App\Exceptions\InvalidUserIdException 無効なユーザーIDの場合
     */
    public function getUserProfile(int $userId): ?UserProfile
    {
        // ...
    }
    ```
*   **インラインコメント**: 複雑なアルゴリズムや、一見して理解しにくい処理のステップには、適宜インラインコメントを追加します。
*   **TODO / FIXME**:
    *   `// TODO:`: 後で対応が必要なタスクや改善点を示すために使用します。具体的な作業内容や担当者、期限などを記述できるとより良いです。
    *   `// FIXME:`: 既知のバグや問題があり、修正が必要な箇所を示します。問題の詳細や参照すべきIssue番号を記述してください。

## コントローラ (Controllers)

コントローラはHTTPリクエストの受付とレスポンスの返却に責務を持ちます。

*   **単一責任の原則**: 一つのコントローラメソッドは、一つの関心事に集中します。CRUD操作ごとにメソッドを分割することを基本とします (例: `index`, `create`, `store`, `show`, `edit`, `update`, `destroy`)。
*   **ファットコントローラの回避**: コントローラ内にビジネスロジックを直接記述せず、サービスクラスやアクションクラスに処理を委譲します。
    ```php
    // 悪い例
    public function store(Request $request)
    {
        $validated = $request->validate([...]);
        // 大量のビジネスロジック...
        $result = $validated['price'] * $validated['quantity'] * 1.10;
        // ...
        return response()->json(['data' => $result]);
    }

    // 良い例
    public function store(StoreLedgerRequest $request) // FormRequestでバリデーション
    {
        try {
            $ledger = $this->ledgerService->createLedger($request->validated());
            return new LedgerResource($ledger);
        } catch (\Exception $e) {
            // エラーハンドリング
            return response()->json(['message' => '作成に失敗しました。'], 500);
        }
    }
    ```
*   **FormRequestの利用**: バリデーションロジックはコントローラから分離し、FormRequestクラスに記述します。
*   **リソースコントローラ/APIリソース**: Eloquentモデルに対する標準的なCRUD操作には、リソースコントローラとAPIリソースを積極的に活用します。

## モデル (Models)

モデルはデータベーステーブルとの対話と、データに関連するビジネスロジックの一部を担当します。

*   **リレーション**: Eloquentリレーション (`hasOne`, `hasMany`, `belongsTo`, `belongsToMany`など) を明確に定義します。メソッド名はリレーション先のモデル名（単数形または複数形）に合わせます。
*   **スコープ**: クエリの再利用性を高めるために、ローカルクエリスコープ (`scopeActive($query)`) やグローバルスコープを適切に利用します。
*   **アクセサ/ミューテタ**: データの取得時・設定時に特定の処理を挟む場合は、アクセサ (`getXAttribute()`) やミューテタ (`setXAttribute($value)`) を利用します。ただし、過度なロジックは避けます。
*   **`$fillable` / `$guarded`**: マスアサインメントの脆弱性を防ぐため、`$fillable` (許可リスト) または `$guarded` (禁止リスト、通常は空配列を推奨) を明示的に設定します。LedgerLeapでは `$fillable` の使用を推奨します。
*   **`$casts`**: 属性のデータ型をキャストするために `$casts` プロパティを明示します (例: `boolean`, `datetime`, `array`, Enumなど)。
*   **Enumの活用**: ステータス管理など、特定の値セットを取る属性にはPHP 8.1以降のEnumを積極的に利用し、`$casts` でEnumクラスを指定します。
*   **ビジネスロジック**: モデル固有の単純なロジック（例: フルネームの取得、ステータスの判定）はモデル内にメソッドとして定義しても良いですが、複雑なものはサービスクラスに委譲します。

## ビュー (Blade)

ビューはユーザーインターフェースの表示を担当します。

*   **可読性**: インデントを適切に行い、コードの可読性を高めます。複雑な条件分岐やループは、ヘルパー関数やビューコンポーザ、Bladeコンポーネントに切り出すことを検討します。
*   **Bladeコンポーネント/Livewireコンポーネント**: 再利用可能なUI部品は、BladeコンポーネントまたはLivewireコンポーネントとして積極的に作成・利用します。
*   **PHPロジックの分離**: ビューファイル (`*.blade.php`) 内での過度なPHPロジック記述は避けます。表示に必要なデータはコントローラやビューコンポーザで整形・準備し、ビューには表示ロジックのみを記述するように努めます。
    ```blade
    {{-- 悪い例 --}}
    @php
        $complexData = [];
        foreach($items as $item) {
            if ($item->isActive() && $item->user_id === auth()->id()) {
                $complexData[] = $item->name . ' (' . $item->category->name . ')';
            }
        }
    @endphp
    <ul>
        @foreach($complexData as $data)
            <li>{{ $data }}</li>
        @endforeach
    </ul>

    {{-- 良い例 (コントローラやサービスで$viewModelを準備) --}}
    <ul>
        @foreach($viewModel->formattedItems as $item)
            <li>{{ $item->displayName }}</li>
        @endforeach
    </ul>
    ```
*   **エスケープ**: XSS脆弱性を防ぐため、ユーザー入力を表示する際は原則としてBladeの二重波括弧 `{{ $variable }}` を使用します。意図的にエスケープしない場合は ` {!! $variable !!} ` の使用箇所とその理由を明確にします。

## Livewireコンポーネント

LivewireはインタラクティブなUIをPHPで構築するためのフレームワークです。

*   **コンポーネントの粒度**: 再利用可能で、かつ単一の関心事に集中するような適切な粒度でコンポーネントを作成します。大きすぎるコンポーネントは状態管理やパフォーマンスの問題を引き起こす可能性があります。
*   **親子コンポーネント間のデータ受け渡し**:
    *   親から子へ: プロパティとして渡します (`<livewire:child-component :prop="$data" />`)。
    *   子から親へ: イベントを発行 (`$this->dispatch('eventName', $data)`) し、親コンポーネントでリスナーを定義します。
*   **状態管理**: コンポーネントのパブリックプロパティは状態を表します。不要なプロパティを公開しないように注意します。
*   **アクション**: ユーザー操作に対応するメソッドは、簡潔に保ち、必要に応じてサービスクラスのメソッドを呼び出します。
*   **バリデーション**: フォーム入力のバリデーションは、Livewireのリアルタイムバリデーション機能や`validate()`メソッドを利用します。

## テスト (Tests)

品質の高いソフトウェア開発のためにはテストが不可欠です。

*   **ユニットテスト (Unit Tests)**: クラスやメソッド単体のロジックを検証します。依存関係はモック化することが多いです。
*   **フィーチャーテスト (Feature Tests)**: アプリケーションの機能（HTTPリクエストからレスポンスまでの一連の流れなど）をテストします。データベースや他のコンポーネントとの結合を含めてテストします。
*   **テストカバレッジ**: 現状、明確な目標値は設定していませんが、主要な機能や複雑なロジックについては高いカバレッジを目指します。将来的には目標値を設定する可能性があります。
*   **テストの命名**: テストメソッド名は、テスト対象のメソッド名や振る舞いが明確にわかるように記述します (例: `test_user_can_register_with_valid_data()`)。
*   **データベースの利用**: フィーチャーテストでは、テスト用のデータベース (`RefreshDatabase` トレイトなど) を利用し、テスト間の独立性を保ちます。

## その他

*   **設定ファイル (`config/*.php`)**:
    *   設定値は `env()` ヘルパーを直接使用せず、`config()` ヘルパー経由でアクセスします。
    *   アプリケーション固有の設定は、専用の設定ファイルを作成します (例: `config/ledgerleap.php`)。
*   **環境変数 (`.env`)**:
    *   機密情報（APIキー、パスワードなど）や環境ごとに異なる設定値は `.env` ファイルに記述します。
    *   `.env.example` ファイルには、アプリケーション実行に必要な全ての環境変数のキーと、デフォルト値またはダミー値を記述し、常に最新の状態に保ちます。
    *   `.env` ファイルはバージョン管理に含めません。
*   **Enumの活用**:
    *   PHP 8.1+ の Enum は、固定された値のセットを表す場合に積極的に使用します（例: ステータス、種別、役割など）。
    *   Enum に関連するロジックは Enum 自身にメソッドとして実装することを検討します（例: 表示用のラベルを返すメソッド）。
    *   Eloquentモデルの属性として使用する場合は `$casts` プロパティでEnumクラスを指定します。
*   **サービスクラス**: 再利用可能なビジネスロジックはサービスクラスに集約します。サービスクラスは依存性注入コンテナを通じて利用します。
*   **リポジトリパターン**: (任意) データベース操作が複雑になる場合は、リポジトリパターンを導入してデータアクセスロジックを分離することを検討します。LedgerLeapでは現状、Eloquentモデルがその役割を担うことが多いです。
*   **DRY原則 (Don't Repeat Yourself)**: 同じコードの繰り返しを避け、共通化できる処理は関数やクラス、トレイトなどにまとめます。
*   **KISS原則 (Keep It Simple, Stupid)**: 不必要に複雑な設計やコードを避け、シンプルで理解しやすい実装を心がけます。
*   **YAGNI原則 (You Ain't Gonna Need It)**: 現時点で必要とされていない機能や過度な一般化は実装しません。

このコーディング規約は、プロジェクトの状況や技術の進化に応じて見直されることがあります。提案や改善点があれば、積極的に議論してください。
