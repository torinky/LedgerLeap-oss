# W2-1.4 性能・アクセシビリティ要件定義

**最終更新:** 2026-01-04  
**対象:** LedgerLeap v12.0 / Branch: `feature/ledger-rollback`  
**ステータス:** Done（PM承認済み）  
**管理場所:** `docs/work/core-features/ledger-diff-rollback/`

---

## 関連ドキュメント

本ドキュメントは以下の検討結果を踏まえています:

- [2026-01-03_plan.md](2026-01-03_plan.md) - 台帳差分表示拡充・ロールバック全体計画
- [2026-01-04_W2-1-1_diff_component_scope.md](2026-01-04_W2-1-1_diff_component_scope.md) - 共通差分コンポーネント機能範囲定義
- [2026-01-04_W2-1-2_role_wording_policy.md](2026-01-04_W2-1-2_role_wording_policy.md) - 基本情報タブとの役割分担・文言ポリシー
- [2026-01-04_W2-1-3_persona_ux_requirements.md](2026-01-04_W2-1-3_persona_ux_requirements.md) - ペルソナ別UX要件整理

---

## 1. 目的・背景

W2-1.1〜W2-1.3で定義した機能要件を踏まえ、Phase 1の実装において満たすべき性能要件とアクセシビリティ要件を定義する。特に、大量の履歴データを扱う際のパフォーマンスと、キーボード操作を中心としたアクセシビリティを重視する。

### 記載範囲

- 性能要件（レスポンス目標、遅延ロード、ページング）
- アクセシビリティ要件（キーボード操作、フォーカス制御、スクリーンリーダー対応）
- 既存実装との整合性確認

### 記載しない内容

- 具体的な実装方法（W3-1.1で定義）
- Phase 2以降の要件

---

## 2. 性能要件

### 2.1 レスポンス目標

#### 2.1.1 初回表示

| 操作 | 目標時間 | 測定条件 | 備考 |
|------|---------|---------|------|
| 基本情報タブの即時差分表示 | 500ms以内 | 通常サイズの台帳（カラム数50以下） | 既存実装の維持 |
| 更新履歴タブの初回ロード | 1000ms以内 | 履歴件数100件以下 | 遅延ロード適用 |
| 専用履歴画面の初回ロード | 1000ms以内 | 履歴件数100件以下 | 既存実装の維持 |

#### 2.1.2 差分表示切替

| 操作 | 目標時間 | 測定条件 | 備考 |
|------|---------|---------|------|
| 任意2バージョン比較の表示 | 800ms以内 | 通常サイズの台帳 | サーバー側差分計算含む |
| スナップショット表示への切替 | 300ms以内 | クライアント側のみ | 差分OFF/ON切替 |
| スライダーUIでのバージョン移動 | 500ms以内 | 既存履歴画面 | 既存実装の維持 |

#### 2.1.3 ユーザー操作

| 操作 | 目標時間 | 測定条件 | 備考 |
|------|---------|---------|------|
| 検索ハイライトの適用 | 200ms以内 | 検索キーワード入力後 | クライアント側処理 |
| 編集者情報ポップオーバー表示 | 100ms以内 | クリック/ホバー時 | 既存データから生成 |
| コピーボタンのフィードバック | 即時 | クリップボードコピー | ユーザー体感 |

### 2.2 遅延ロード（Lazy Loading）

#### 2.2.1 更新履歴タブ

**方針:**
- 更新履歴タブは、タブが選択されるまでコンポーネントをロードしない。
- Livewireの `wire:init` または Alpine.js の `x-init` を活用。

**実装要件:**
- タブ選択時に初めて履歴データを取得。
- ローディングスピナーを表示し、ユーザーに待機状態を明示。
- 一度ロードしたデータはキャッシュし、再選択時は即座に表示。

#### 2.2.2 承認履歴テーブル

**方針:**
- 初回表示時は最新20件のみを表示。
- スクロールまたは「もっと見る」ボタンで追加ロード（無限スクロールまたはページング）。

**実装要件:**
- 初回ロード: 最新20件
- 追加ロード: 20件ずつ
- 最大表示件数: 制限なし（パフォーマンス劣化時は要検討）

### 2.3 ページング

#### 2.3.1 専用履歴画面

**方針:**
- スライダーUIは既存実装を維持（全履歴をメモリに保持しない）。
- 現在表示中のバージョン前後のデータのみをプリフェッチ。

**実装要件:**
- 表示中バージョン ± 5件をプリフェッチ。
- スライダー移動時にスムーズに表示できるよう、バックグラウンドで取得。

#### 2.3.2 更新履歴タブ

