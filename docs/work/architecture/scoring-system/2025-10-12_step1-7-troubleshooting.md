# Step 1.7: トラブルシューティングガイド

**作成日:** 2025-10-12  
**目的:** スコア表示が正しく動作しない場合の確認手順

---

## 問題: スコアが表示されない / スコアが0のまま

### 原因1: スコアが計算されていない ✅ 解決済み

**確認方法:**
```bash
./vendor/bin/sail artisan tinker --execute="echo \App\Models\Ledger::select('id', 'composite_score')->orderBy('composite_score', 'desc')->limit(5)->get()->toJson(JSON_PRETTY_PRINT);"
```

**解決策:**
```bash
./vendor/bin/sail artisan scoring:calculate
```

**実行結果（確認済み）:**
- 27件の台帳のスコアが正常に計算されました
- 最高スコア: 37.2040
- スコア範囲: 33.5～37.2

---

### 原因2: ブラウザキャッシュ

**解決策:**
1. **ハードリフレッシュ**
   - Mac: `Cmd + Shift + R`
   - Windows/Linux: `Ctrl + Shift + R`

2. **ブラウザキャッシュのクリア**
   - Chrome: 設定 > プライバシーとセキュリティ > 閲覧履歴データの削除
   - キャッシュされた画像とファイルのみ削除

3. **シークレットモード/プライベートブラウジングで確認**
   - 新しいシークレットウィンドウで http://localhost にアクセス

---

### 原因3: Viteアセットが古い ✅ 解決済み

**確認方法:**
```bash
ls -lh public/build/manifest.json
```

**解決策:**
```bash
./vendor/bin/sail npm run build
```

**実行結果（確認済み）:**
- アセットが正常にリビルドされました
- manifest.json: 2.86 kB

---

### 原因4: Livewireキャッシュ

**解決策:**
```bash
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
```

---

## 確認手順（推奨順）

### ステップ1: データベース確認 ✅
```bash
./vendor/bin/sail artisan tinker --execute="
echo 'Total ledgers: ' . \App\Models\Ledger::count() . PHP_EOL;
echo 'Ledgers with score > 0: ' . \App\Models\Ledger::where('composite_score', '>', 0)->count() . PHP_EOL;
echo 'Average score: ' . \App\Models\Ledger::where('composite_score', '>', 0)->avg('composite_score') . PHP_EOL;
"
```

**期待される結果:**
```
Total ledgers: 27
Ledgers with score > 0: 27
Average score: 34.xxx
```

### ステップ2: UI要素確認
ブラウザの開発者ツール（F12）で以下を確認：

1. **ネットワークタブ**
   - Livewireのリクエストが正常に完了しているか
   - レスポンスに`composite_score`が含まれているか

2. **コンソールタブ**
   - JavaScriptエラーがないか

3. **要素の検証**
   - スコア列の`<th>`タグが存在するか
   ```html
   <th class="text-center px-4 py-2 tracking-wider bg-accent bg-opacity-30">
       <span class="text-sm font-bold">総合スコア</span>
   ```
   - スコア表示の`<td>`タグが存在するか
   ```html
   <td class="px-2 py-2 text-center border">
       <span class="badge badge-sm badge-primary">34.5</span>
   ```

### ステップ3: ブラウザキャッシュクリア
1. ハードリフレッシュ（Cmd+Shift+R / Ctrl+Shift+R）
2. シークレットモードで確認

### ステップ4: サーバーサイドキャッシュクリア
```bash
./vendor/bin/sail artisan view:clear
./vendor/bin/sail artisan cache:clear
./vendor/bin/sail artisan config:clear
```

---

## 期待される表示

### テーブルヘッダー
```
ID | 総合スコア | [カラム1] | [カラム2] | ... | 更新日時
```

### テーブル行
```
[編集] | 37.2 | データ1 | データ2 | ... | 2025-10-12 15:30:00
[詳細] |      |         |         |     |
```

### スコアバッジの色分け
- **70点以上**: 🟢 緑 (`badge-success`)
- **40-69点**: 🔵 青 (`badge-primary`)
- **20-39点**: 🔵 水色 (`badge-info`)
- **1-19点**: ⚪ グレー (`badge-ghost`)
- **0点**: `-` 表示

---

