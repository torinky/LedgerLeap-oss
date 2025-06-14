# アクセスとアクティビティ機能

## 概要

LedgerLeap の「アクセスとアクティビティ」機能は、組織内の情報に対する透明性と監査可能性を高めるために設計されています。この機能により、ユーザーは特定の情報（フォルダ、台帳定義、台帳レコード）に対して**誰がどのようなアクセス権限を持っているか**、そして**いつ、誰がどのような操作を行ったか**を、直感的かつ詳細に確認することができます。これにより、情報セキュリティの確保、コンプライアンスの遵守、問題発生時の迅速な原因究明、そして日常的な情報共有範囲の理解を支援します。

## ターゲットユーザーと利用シナリオ

この機能は、特に以下のようなユーザーと状況での利用を想定しています。

*   **管理者 / 部門長 (佐藤 健太)**
    *   **シナリオ1: 監査対応**:
        *   特定の台帳レコード、台帳定義、またはフォルダについて、「誰が、いつ、どのような操作をしたのか？」「現在、誰がこの情報にアクセスできる権限を持っているのか？」といった問い合わせに対し、迅速かつ正確な情報提供が求められる。
        *   本機能により、対象リソースの「総合アクティビティ履歴」や「アクセスと権限」を瞬時に確認し、証跡を提供できます。
    *   **シナリオ2: 権限設定のレビュー・調整**:
        *   新入社員の配属や異動があった際、特定のフォルダや台帳に対するアクセス権限が適切に設定されているかを確認・調整したい。
        *   「アクセスと権限」ビューを通じて、各ロールやユーザーが持つ権限の全体像を把握し、設定の妥当性を評価できます。
    *   **シナリオ3: 情報漏洩の疑義発生時**:
        *   特定の情報が不正に閲覧・変更された疑いがある場合、その情報の過去のアクセス履歴を遡って調査し、原因を特定したい。
        *   「総合アクティビティ履歴」で、不審な操作のタイムラインと詳細を追跡し、問題の範囲と原因を絞り込むことができます。

*   **実務担当者 / 現場リーダー (田中 美咲)**
    *   **シナリオ1: 情報共有範囲の確認**:
        *   自分が登録した報告書や手順書が、チームの他のメンバーや上長に適切に共有されているか、閲覧権限が設定されているかを知りたい。
        *   「アクセスと権限」ビューで、自分がアクセスしている情報に対して、どの範囲のユーザーやロールがアクセス可能かをざっくりと理解できます。
    *   **シナリオ2: 過去の変更内容の確認**:
        *   特定の台帳レコードが、いつ、誰によってどのように変更されたかを確認し、最新の情報が正しいかを検証したい。
        *   「総合アクティビティ履歴」で、台帳レコードに対する変更履歴（差分含む）を時系列で確認し、情報の整合性を検証できます。
    *   **シナリオ3: 自身の操作履歴の確認**:
        *   自分が特定の情報を更新したはずなのに、反映されていないように見える場合、システムが自分の操作を正しく認識しているかを確認したい。
        *   「総合アクティビティ履歴」を自分の操作に絞り込むことで、自身の行った操作が正確に記録されているかを確認できます。

## 機能詳細

「アクセスとアクティビティ」機能は、主にユーザーが日常的に利用する「台帳レコード詳細画面 (`/ledger/{id}`)」および「台帳一覧／検索画面 (`/ledger`)」に統合して提供されます。

### 1. 総合アクティビティ履歴 (`App\Livewire\Common\ActivityHistoryDisplay`)

このコンポーネントは、指定されたリソース（台帳レコード、台帳定義、フォルダ）に関連する全てのシステム活動を時系列で表示します。

*   **表示内容**:
    *   **日時**: イベント発生日時。
    *   **操作者**: 操作を行ったユーザー名。ユーザーが削除された場合は「システム」などと表示。
    *   **操作内容**: イベントの種類（例: 「台帳レコードを作成しました」「フォルダを更新しました」「ワークフローを承認しました」）を分かりやすく表示。
    *   **対象リソース**: アクティビティの対象となったリソースのタイプ（例: 台帳レコード、台帳定義、フォルダ）と、その名称やID。
    *   **変更詳細**: 更新操作の場合、変更前後のデータ差分を分かりやすく表示（例: カラム名と値の変更）。
    *   **コメント**: ワークフローアクションなどで付与されたコメント。