**方針:**
- 承認履歴テーブルは**無限スクロール方式**で追加ロード。
- 差分表示エリアは選択された2バージョンのみを表示（ページング不要）。

**実装要件:**
- Intersection Observer API を使用した無限スクロール。
- スクロール位置が最下部に近づいたら、次の20件を自動取得。
- ローディング中はスピナーを表示。

### 2.4 大量データ対応

#### 2.4.1 想定データ量（PM承認済み）

| データ種別 | 想定最大値 | 備考 |
|----------|----------|------|
| 台帳カラム数 | 100 | 大規模な台帳定義 |
| 履歴件数 | **100** | 台帳1レコードの変更履歴、1000件はユーザーシナリオ上発生しない |
| 添付ファイル数（1バージョンあたり） | 50 | 大量添付のケース |

#### 2.4.2 対応方針

- **カラム数が多い場合**: グループ開閉機能で初期表示を最小化。
- **履歴件数が多い場合**: 遅延ロード・無限スクロールで初期ロードを軽量化（想定100件で十分対応可能）。
- **添付ファイルが多い場合**: サムネイル遅延ロード。

### 2.5 性能測定の実装

#### 2.5.1 測定方針

**PM承認結果:**
- レスポンス目標を達成できているか測定できるよう実装する。
- `docs/operation` のモニタリング手法に従う。

#### 2.5.2 測定対象

| 測定項目 | 測定タイミング | 目標値 | 測定方法 |
|---------|-------------|--------|----------|
| 更新履歴タブの初回ロード | タブ選択時 | 1000ms以内 | Performance API |
| 任意2バージョン比較の表示 | 比較実行時 | 800ms以内 | Performance API |
| 検索ハイライトの適用 | 検索キーワード入力後 | 200ms以内 | Performance API |
| 編集者情報ポップオーバー表示 | クリック/ホバー時 | 100ms以内 | Performance API |

#### 2.5.3 実装要件

**参考実装:** `docs/operations/fileinspector-performance-monitoring.md`

- **環境変数による制御**:
  - `.env` ファイルで測定機能のON/OFF切替を可能にする。
  - 開発環境ではデフォルトで有効、本番環境ではデフォルトで無効。
  - 測定するメトリクスごとに個別にON/OFF可能。
  
  ```dotenv
  # パフォーマンス測定機能の有効化
  PERFORMANCE_MONITORING_ENABLED=true
  
  # 測定するメトリクスの種類
  PERFORMANCE_METRIC_HISTORY_TAB_LOAD=true
  PERFORMANCE_METRIC_VERSION_COMPARE=true
  PERFORMANCE_METRIC_SEARCH_HIGHLIGHT=true
  PERFORMANCE_METRIC_EDITOR_INFO_POPOVER=true
  ```

- **設定ファイル** (`config/ledgerleap.php`):
  ```php
  'performance' => [
      'enabled' => env('PERFORMANCE_MONITORING_ENABLED', env('APP_ENV') === 'local'),
      'log_destination' => env('PERFORMANCE_LOG_DESTINATION', 'both'),
      'metrics' => [
          'history_tab_load' => env('PERFORMANCE_METRIC_HISTORY_TAB_LOAD', true),
          'version_compare' => env('PERFORMANCE_METRIC_VERSION_COMPARE', true),
          'search_highlight' => env('PERFORMANCE_METRIC_SEARCH_HIGHLIGHT', true),
          'editor_info_popover' => env('PERFORMANCE_METRIC_EDITOR_INFO_POPOVER', true),
      ],
  ],
  ```

- **Performance API の活用**:
  - `performance.mark()` でタイミングをマーク。
  - `performance.measure()` で処理時間を測定。
  - 開発環境では `console.log()` で測定結果を出力。
  
- **ログ記録** (Livewireコンポーネント):
  ```php
  public function logPerformance(string $metric, float $duration, array $metadata = []): void
  {
      if (! config('ledgerleap.performance.enabled', false)) {
          return;  // 測定無効時は何もしない
      }
      
      if (! config("ledgerleap.performance.metrics.{$metric}", true)) {
          return;  // メトリクスが無効時は何もしない
      }
      
      $logData = array_merge([
          'metric' => $metric,
          'duration_ms' => round($duration, 2),
      ], $metadata);
      
      // Laravel標準ログに出力
      if (in_array(config('ledgerleap.performance.log_destination'), ['log', 'both'])) {
          Log::info("[Ledger Diff Performance] {$metric}", $logData);
      }
      
      // JSON統計ファイルに出力
      if (in_array(config('ledgerleap.performance.log_destination'), ['json', 'both'])) {
          $this->appendToJsonStats($logData);
      }
  }
  ```

