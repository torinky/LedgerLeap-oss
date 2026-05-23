# 権限表示を概要先行へ再設計する計画

**作成日**: 2026-04-25  
**対象**: [resources/views/livewire/common/permission-display.blade.php](../../../../resources/views/livewire/common/permission-display.blade.php), [app/Livewire/Common/PermissionDisplay.php](../../../../app/Livewire/Common/PermissionDisplay.php), [tests/Feature/Livewire/Common/PermissionDisplayTest.php](../../../../tests/Feature/Livewire/Common/PermissionDisplayTest.php)

## Goal

台帳詳細画面の権限表示を、ロール・組織・ユーザーをただ並べる構成から、
「この台帳に誰がどの理由でアクセスできるか」を最初に理解できる構成へ見直す。

狙いは次の3点。

1. 初見でアクセス概要と自分の権限が分かること
2. 直接付与と継承の違いが、アイコン頼みではなく説明文でも読めること
3. フィルタ・検索・ページング時の loading 表示が、更新対象に対して自然に見えること

## User scenarios

### 1. 監査・コンプライアンス担当

- 台帳を開いてすぐに、誰が閲覧/編集できるかを把握したい
- その権限が direct か inherited かを短時間で確認したい
- 画面を行き来せずに、ロール・組織・ユーザーの対応を追いたい

### 2. 台帳オーナー / 業務管理者

- 権限の付け方が想定どおりかを確認したい
- 組織経由での付与と、個別ロール付与を区別して見たい
- 必要ならフィルタで対象を絞り、権限の全体像を崩さずに見たい

### 3. 部門責任者

- 自部署がどのロールを持ち、どのユーザーが対象かを確認したい
- 表の意味を読み解く前に、概要カードで自分に必要な情報を見たい

## Findings

公開 RBAC / IAM の説明では、平坦な一覧よりも次の見せ方が理解しやすい。

- 概要カードで全体像を先に出す
- 直接付与 / 継承を明示して、なぜ見えるかを説明する
- 詳細はロール・組織・ユーザーの順、またはタブ / アコーディオンで段階的に開く
- バッジは状態の要約に使い、説明の主役にしない

LedgerLeap の現行ビューは、ロール・組織・ユーザーの3セクションが縦に長く続き、
direct / inherited の意味もバッジ中心で伝えているため、初見の理解に負荷がある。

## Recommended shape

### 1. 上部に概要カード

- 自分の権限の要点
- アクセス可能なロール数 / 組織数 / ユーザー数
- direct / inherited の凡例

### 2. フィルタは概要の直下

- ロール
- 組織
- 権限種別
- ユーザー検索

### 3. 下部は3セクションのドリルダウン

- Roles
  - 直接付与 / 継承をテキストで補足
  - 権限バッジは短く、見出し側で意味を補う
- Organizations
  - direct_roles / inherited_roles を分けて見せる
  - 組織単位でのアクセスの理由を読み取りやすくする
- Users
  - 組織・ロール・権限を同じ行で追えるようにする
  - empty 状態は「対象なし」を明確に言う

### 4. loading は targeted overlay

- `filterByRoleId`
- `filterByOrganizationId`
- `filterByPermissionValue`
- `searchUserQuery`

これらの更新にだけ loading を寄せ、画面全体の重複描画は避ける。

## Design skill mapping

この計画では、UI を一括で判断せず、要素ごとに既存スキルへ照らしてレビューする。

### 概要カード / タイトル領域

- `title-block` の考え方に合わせて、最初に読むべき情報を短くまとめる
- 重要なテキストは固定で小さくしない
- バッジや補助アイコンは意味の主役にしない

### フィルタ領域

- 必要なら `form-layout` の考え方を使い、ラベルと入力の役割を明確にする
- 検索や選択は、説明文より操作のしやすさを優先する
- 条件が多いときほど、見出しと入力のまとまりを崩さない

### ロール / 組織 / ユーザーの各セクション

- `responsive-text-icon-sizing` に沿って、主情報の文字とアイコンを読みやすく保つ
- badge は状態や件数などの短いメタデータに限定する
- tooltip は補足に使い、主説明は本文側で読めるようにする