*   **データ操作機能 (MVP)**:
    *   **ソート**: 日時（昇順/降順、デフォルトは最新が上）。
    *   **ページネーション**: 長い履歴も効率的に表示。
*   **UI/UX (MVP)**:
    *   変更差分（特に重要なフィールド）の視覚的なハイライト。
    *   操作者名や対象リソース名（台帳レコード、台帳定義、フォルダなど）から、それぞれの詳細画面へのリンク。

### 2. アクセスと権限 (`App\Livewire\Common\PermissionDisplay`)

このコンポーネントは、指定されたリソース（台帳レコード、台帳定義、フォルダ）に対するアクセス権限の概要と詳細を表示します。

*   **表示内容**:
    *   **適用されている権限の概要**: ログインユーザーが対象リソースに対して現在持つ権限（例: 「閲覧可能」「書き込み可能」）を簡潔に表示。
    *   **階層的な権限元**: このリソースに適用される権限が、どの親フォルダや台帳定義、ロールから継承されているかを視覚的に分かりやすく表示。
    *   **ロールごとの権限一覧**: 各ロールがこのリソースに対して持つ権限タイプ（閲覧 `READ`、書き込み `WRITE`、点検 `INSPECT`、承認 `APPROVE`、管理 `ADMIN`、削除 `DELETE`）をアイコンやバッジで表示。
    *   **アクセス可能なユーザー/ロール一覧**: フォルダと台帳定義の権限設定を総合して、このリソースにアクセス可能な個別のユーザー名と、彼らが持つロールのリスト。
*   **データ操作機能 (MVP)**:
    *   **ページネーション**: アクセス可能なユーザー/ロールのリストが長くなる場合に備える。
*   **UI/UX (MVP)**:
    *   権限の包含関係（例: `ADMIN` は `APPROVE` を含む）をアイコンや色、ツールチップなどで分かりやすく表示。
    *   ロール名やユーザー名から、それぞれの管理画面（Filament）へのリンク（権限がある場合のみ表示）。

### 3. フォルダ概要パネル (`App\Livewire\Common\FolderSummary`)

台帳一覧／検索画面 (`RecordsTable`) に統合され、現在のフォルダ全体のアクセス権限と活動の概要を提供します。

*   **表示内容**:
    *   現在のフォルダ名。
    *   そのフォルダのアクセス権限を持つ主要なロールの概要表示（例: 「このフォルダは営業部全体で閲覧可能です。」）。
    *   ログインユーザーがそのフォルダに対して持つ権限の概要（例: 「あなたは編集権限を持っています。」）。
*   **機能**:
    *   **「フォルダのアクセス権限詳細を見る」ボタン**: クリックで `App\Livewire\Common\PermissionDisplay` コンポーネントを、対象フォルダIDを指定してモーダル（または別ページ）として開く。
    *   **「フォルダの活動履歴を見る」ボタン**: クリックで `App\Livewire\Common\ActivityHistoryDisplay` コンポーネントを、対象フォルダIDを指定してモーダル（または別ページ）として開く。

### 4. 台帳定義ごとの概要情報 (RecordsTableに直接追加またはサブコンポーネント)

台帳一覧／検索画面 (`RecordsTable`) の各台帳定義の行に、その台帳定義のアクセス権限と直近の活動の概要を表示します。

*   **表示内容**:
    *   台帳定義名の下に、その台帳定義のアクセス権限の概要（例: アイコンや短いテキスト）。
    *   直近の操作（例: 「最終更新: 〇日前 (〇〇さん)」）や、未処理ワークフロー件数などの概要情報（アイコンなど）。
*   **機能**:
    *   **「台帳定義のアクセス権限詳細を見る」ボタン**: クリックで `App\Livewire\Common\PermissionDisplay` コンポーネントを、対象台帳定義IDを指定してモーダル（または別ページ）として開く。
    *   **「台帳定義の活動履歴を見る」ボタン**: クリックで `App\Livewire\Common\ActivityHistoryDisplay` コンポーネントを、対象台帳定義IDを指定してモーダル（または別ページ）として開く。

