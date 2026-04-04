# Issue #133 / 台帳一覧→詳細の highlight query 継承 振り返り

**作成日**: 2026-04-04  
**対象 Issue**: [#133](https://github.com/torinky/LedgerLeap/issues/133)

---

## 1. 何を直したか

Issue #133 では、台帳一覧から詳細画面へ遷移する際に消えていた `highlight` クエリを復元した。

対応内容は次のとおり。

- `resources/views/components/ledger/table-row.blade.php`
  - 詳細リンク生成時に、`highlightKeyword` がある場合だけ `highlight` クエリを付与するよう復元した
- `tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php`
  - 一覧の強調表示だけでなく、詳細リンク URL に `highlight` が入ることを回帰テストで確認した
- `app/Livewire/Ledger/Show.php`
  - 既存の `#[Url(as: 'highlight')]` 受け口はそのまま活かした

結果として、一覧の検索語は詳細画面で再現され、過去実装に近い動作へ戻した。

---

## 2. 何が問題だったか

問題の本質は、**ドキュメントではなくリンク生成側の回帰** だった。

- 正常だった過去実装では、`494c1754` 時点で `table-row.blade.php` の詳細リンクに `highlight` が含まれていた
- その後の `69467dfc` で、詳細リンクから `highlight` が削除された
- 詳細画面側の `highlight` 受け口自体は生きていたため、画面の描画ロジックではなく **一覧→詳細の導線** が壊れていた

つまり、今回必要だったのは「新しい仕様を作ること」ではなく、**既にあった意図をコードで復元すること** だった。

---

## 3. 既存ドキュメントとの整合

この作業は、既存の `docs/work` と矛盾しない。

### 3.1 URL 正規化計画との一致

`docs/work/ui-ux/ledger-list-redesign/2026-03-29_ledger-list-url-normalization-plan.md` では、次の方針が既に明記されている。

- `highlight` は **一覧 canonical から外す**
- `highlight` は **詳細 canonical の共有対象** として扱う
- `localStorage` は補助記憶であり、共有URLの正本にはしない

今回の復元は、この方針どおりに **詳細側の query 継承を戻しただけ** なので、計画書の更新は不要だった。

### 3.2 以前の修正レポートとの一致

`docs/work/ui-ux/attachment/2025-12-27_phase4-2_highlight_fix_report.md` は、詳細画面の `highlight` 受け口と添付ファイル側の伝搬を整備した記録である。

今回の問題はその後段の一覧→詳細導線で発生したため、既存の修正意図とも整合している。

---

## 4. 学び

### 4.1 URL 系の regression は「受け口」ではなく「生成側」を疑う

Livewire の `#[Url]` が生きていても、リンク生成側が query を落としていれば強調表示は消える。

そのため、今回のような症状では次の順で確認するのが早い。

1. 過去の正常 commit でリンク生成を確認する
2. 現在の route 生成を比較する
3. 詳細側の受け口が残っているかを見る
4. 表示テストだけでなく href そのものも検証する

### 4.2 localStorage と URL の役割を混同しない

今回の件では、検索語の継承と折りたたみ状態の保存が同じ「状態保存」として見えやすかった。

ただし実際には、

- `highlight` は **URL 正本**
- 折りたたみなどの UI 状態は **localStorage 補助**

という分担が適切だった。

### 4.3 テストは表示結果だけでなく導線も固定する

`assertSeeHtml('<mark ...>')` だけでは、詳細画面に遷移する前の link regression を防ぎきれない。

今回からは、一覧の回帰テストで **詳細リンクの href に `highlight` が入ること** も確認するようにした。

---

## 5. docs/work / .github の扱い

今回は、この学びは **feature-local な URL 継承の復元** に留まると判断した。

- `docs/work` には、この振り返りを残す
- `.github` 側の新規 skill 化や instruction 化はしない
- すでに `ledger-list-url-normalization-plan.md` が canonical ルールを保持しているため、上書きは不要

将来、同種の URL query 継承 regression が複数機能で再発したら、その時点で reusable guidance を再検討する。

---

## 6. 参照先

- Issue: [#133](https://github.com/torinky/LedgerLeap/issues/133)
- Commit: `76118271` (`fix(ledger): restore highlight query on detail links`)
- 正常だった過去実装: `494c1754`
- regression が入ったコミット: `69467dfc`
- 実装: `resources/views/components/ledger/table-row.blade.php`
- テスト: `tests/Feature/Livewire/Ledger/RecordsTableQueryTest.php`
- 詳細受け口: `app/Livewire/Ledger/Show.php`
- URL 正規化計画: `docs/work/ui-ux/ledger-list-redesign/2026-03-29_ledger-list-url-normalization-plan.md`
- 先行レポート: `docs/work/ui-ux/attachment/2025-12-27_phase4-2_highlight_fix_report.md`

---

## 7. Freshness

- status: confirmed
- last_confirmed_at: 2026-04-04
- recheck_after: 次回 `ledger.show` の URL 生成、`table-row.blade.php` の詳細リンク、または canonical URL 仕様を変更するとき
- recheck_trigger: 一覧→詳細の `highlight` 継承が再び消える、あるいは URL 正規化方針に変更が入るとき

