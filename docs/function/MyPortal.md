# マイポータル機能

## 概要

マイポータルは、LedgerLeap
にログインしたユーザーが最初に（デフォルト設定の場合）目にするパーソナライズされた画面です。ユーザーが自身の状況（役割、権限、担当範囲など）を素早く把握し、必要な情報や機能へスムーズにアクセスできるよう設計されています。特に、ITリテラシーにばらつきのあるユーザー層を考慮し、専門用語を避け、直感的に理解できる情報を提供することを目的としています。

## 機能の目的とユーザーシナリオ

マイポータルは、以下の目的とユーザーシナリオに対応するために導入されました。

* **状況把握の容易化:**
  ログイン直後に、自分がどの組織に所属し、どのような役割（ロール）を持ち、システム上で主に何ができるのか（権限）を一目で確認できるようにします。これにより、特に初回利用者や役割変更があったユーザーの不安を軽減し、システムへのオンボーディングを支援します。
* **操作の起点提供:** 日常的に利用する可能性の高い「担当フォルダ」へのショートカットを提供し、目的のデータへのアクセスや作業開始までのステップを短縮します。
* **権限の可視化:**
  複雑になりがちな権限設定について、「主なできること」という形で分かりやすく提示します。ユーザーは自分が許可されている主要な操作を把握でき、また必要に応じて詳細な権限（全アクセス可能フォルダなど）も確認可能です。
* **ITリテラシーへの配慮:** 専門的な権限名 (`manage_users` など)
  や内部的なロール名を直接表示するのではなく、翻訳ファイルやロジックによって、より業務に即した平易な言葉（例:
  「ユーザーアカウントを追加・編集できます」、「営業部 一般担当」）で表示するよう努めています。

## 機能詳細

マイポータル画面 (`/my-portal`) は、`App\Livewire\MyPortal` コンポーネントによって制御され、主に以下の情報エリアで構成されます。

### 1. ヘッダー情報

* 画面上部には `MaryUI` の `Header` コンポーネント (`<x-mary-header>`) を使用し、ページタイトルと共にユーザーへの挨拶（例:
  「ようこそ、〇〇さん！」）が表示されます。
* ユーザー名は `Auth::user()->name` から取得されます。
* ヘッダー右側にはプロファイル編集画面へのリンクボタンなどが配置される場合があります。

### 2. 役割と所属エリア

* ユーザーの現在の役割と所属組織に関する情報が表示されます。
* **主な役割/担当:** ユーザーの主所属組織 (`User::primaryOrganization()`) の名前と、そのユーザーに有効なロール (
  `UserService::getAllUniqueRolesForUser()`) の中で代表的なものを組み合わせ、翻訳キー (`ledger.role_label.*`)
  を用いて分かりやすい肩書き（例: 「営業部 一般担当 (主所属)」）として表示します。この文字列は `MyPortal` コンポーネントの
  `prepareRoleDisplayString` メソッドで生成されます。
* **その他の所属:** 主所属以外の所属組織 (`User::organizations()` リレーション) がリスト表示されます。
* **有効な全ロール:** ユーザーに現在有効な全てのロールが、翻訳された表示名でバッジ表示されます（主に確認用）。

### 3. 主なできることエリア

* ユーザーが付与されている主要なアクション権限 (`Permission`) のうち、あらかじめ定義されたリスト (
  `MyPortal::$permissionsToCheck`) に含まれるものが、平易な説明文と共にリスト表示されます。
* 表示されるのはユーザーが**実際に持っている権限のみ**です。
* 表示例: 「✅ アクセス可能な台帳の情報を更新できます。」
* 権限の有無は `MyPortal::prepareMajorPermissions` メソッド内で、`UserService::getAllUniquePermissionsForUser()`
  の結果と比較して判定されます。説明文は翻訳キー (`ledger.permission_description.*`) を使用します。

### 4. あなたの担当フォルダエリア

* ユーザーが日常業務でアクセスする可能性が高い主要なフォルダへのショートカットが表示されます。
* デフォルトでは、「書き込み(Write)または管理(Admin)権限があり、かつ階層が浅い（ルート直下または第1階層）フォルダ」が最大5件表示されます。
* 各フォルダには、権限レベルを示すアイコン（例: ✏️編集可能 / 👑管理可能）とテキスト、およびそのフォルダの台帳一覧画面へのリンクボタンが表示されます。
* 担当フォルダリストは `MyPortal::prepareAssignedFolders` メソッド内で、`WritableFolderRepository` を用いて権限のあるフォルダIDを取得し、
  `Folder` モデルの `withDepth()->having()` などを使ってフィルタリング・取得されます。権限表示には `FolderPermissionType`
  Enum と翻訳キーが利用されます。

