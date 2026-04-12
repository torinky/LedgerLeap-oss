# 2026-04-12 Ledger CSV Export Batchable Lesson

**status:** complete
**last_confirmed_at:** 2026-04-12
**recheck_after:** 2026-07-12
**recheck_trigger:** `Bus::batch()` を使うジョブのリファクタや、`ExportJob` / `ProcessAttachedFile` などのバッチ実装見直し時

## Goal

`Bus::batch()` を使う台帳 CSV エクスポートで、`ExportJob` に必要な `Batchable` が抜けると実行時例外になる件を記録する。

## Findings

- `app/Livewire/Ledger/Export.php` は `Bus::batch()` で `ExportJob` を送る。
- `ExportJob` には `Illuminate\Bus\Batchable` が必要。
- `Batchable` が無いと `Attempted to batch job ... but it does not use the Batchable trait.` で落ちる。
- このパターンは `Batchable` を戻すだけで解消した。

## Evidence

- `app/Livewire/Ledger/Export.php`
- `app/Jobs/Ledger/ExportJob.php`
- `tests/Feature/Exports/LedgerExportTest.php`

## Notes

- `Bus::batch()` を使うジョブは、`ShouldQueue` だけでは不十分。
- 将来の再発防止として、バッチ導入時は `Batchable` の有無を最初に確認する。
- これは現時点ではドメイン固有の作業メモで、専用 skill へ昇格するのは同種事例が複数出てからでよい。