- **Bladeテンプレートでの測定**:
  ```blade
  @php
      $performanceEnabled = config('ledgerleap.performance.enabled', false);
      $historyTabMetricEnabled = config('ledgerleap.performance.metrics.history_tab_load', true);
  @endphp
  
  @if($performanceEnabled && $historyTabMetricEnabled)
  <div x-data="{
      measureHistoryTabLoad() {
          performance.mark('history-tab-load-start');
          
          // タブロード処理
          $wire.loadHistoryData().then(() => {
              performance.mark('history-tab-load-end');
              performance.measure('history-tab-load', 'history-tab-load-start', 'history-tab-load-end');
              
              const measure = performance.getEntriesByName('history-tab-load')[0];
              console.log(`[Ledger Diff Performance] History tab load time: ${measure.duration}ms`);
              
              $wire.logPerformance('history_tab_load', measure.duration, {
                  ledger_id: {{ $ledgerRecord->id }},
                  diff_count: {{ $ledgerDiffCount }}
              });
          });
      }
  }">
  @endif
  ```

- **ログフォーマット**:
  - **Laravel標準ログ** (`storage/logs/laravel-YYYY-MM-DD.log`):
    ```
    [2026-01-04 14:55:00] local.INFO: [Ledger Diff Performance] history_tab_load {"metric":"history_tab_load","duration_ms":950.5,"ledger_id":123,"diff_count":45}
    ```
  
  - **JSON統計ファイル** (`storage/logs/performance_stats.json`):
    ```json
    [
        {
            "metric": "history_tab_load",
            "duration_ms": 950.5,
            "ledger_id": 123,
            "diff_count": 45,
            "timestamp": "2026-01-04T14:55:00.123456Z"
        }
    ]
    ```

- **統計分析コマンド**:
  ```bash
  # 更新履歴タブロード時間の平均
  cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "history_tab_load") | .duration_ms] | add / length'
  
  # 任意2バージョン比較時間の最大値
  cat storage/logs/performance_stats.json | jq '[.[] | select(.metric == "version_compare") | .duration_ms] | max'
  ```

- **モニタリング連携**:
  - `docs/operations/fileinspector-performance-monitoring.md` の手法に従い、本番環境での測定データを収集。
  - 目標値を超えた場合のアラート設定（運用フェーズで検討）。
  - ベンチマーク結果を定期的にドキュメント化。

---

## 3. アクセシビリティ要件

### 3.1 キーボード操作

#### 3.1.1 基本操作

| 操作 | キー | 動作 | 備考 |
|------|------|------|------|
| タブ間移動 | `Tab` / `Shift+Tab` | フォーカス可能な要素間を移動 | 標準動作 |
| タブ選択 | `Enter` / `Space` | 選択中のタブを開く | DaisyUI標準 |
| モーダル/ポップオーバーを閉じる | `Esc` | 開いているモーダルを閉じる | 標準動作 |

#### 3.1.2 承認履歴テーブル

| 操作 | キー | 動作 | 備考 |
|------|------|------|------|
| 行間移動 | `↑` / `↓` | 前後の履歴行にフォーカス移動 | 実装必須 |
| バージョン選択 | `Space` | 比較対象として選択/解除 | チェックボックス操作 |
| 詳細表示 | `Enter` | 選択中のバージョンの詳細を表示 | スナップショット表示 |

#### 3.1.3 差分表示エリア

| 操作 | キー | 動作 | 備考 |
|------|------|------|------|
| グループ開閉 | `Enter` / `Space` | フォーカス中のグループを開閉 | 既存実装の維持 |
| 次のグループへ移動 | `Tab` | 次のグループヘッダーにフォーカス | 標準動作 |
| 差分ON/OFFトグル | `Ctrl+D` | 差分表示の切替 | ショートカット（オプション） |

#### 3.1.4 編集者情報ポップオーバー

| 操作 | キー | 動作 | 備考 |
|------|------|------|------|
| ポップオーバーを開く | `Enter` / `Space` | 編集者名にフォーカス時 | 実装必須 |
| コピーボタン操作 | `Tab` → `Enter` | ポップオーバー内のコピーボタンを操作 | 標準動作 |
| ポップオーバーを閉じる | `Esc` | ポップオーバーを閉じる | 標準動作 |

### 3.2 フォーカス制御

#### 3.2.1 フォーカストラップ

**対象:**
- モーダル（将来的な詳細差分表示用）
- ポップオーバー（編集者情報表示）

