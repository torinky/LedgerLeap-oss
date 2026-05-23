# [Issue]: CSVエクスポートの状態表示とダウンロード導線を整理する

## イシュー種別
改善

## 概要
台帳一覧の CSV エクスポートについて、処理中・完了後の状態表示とダウンロード導線を整理したい。
現在はボタンの表示や状態が分かりにくく、完了通知や不活性状態の見え方が利用者の期待とずれている。

## 背景 / 目的
- CSV エクスポートはバッチで生成しており、生成完了までに時間がかかる場合がある。
- しかし現状の UI では、処理中か完了済みかが分かりづらく、ダウンロード可否も直感的ではない。
- 利用者が「今なにが起きているか」を把握でき、迷わず次の操作へ進める導線にしたい。

## 現状
- 参照ファイル:
  - `resources/views/components/ledgerDefine/header.blade.php`
  - `resources/views/livewire/ledger/export.blade.php`
  - `app/Livewire/Ledger/Export.php`
  - `tests/Feature/Exports/LedgerExportTest.php`
- 既存の挙動:
  - CSV 生成は `Bus::batch()` で非同期実行している。
  - 生成中と完了後の見え方が分かりにくい。
  - ダウンロード導線が押せる/押せない状態と表示状態でずれることがある。
- 制約:
  - CSV ファイル生成はバッチ処理のまま維持する。
  - 生成完了前に実ファイルの直接生成・直接ダウンロードへ寄せない。

## 目標 / 完了状態
- CSV エクスポート開始後、処理中であることが一目で分かる。
- 完了後は、ダウンロードできる状態が明確に分かる。
- ボタンの見た目と実際の動作が一致している。
- 不活性状態のボタンを押しても、ユーザーが「何も起きない」と感じない。

## スコープ / 非スコープ
**対象:**
- 台帳一覧の CSV エクスポート UI
- 生成中・完了後の状態表示
- ダウンロード導線の見せ方と有効/無効状態

**対象外:**
- CSV の出力内容そのものの再設計
- バッチ生成方式の廃止
- エクスポートの権限モデル見直し

## 方針候補 / メモ
1. 状態ごとにボタン表示を分ける
   - 生成開始
   - 生成中
   - 完了後ダウンロード
2. 1つの導線に寄せる場合は、ラベルや icon、disabled 状態で明確に区別する
3. トーストは補助通知に留め、状態確認の主手段にはしない

## スプリント分解
- [ ] Sprint 1: 現行 UI と状態遷移の整理
- [ ] Sprint 2: ボタン表示と導線の修正
- [ ] Sprint 3: 回帰テストの追加と確認

## エビデンス / 参照先
- `app/Livewire/Ledger/Export.php`
- `resources/views/livewire/ledger/export.blade.php`
- `resources/views/components/ledgerDefine/header.blade.php`
- `tests/Feature/Exports/LedgerExportTest.php`
- `git commit`: `96b760a0`（CSV エクスポートのバッチ処理復元）
- `./vendor/bin/sail test tests/Feature/Exports/LedgerExportTest.php` で回帰確認済み

## 完了条件
- [ ] CSV エクスポートの状態表示が、利用者にとって分かりやすくなっている
- [ ] 生成中と完了後で、ダウンロード可否が視覚的に判別できる
- [ ] ボタンの表示状態と実際の動作が一致している
- [ ] 回帰テストで CSV エクスポート導線が通る
- [ ] バッチ生成を維持したまま改善できている

## 関連リンク
- Issue / PR: なし
- docs: `docs/work/exports/2026-04-12_ledger-csv-export-batchable-lesson.md`

## 確認事項
- [x] バグ報告ではないことを確認した
- [x] 背景 / 現状 / 目標 / スコープを分けて書いた
- [x] スプリント分解と完了条件を記入した
- [x] 参照先やエビデンスを可能な範囲で添付した

