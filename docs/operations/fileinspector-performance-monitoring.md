# FileInspector パフォーマンス測定ガイド
**最終更新:** 2026-03-21
**対象:** LedgerLeap v12.0以降
**関連作業記録:** `docs/work/ui-ux/2026-03-21_issue-114_performance_monitoring_and_regression_detection_report.md`
---
## 1. このドキュメントの整理思想
この文書は、FileInspector の性能観測について **「どの指標を browser.log で見るか」** を迷わないための運用手順です。
backend の回帰検知は `docs/operations/ledger-records-performance-monitoring.md` に分離し、ここでは **browser-only の体感測定** に絞ります。
そのため、次の方針で整理します。
- フロントエンド測定は `browser.log` / DevTools Console に寄せる
- FileInspector の測定は `performance_stats.json` に重複記録しない
- backend の性能ログとは役割を分ける
- 日常運用では「画面が遅いか」を確認し、原因分析は browser log を起点にする
---
## 2. 監視対象
### 2.1 browser-only の測定メトリクス
| メトリクス | 説明 | 確認方法 |
|---|---|---|
| `drawer_open` | ドロワーが開いて内容が見えるまでの時間 | Console / browser.log |
| `tab_switch` | Content / Details / History / Permissions の切り替え時間 | Console / browser.log |
| `image_preview_load` | 画像プレビューの初回読み込み時間 | Console / browser.log |
> 重要: これらは **Livewire へ送らず**、browser-side の測定として確認します。
> そのため `performance_stats.json` には書き込みません。
### 2.2 backend 側の性能ログとの役割分担
- `performance-YYYY-MM-DD.log`
  - backend の閾値超過 warning
- `performance_stats.json`
  - backend の詳細分析用
- `browser.log` / DevTools Console
  - FileInspector の体感測定用
---
## 3. 設定方法
### 3.1 環境変数
```dotenv
PERFORMANCE_MONITORING_ENABLED=true
PERFORMANCE_LOG_DESTINATION=both
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=true
```
### 3.2 補足
- `PERFORMANCE_LOG_DESTINATION` は backend の性能ログに影響します
- FileInspector の browser-only 測定は、`PERFORMANCE_LOG_DESTINATION` を変えても browser.log 側に残ります
- 本番環境では、必要に応じて browser-only 測定を無効化できます
---
## 4. 動作確認
### 4.1 browser.log / DevTools Console で確認
1. 台帳詳細画面を開く
2. Chrome DevTools を開く
3. Console タブでログを確認する
4. FileInspector ドロワーを開く
表示例:
```text
[FileInspector Performance] Drawer open started at: 12345.67
[FileInspector Performance] Drawer open duration: 2033.45 ms
[FileInspector Performance] Tab switch: content -> history 22.00 ms
[FileInspector Performance] Image preview loaded { duration_ms: 512.34, url: "...", cached: false }
```
### 4.2 確認ポイント
- `drawer_open` が開閉ごとに1回だけ出るか
- `tab_switch` が切り替えのたびに出るか
- `image_preview_load` が初回読み込み時のみ出るか
- browser.log に残っているログが、backend の warning と混ざっていないか
---
## 5. backend 側ログとの見分け方
### 5.1 `performance-YYYY-MM-DD.log`
backend の warning は日次ローテートされた `performance-YYYY-MM-DD.log` に出ます。
FileInspector の browser-only 測定はここに出しません。
### 5.2 `performance_stats.json`
`performance_stats.json` は backend 指標の分析用です。
FileInspector の browser-only メトリクスをこのファイルに保存しないのは、**frontend の体感測定と backend の回帰検知を混線させないため**です。
---
## 6. 無効化 / 最小化
### 6.1 完全に無効化
```dotenv
PERFORMANCE_MONITORING_ENABLED=false
```
### 6.2 片方だけ無効化
```dotenv
PERFORMANCE_METRIC_DRAWER_OPEN=true
PERFORMANCE_METRIC_TAB_SWITCH=false
```
### 6.3 backend ログのみ最小化
```dotenv
PERFORMANCE_LOG_DESTINATION=none
```
> 注意: `PERFORMANCE_LOG_DESTINATION=none` は backend 側の性能ログを止める設定です。
> browser-only の FileInspector 測定は別経路のため、完全停止には別途 frontend 側の機能無効化が必要です。
---
## 7. トラブルシューティング
### 7.1 browser.log に何も出ない
- DevTools Console を開いているか確認する
- 該当画面で FileInspector を開き直す
- browser 側のログ収集が別途抑制されていないか確認する
### 7.2 backend の `performance_stats.json` に FileInspector がない
これは正常です。
FileInspector の browser-only メトリクスは、現在 backend の JSON には書きません。
### 7.3 backend の `performance-YYYY-MM-DD.log` が空
- `threshold_exceeded` が発生していない可能性が高いです
- これは「未実装」ではなく、閾値を超える異常がまだ観測されていない状態です
---
## 8. 参照先
- backend の運用仕様: `docs/operations/ledger-records-performance-monitoring.md`
- 作業記録: `docs/work/ui-ux/2026-03-21_issue-114_performance_monitoring_and_regression_detection_report.md`
- backend 実装: `app/Livewire/Traits/LogPerformance.php`
- browser-only 実装: `resources/views/livewire/attached-file/file-inspector.blade.php`
- browser-only 実装: `resources/views/livewire/attached-file/file-inspector/preview.blade.php`