## 関連ファイル（想定）

*   **Livewire コンポーネント**:
    *   `app/Livewire/Common/ActivityHistoryDisplay.php` (新規)
    *   `app/Livewire/Common/PermissionDisplay.php` (新規)
    *   `app/Livewire/Common/FolderSummary.php` (新規)
    *   `app/Livewire/Ledger/Show.php` (既存 - 上記共通コンポーネントを埋め込み)
    *   `app/Livewire/Ledger/RecordsTable.php` (既存 - 上記共通コンポーネントを埋め込み、概要情報追加)
*   **Blade ビュー**:
    *   `resources/views/livewire/common/activity-history-display.blade.php` (新規)
    *   `resources/views/livewire/common/permission-display.blade.php` (新規)
    *   `resources/views/livewire/common/folder-summary.blade.php` (新規)
    *   `resources/views/livewire/ledger/show.blade.php` (修正)
    *   `resources/views/livewire/ledger/records-table.blade.php` (修正)
*   **モデル**:
    *   `App\Models\CustomActivity.php` (既存 - データソース)
    *   `App\Models\Folder.php` (既存 - 権限情報、活動履歴対象)
    *   `App\Models\LedgerDefine.php` (既存 - 権限情報、活動履歴対象)
    *   `App\Models\Ledger.php` (既存 - 活動履歴対象)
    *   `App\Models\Role.php` (既存 - 権限情報)
    *   `App\Models\Permission.php` (既存 - 権限情報)
    *   `App\Models\RoleFolderPermission.php` (既存 - 権限情報)
*   **サービス**:
    *   `App\Services\UserService.php` (既存 - ユーザーの権限判定ヘルパー)
    *   `App\Services\ActivityLogService.php` (新規 - ActivityLog の取得・フィルタリングロジックをカプセル化)
    *   `App\Services\PermissionService.php` (新規 - 権限関連ロジックをカプセル化)
*   **翻訳ファイル**:
    *   `lang/ja/ledger.php` (新規機能のラベル、説明、メッセージ)
    *   `lang/ja/activitylog.php` (ActivityLog の説明文)
    *   `lang/ja/permission.php` (権限タイプの説明)



---

### **実装ステップの分解**


#### **ステップ 1: `App\Livewire\Common\ActivityHistoryDisplay` の基本実装**

*   **目的**: どのリソースタイプ（Ledger, LedgerDefine, Folder）にも対応できる共通のアクティビティ履歴表示コンポーネントの作成。
*   **作業内容**:
    1.  `app/Livewire/Common/ActivityHistoryDisplay.php` を作成。
    2.  コンポーネント内で `$resourceId`, `$resourceType` プロパティを受け取るように定義。
    3.  `mount()` メソッドで、`$resourceType` と `$resourceId` に基づいて `CustomActivity::query()` を構築し、関連するアクティビティログを取得するロジックを実装。
        *   `Spatie\Activitylog\Models\Activity` モデル（`CustomActivity` が継承）の `subject_id`, `subject_type` を利用してフィルタリング。
        *   `with('causer', 'subject')` で関連するユーザーと対象モデルをイーガーロード。
    4.  ビュー `resources/views/livewire/common/activity-history-display.blade.php` を作成。
    5.  取得したアクティビティログを、日時、操作者、操作内容、対象リソースタイプ/名称、コメントの形式でテーブルまたはリスト表示。
    6.  日時によるソート（昇順/降順）とページネーションを実装。
    7.  仮のデータやテストコードで、単一の `Ledger`, `LedgerDefine`, `Folder` に対するアクティビティログが正しく表示されることを確認。

#### **ステップ 2: `App\Livewire\Common\PermissionDisplay` の基本実装**