## デバッグコマンド集

### スコアの分布を確認
```bash
./vendor/bin/sail artisan tinker --execute="
\$ranges = [
    '70+' => \App\Models\Ledger::where('composite_score', '>=', 70)->count(),
    '40-69' => \App\Models\Ledger::whereBetween('composite_score', [40, 69.99])->count(),
    '20-39' => \App\Models\Ledger::whereBetween('composite_score', [20, 39.99])->count(),
    '1-19' => \App\Models\Ledger::whereBetween('composite_score', [0.01, 19.99])->count(),
    '0' => \App\Models\Ledger::where('composite_score', 0)->count(),
];
print_r(\$ranges);
"
```

### 特定の台帳のスコア詳細
```bash
./vendor/bin/sail artisan tinker --execute="
\$ledger = \App\Models\Ledger::find(14);
echo 'ID: ' . \$ledger->id . PHP_EOL;
echo 'Composite Score: ' . \$ledger->composite_score . PHP_EOL;
echo 'Updated At: ' . \$ledger->updated_at . PHP_EOL;
echo 'Workflow Status: ' . (\$ledger->status ? \$ledger->status->value : 'none') . PHP_EOL;
"
```

### コンポーネントの状態確認
```bash
./vendor/bin/sail artisan tinker --execute="
echo 'orderBy default: composite_score' . PHP_EOL;
echo 'Schema has composite_score: ' . (Schema::hasColumn('ledgers', 'composite_score') ? 'YES' : 'NO') . PHP_EOL;
"
```

---

## よくある質問

### Q1: スコアが計算されているのに表示されない
**A:** ブラウザのハードリフレッシュ（Cmd+Shift+R）を試してください。Livewireのキャッシュが原因の可能性があります。

### Q2: スコア列が表示されない
**A:** 以下を確認：
1. マイグレーションが実行されているか: `./vendor/bin/sail artisan migrate:status | grep scoring`
2. Viteがリビルドされているか: `./vendor/bin/sail npm run build`
3. ブラウザキャッシュをクリア

### Q3: 特定の台帳定義でのみ表示されない
**A:** 台帳定義ごとにテーブルが生成されます。該当の台帳定義を選択して、リストを展開しているか確認してください。

### Q4: スコアが0のまま
**A:** バッチコマンドを実行: `./vendor/bin/sail artisan scoring:calculate`

### Q5: ソートが効かない
**A:** 
1. スコア列のヘッダーにソートボタン（↑↓）が表示されているか確認
2. クリックで昇順・降順が切り替わるか確認
3. ブラウザコンソールでJavaScriptエラーを確認

---

## 既知の制限事項

1. **リアルタイム更新なし**: スコアは日次バッチ（午前3時）または手動実行で更新されます
2. **新規台帳のスコア**: 作成直後はスコア0で、最初のバッチ実行後に計算されます
3. **英語翻訳未対応**: 現在は日本語のみ対応（`lang/ja/ledger.php`）

---

## サポート情報

実装ファイル:
- `app/Livewire/Ledger/RecordsTable.php` - ソートロジック
- `resources/views/components/ledger/table-header.blade.php` - ヘッダー表示
- `resources/views/components/ledger/table-row.blade.php` - スコア表示
- `lang/ja/ledger.php` - 翻訳キー

テストファイル:
- `tests/Feature/Livewire/Ledger/RecordsTableCompositeScoreSortTest.php`

---

## 📝 関連ドキュメント

**作業ドキュメント:**
- [ハイブリッド型情報価値評価システム 実装計画](./2025-10-08_search-result-scoring-and-sorting-plan.md) - 親ドキュメント
- [Step 1.7 実装完了レポート](./2025-10-12_step1-7-implementation-complete.md) - 実装サマリー
- [Step 1.7 UI統合 詳細計画](./2025-10-12_step1-7-ui-integration-plan.md) - 詳細計画

**公式ドキュメント:**
- [スコアリングシステム（機能）](../../../features/scoring-system.md) - ユーザー向け説明
- [スコアリングシステム（開発者ガイド）](../../../development/scoring-system.md) - 開発者向け詳細

---

**作成日:** 2025年10月12日  
**最終更新:** 2025年10月12日  
**ステータス:** スコア計算済み、UI実装完了