### 5. 詳細情報エリア (アコーディオン内)

* より詳細な情報を確認したいユーザー向けに、アコーディオン（`<x-mary-collapse>`）内に以下の情報が表示されます。
* **アクセス可能な全フォルダツリー:** ユーザーが閲覧可能な全てのフォルダが、既存の `<x-folder.tree>` Blade
  コンポーネントを用いて階層表示されます。各フォルダには権限アイコン (👁️閲覧可能 / ✏️編集可能 / 👑管理可能)
  が表示されます。このツリーは表示専用であり、クリックしてもフォルダ移動などのアクションは発生しません (
  `interactive="false"` で制御）。データは `MyPortal::prepareAllFolderTreeData` で準備されます。
* **(将来的な拡張)** 全ロールリスト、通知設定の概要などをこのエリアに追加することも可能です。

### ログイン後の初期画面設定

* ユーザーは、プロファイル編集画面 (`/profile`)
  の「ログイン後の初期画面設定」セクションで、ログイン後に最初に表示される画面を「マイポータル」(`my_portal`)
  または「台帳/フォルダ一覧」(`ledgers`) から選択できます。
* この設定は `users` テーブルの `login_landing_page` カラム (Enum: `App\Enums\LoginLandingPage`) に保存されます。
* 実際のログイン時のリダイレクト処理は `App\Http\Controllers\Auth\AuthenticatedSessionController` の `store`
  メソッド内で、このユーザー設定値に基づいて行われます。`intended()` の挙動よりもユーザー設定が優先されるように調整されています（セッション内の
  `url.intended` を削除、またはリダイレクト先で明示的に指定）。

## ナビゲーションからのアクセス

* ログイン後の初期画面設定に関わらず、ユーザーはいつでもヘッダーナビゲーションの**アプリ名/ロゴ**
  をクリックすることでマイポータル画面 (`/my-portal`) にアクセスできます。
* 他の主要機能（例: 台帳一覧）へもヘッダーナビゲーションからアクセス可能です。

## 関連ファイル

* **Livewire コンポーネント:** `app/Livewire/MyPortal.php` (データ準備・表示ロジック)
* **Blade ビュー:** `resources/views/livewire/my-portal.blade.php` (画面レイアウト)
* **モデル:**
    * `app/Models/User.php` (ユーザー情報、リレーション、`getAllUniqueRoles`, `getAllUniquePermissions`)
    * `app/Models/Organization.php`
    * `app/Models/Role.php`
    * `app/Models/Permission.php`
    * `app/Models/Folder.php` (階層構造, `withDepth`)
* **サービス・リポジトリ:**
    * `app/Services/UserService.php` (権限・ロール取得、設定アクセス判定 `canUserAccessSettings`)
    * `app/Repositories/WritableFolderRepository.php` (アクセス可能フォルダID取得)
* **Enum:**
    * `app/Enums/LoginLandingPage.php` (ログイン後画面設定値)
    * `app/Enums/FolderPermissionType.php` (フォルダ権限タイプ)
* **コントローラー (関連):**
    * `app/Http/Controllers/Auth/AuthenticatedSessionController.php` (ログインリダイレクト処理)
    * `app/Http/Controllers/ProfileController.php` (プロファイル画面表示)
* **フォームリクエスト (関連):** `app/Http/Requests/ProfileUpdateRequest.php` (プロファイル更新バリデーション)
* **Blade コンポーネント (関連):** `resources/views/components/folder/tree.blade.php` (フォルダツリー表示)
* **レイアウト:** `resources/views/layouts/app.blade.php`, `resources/views/layouts/appWithDrawer.blade.php`
* **ナビゲーション:** `resources/views/layouts/daisyuiNavigation.blade.php`
* **言語ファイル:** `lang/**/ledger.php` (各種ラベル、説明文、翻訳キー)
* **ルーティング:** `routes/web.php` (`/my-portal` ルート定義)

## 今後の展望

* 担当フォルダエリアのカスタマイズ性向上（ユーザーがお気に入りフォルダをピン留めするなど）。
* マイポータル内に「最近アクセスした台帳」や「未読通知」などのウィジェットを追加。
* 通知設定の概要表示や、設定画面への直接リンクを追加。
* 管理者がマイポータルに表示する情報をカスタマイズできる機能。