*   **目的**: どのリソースタイプ（Ledger, LedgerDefine, Folder）にも対応できる共通の権限情報表示コンポーネントの作成。
*   **作業内容**:
    1.  `app/Livewire/Common/PermissionDisplay.php` を作成。
    2.  コンポーネント内で `$resourceId`, `$resourceType` プロパティを受け取るように定義。
    3.  `mount()` メソッドで、`$resourceType` と `$resourceId` に基づいて、そのリソースに最終的に適用される権限情報を取得するロジックを実装。
        *   `Ledger` の場合は、親の `LedgerDefine` を経由して `Folder` の権限を取得。
        *   `Folder` の場合は、`RoleFolderPermission` を直接参照。
        *   `LedgerDefine` の場合は、`HasModelRoles` で直接紐づいたロールと、親 `Folder` の権限を取得。
        *   `UserService` や `Folder` モデルの既存の権限関連メソッド（`getAllPermissionsWithInheritance` など）を最大限活用。
    4.  ログインユーザーが持つ権限の概要を判定し、表示するロジックを実装。
    5.  ビュー `resources/views/livewire/common/permission-display.blade.php` を作成。
    6.  取得した権限情報を、階層的な権限元、ロールごとの権限タイプ（アイコン/バッジ付き）の形式で表示。
    7.  アクセス可能なユーザー/ロール一覧を表示し、ページネーションを実装。

#### **ステップ 3: `Ledger/Show.php` (レコード詳細画面) への共通コンポーネント統合**

*   **目的**: 台帳レコード詳細画面に、作成した共通コンポーネントをタブとして組み込む。
*   **作業内容**:
    1.  `app/Livewire/Ledger/Show.php` を修正。
    2.  `show.blade.php` に新たなタブを追加。例えば「総合活動履歴」「アクセスと権限」。
    3.  各タブ内に、対応する共通コンポーネント (`@livewire('common.activity-history-display', ['resourceId' => $ledger->id, 'resourceType' => 'Ledger'])` など) を埋め込む。
    4.  既存のワークフロー履歴タブと新しいタブが機能することを確認。

#### **ステップ 4: `ActivityHistoryDisplay` の「総合アクティビティ」対応強化**

*   **目的**: レコード、台帳定義、フォルダの活動ログを単一のタブで表示できるようにする。
*   **作業内容**:
    1.  `app/Livewire/Common\ActivityHistoryDisplay.php` を修正。
    2.  `mount()` メソッドで、`$resourceId`, `$resourceType` に加え、`$includeRelatedResources` (boolean) などのオプションを受け取るように変更。
    3.  `$includeRelatedResources` が `true` の場合、対象レコードの `ledger_define_id` と `folder_id` を取得し、それらに関連するアクティビティも一緒に取得するロジックを追加。
        *   `CustomActivity::where(function ($query) use ($ledgerId, $ledgerDefineId, $folderId) { ... })` のようにOR条件でクエリを構築。
    4.  各アクティビティの表示に、それがどのリソース（レコード、台帳定義、フォルダ）に対する操作かを明確に示すラベルを追加。

#### **ステップ 5: `Ledger/RecordsTable.php` への概要情報とモーダル導線追加**

*   **目的**: 台帳一覧/検索画面に、フォルダ概要パネルと、各台帳定義ごとの概要情報および詳細への導線を追加する。
*   **作業内容**:
    1.  `app/Livewire/Common/FolderSummary.php` を作成。
    2.  `resources/views/livewire/common/folder-summary.blade.php` を作成。
    3.  `app/Livewire/Ledger/RecordsTable.php` を修正。
    4.  `records-table.blade.php` の画面上部に `@livewire('common.folder-summary', ['folderId' => $currentFolderId])` を埋め込む。
    5.  `FolderSummary` コンポーネント内で、現在のフォルダの権限概要（ログインユーザー視点）と、Activity / Permission 詳細モーダルを開くボタンを実装。
        *   モーダルは `ActivityHistoryDisplay` や `PermissionDisplay` を動的に読み込む形を検討。
    6.  各 `LedgerDefine` 行に、その定義の権限概要（アイコン等）と直近の活動概要（最終更新者、日時）を表示。
    7.  各 `LedgerDefine` 行に、Activity / Permission 詳細モーダルを開くボタンを実装。

---