**要件:**
- モーダル/ポップオーバーが開いている間、フォーカスはその内部に留まる。
- `Tab` で最後の要素に到達した場合、最初の要素に戻る。
- `Shift+Tab` で最初の要素に到達した場合、最後の要素に戻る。

#### 3.2.2 フォーカス復帰

**要件:**
- モーダル/ポップオーバーを閉じた際、開く前にフォーカスがあった要素に復帰。
- タブ切替時、前回フォーカスがあった要素を記憶し、再選択時に復帰（オプション）。

#### 3.2.3 フォーカス可視化

**要件:**
- すべてのインタラクティブ要素（ボタン、リンク、チェックボックス等）にフォーカスリングを表示。
- DaisyUI/Tailwind CSS の標準フォーカススタイルを使用。
- カスタムスタイルが必要な場合も、視認性を確保。

### 3.3 スクリーンリーダー対応

#### 3.3.1 ARIA属性

**承認履歴テーブル:**
- `role="table"`, `role="row"`, `role="cell"` を適切に設定。
- 比較対象として選択された行に `aria-selected="true"` を設定。
- バージョン番号、更新日時、更新者を `aria-label` で明示。

**差分表示エリア:**
- グループの開閉状態を `aria-expanded` で示す。
- 変更されたカラムに `aria-label="変更あり"` を設定（オプション）。

**編集者情報ポップオーバー:**
- `role="dialog"`, `aria-labelledby`, `aria-describedby` を設定。
- コピーボタンに `aria-label="メールアドレスをコピー"` 等の説明を設定。

#### 3.3.2 ライブリージョン

**要件:**
- コピー成功時のフィードバックを `aria-live="polite"` で通知。
- 差分表示の切替時、`aria-live="polite"` で「差分表示をONにしました」等を通知（オプション）。

#### 3.3.3 代替テキスト

**要件:**
- アイコンのみのボタンには `aria-label` を設定。
- ステータスバッジには `aria-label` でステータス名を明示。

### 3.4 カラーコントラスト

**要件:**
- WCAG 2.1 AA基準を満たす（コントラスト比 4.5:1 以上）。
- DaisyUI のテーマがデフォルトで基準を満たしていることを確認。
- カスタムカラーを使用する場合は、コントラストチェッカーで検証。

---

## 4. 既存実装との整合性

### 4.1 基本情報タブ

**性能:**
- 既存の即時差分表示のレスポンス（500ms以内）を維持。
- 表示レベル切替、グループ開閉のパフォーマンスを維持。

**アクセシビリティ:**
- 既存のキーボード操作（グループ開閉等）を維持。
- フォーカス制御の一貫性を確保。

### 4.2 専用履歴画面

**性能:**
- スライダーUIの既存パフォーマンス（500ms以内）を維持。
- ワークフロー情報カード表示のレスポンスを維持。

**アクセシビリティ:**
- スライダーのキーボード操作（`←` / `→`）を確保。
- ワークフロー情報カードのスクリーンリーダー対応を確認。

---

## 5. PM確認事項（承認結果）

> [!NOTE]
> **PM承認済み** (2026-01-04)
> - 履歴件数の想定: 100件程度で十分（1000件はユーザーシナリオ上発生しない）
> - ページング方式: 無限スクロール方式を採用
> - 性能測定: 測定できるよう実装すること（`docs/operation`のモニタリング手法に従う）
> - その他の要件: 提案内容で承認

1. **レスポンス目標の妥当性**:
   - **PM承認結果**: 提示した目標時間で承認。
   - 実現可能性とユーザー体験のバランスが取れている。

2. **遅延ロード・ページングの方式**:
   - **PM承認結果**: 無限スクロール方式を採用。
   - スクロールで処理できるものであれば無限スクロールで問題ない。

3. **履歴件数の想定**:
   - **PM承認結果**: 100件程度で十分。
   - 台帳の1レコードについての変更履歴であるため、1000件はユーザーシナリオ上発生しない。

4. **性能測定の実装**:
   - **PM承認結果**: 測定できるよう実装すること。
   - `docs/operation` のモニタリング手法に従う。

5. **キーボードショートカット**:
   - **判断保留**: `Ctrl+D` による差分ON/OFF切替等は、Phase 1では実装せず、ユーザーフィードバックを見て Phase 2 で検討。

---

## 6. 次のアクション

1. **PMによる確認**:
   - 性能目標の承認
   - 遅延ロード・ページング方式の決定
   - キーボードショートカットの要否

2. **W3-1.1（共通差分コンポーネントIF設計）への引き継ぎ**:
   - 本ドキュメントで定義した性能・アクセシビリティ要件を反映した詳細設計に進む。