### loading 表示

- `livewire-loading-ui` に沿って、wire:target を対象操作へ絞る
- skeleton / overlay / opacity の役割を混ぜない
- 全体を重ね直すより、更新対象の上にだけ薄く載せる

### 共通の見え方

- `design.instructions.md` の「badge-first review」を使い、見出し・badge・tooltip を同時に確認する
- 直接 / 継承 / 対象数の意味が、色だけでなく文言でも読めるかを確認する
- desktop と mobile の両方で、primary text が小さすぎないかを見る

## Sprint breakdown

### Sprint 1: 情報設計の確定

- 概要カードに入れる項目を確定する
- direct / inherited の表現ルールを決める
- ロール / 組織 / ユーザーの読ませ順を固定する
- 必要な翻訳キーを洗い出す

### Sprint 2: UI 再構成

- [resources/views/livewire/common/permission-display.blade.php](../../../../resources/views/livewire/common/permission-display.blade.php) を再編する
- loading overlay を対象限定に整理する
- 見出し、凡例、空状態の見え方を整える
- 概要カード、フィルタ、各セクション、loading をそれぞれ既存のデザインスキルで個別に見直す
- 要素ごとの調整結果を、その場で `title-block` / `form-layout` / `responsive-text-icon-sizing` / `livewire-loading-ui` に照らして確認する
- ひとつの UI ブロックを直したら、必ず badge・tooltip・見出しの関係まで同時に確認する

### Sprint 3: 翻訳・テスト・記録

- 翻訳キーを追加する
- [tests/Feature/Livewire/Common/PermissionDisplayTest.php](../../../../tests/Feature/Livewire/Common/PermissionDisplayTest.php) を拡張する
- 実装で得た UI パターンを必要なら docs/work/ui-ux に追記する
- ユーザー側の最終 UI 調整結果を受けて、何が良くなり、何が残ったかを記録する
- 調整後の成果が他画面でも再現できるなら、`skill-maintenance` に戻せる単位まで一般化する
- 逆に単発の調整で終わるものは、`docs/work/ui-ux` に留めてスキルへは昇格しない

## Linked GitHub issues

- Parent: #165
- Sprint 1: #166
- Sprint 2: #167
- Sprint 3: #168

## Scope

### Included

- PermissionDisplay の表示整理
- 概要カード / 凡例 / 説明文の追加
- targeted loading の整理
- 翻訳キーと Livewire テストの追加

### Excluded

- 権限計算ロジックの全面改修
- 監査履歴の完全な grant path 追跡
- DB スキーマ変更

## Verification

1. Livewire テストでレンダリングと主要フィルタの回帰を確認する
2. 画面で概要カード、direct / inherited、空状態、loading の見え方を確認する
3. 翻訳キー追加後は translations:compare を通す
4. Tailwind class を増やしたら npm build を通す

## Acceptance criteria

- [ ] 初期表示でアクセス概要が分かる
- [ ] direct / inherited が icon だけでなく文言でも判別できる
- [ ] フィルタ時の loading が対象に限定される
- [ ] 翻訳キーが追加され、ハードコード文言が増えていない
- [ ] テストが追加され、主要な表示意図が固定されている

## Evidence

- [resources/views/livewire/common/permission-display.blade.php](../../../../resources/views/livewire/common/permission-display.blade.php)
- [app/Livewire/Common/PermissionDisplay.php](../../../../app/Livewire/Common/PermissionDisplay.php)
- [app/Services/PermissionService.php](../../../../app/Services/PermissionService.php)
- [tests/Feature/Livewire/Common/PermissionDisplayTest.php](../../../../tests/Feature/Livewire/Common/PermissionDisplayTest.php)
- [docs/work/ui-ux/2026-04-18_ledger-detail-ui-redesign-retrospective.md](2026-04-18_ledger-detail-ui-redesign-retrospective.md)
- [docs/work/ui-ux/2026-04-11_ledger-index-manager-ui-plan.md](2026-04-11_ledger-index-manager-ui-plan.md)

## Notes

- この計画は、概要先行 + ドリルダウンの構成を前提にしている。
- 完全な権限由来の履歴表示は、現状のデータ構造だけでは不足する可能性があるため次段階に分ける。
- まずは「理解しやすい表示」を先に固め、必要になった段階で由来メタデータを拡張する。
- ユーザーの最終調整を前提に、Sprint 3 で学びを回収し、再利用できるものだけをスキルへ戻す。

## Sprint status

### Sprint 1: 完了

**完了内容**
- 概要カード先行の情報設計を確定した
- direct / inherited はバッジだけでなく説明文でも補足する方針にした
- ロール / 組織 / ユーザーは、初見で追いやすい順序に並べる前提を固定した
- 必要な翻訳キーは、後続 Sprint で追加する対象として整理した

**判断メモ**
- 役割・組織・ユーザーの3表をそのまま見せるのではなく、先に「誰がどの理由で見えるか」を要約する
- 監査・台帳オーナー向けに、直接付与と継承の見分けやすさを最優先にする
- 具体的な UI 調整は Sprint 2 に分離し、Sprint 1 は情報設計の確定に集中する

### Sprint 2: 完了

**完了内容**
- `permission-display.blade.php` を概要カード、フィルタカード、3つの詳細カードに再構成した
- loading 表示を `wire:target` 付きの overlay と opacity 制御に整理し、重複描画をなくした
- direct / inherited の凡例を上部で明示し、各セクションでも文言で補足するようにした
- badge 化した状態表示には icon + tooltip を付け、文字は secondary metadata として読めるサイズ感に抑えた
- 概要パネルに「対象リソース」と「閲覧者」を明示し、アクセスレベルと権限は icon-bearing の badge / stat 表示にした
- `PermissionDisplayTest` に概要・フィルタ・凡例の表示確認を追加し、`./vendor/bin/sail test tests/Feature/Livewire/Common/PermissionDisplayTest.php` が通過した

**判断メモ**
- 情報量の多い権限表示は、概要先行で全体像を把握してから詳細を追う方が理解しやすい
- badge だけに頼らず、見出しと文言で direct / inherited の意味を補足することで、監査用途でも読み取りやすくなる
- loading は対象操作に絞ることで、どの操作で何が更新されているかを UI 上で追いやすくできる
- 文字サイズは主情報を縮めすぎず、badge や補足ラベルはあくまで metadata として扱うのが `responsive-text-icon-sizing` に沿う
- `title-block` 的な読み順を維持しつつ、`stats` と icon-bearing badge で count / status / subject を分けると、汎用コンポーネントでも意味が崩れにくい

### Sprint 3: 進行中

**完了内容**
- 概要パネルに対象リソースと閲覧者を追加し、「誰の何に対する権限か」を先に読めるようにした
- direct / inherited の状態表示を icon-only + tooltip に統一し、長いラベルをカード内から外した
- 権限バッジを icon-bearing に揃え、色・状態・意味を分離した
- 翻訳キー参照は `ledger.activity.column.subject` を使う形へ整理し、実在キーに合わせた
- 要求元フォルダの breadcrumb を階層ごとにリンク化し、各フォルダの edit 画面へ遷移できるようにした
- `PermissionDisplayTest` を再実行し、8 passed で回帰がないことを確認した
- 振り返りは [docs/work/ui-ux/2026-04-25_permission-display-overview-retrospective.md](2026-04-25_permission-display-overview-retrospective.md) に記録した

**判断メモ**
- 権限表示のような compact な状態領域は、一覧の意味を説明する前に subject / viewer を置くと読みやすい
- 状態マーカーは、文字を足すより icon-only + tooltip のほうが情報密度に合う
- badge / tooltip / heading の役割を分けると、同じ画面を違うロールで読んでも解釈しやすい
- 再利用可能な学びは design.instructions に昇格し、案件固有の調整は docs/work に留める
- まだ残る細かな見え方調整は Sprint 3 のまま吸収し、完了扱いにはしない
