# #232 バージョン表示とCHANGELOG導線 振り返り

**status:** complete
**last_updated_at:** 2026-05-31
**related_issue:** #232
**related_docs:** `docs/work/2026-05-30_versioning-strategy.md`

## 概要

アプリケーションのバージョン情報をフッター/プロフィールメニューに表示し、
#231 の CalVer git tag 運用と自動連動させる 6 スプリントの機能実装。
`git describe --tags` をプライマリ解決とし、APP_VERSION env / .version ファイルをフォールバックとする。

## 何がうまくいったか

### 1. 解決ロジックを Helper に分離したこと
当初 `config/ledgerleap.php` にインラインで `shell_exec` を含めようとしたが、
`config()->offsetUnset()` では config ファイルの再評価が行われずテスト不可能と判明。
`App\Helpers\Version::resolve()` に抽出したことで、Unit テストで全フォールバックパターンをテストできた。

### 2. 4段階フォールバックの設計
`APP_VERSION` env → `.version` ファイル → `git describe --tags` → `'0.0.0'` と
環境に応じて適切な解決方法が選ばれる設計により、開発/CI/本番の全環境をカバーできた。

### 3. release.yml の安全設計
ブランチ判定不能時は `exit 0` でスキップし、ワークフロー全体を失敗させない。
`git pull --ff-only` で競合を防止。

## 何が手戻りを生んだか

### 1. イシュー本文の情報ロス
スプリントを進める中でイシュー本文を段階的に更新した結果、
背景・仕様詳細・スプリント計画が消え、チェックリストだけが残る状態になった。
→ 本文に全計画を復元し、進捗はコメントで管理する方式に修正。

### 2. `putenv()` 後の config 再評価不可
`putenv('APP_VERSION=v2026.2.0')` の後に `config('ledgerleap.version')` を呼んでも
元の値が返る問題に遭遇。config ファイルは bootstrap 時に一度だけ解析されるため。
→ Helper 抽出で解決。

## 上書き指示されたこと

- CHANGELOG は単なる変更記録ではなく、バージョンリンクからのランディングページであることを
  意識し、ペルソナ別の導線を確保するよう設計変更
- イシュー本文は情報量を落とさず、進捗はコメントで管理する方式に修正
- スプリント完了ごとにテストエビデンス付きでコメント報告

## 再利用可能パターン

| パターン | 適用先 | 反映済み |
|---------|--------|---------|
| config 値解決ロジックの Helper 抽出 | `php-laravel.instructions.md` | ✅ |
| config/env テスト制約 | `tests.instructions.md` | ✅ |
| ペルソナ別 CHANGELOG 記述規則 | `release-workflow` スキル・ランブック | ✅ |
| `.version` ファイルによる CI→デプロイ連携 | `release.yml` | ✅ |

## 技術要素の振り返り

### config 値のテスト
- **問題**: `putenv()` も `offsetUnset()` も config 再評価をトリガーしない
- **解決**: 解決ロジックを `App\Helpers\Version` に抽出し、直接テスト
- **ガードレール**: `php-laravel.instructions.md` に追加、`tests.instructions.md` に制約を追加

### git describe のテスト
- `git tag v-test && sail tinker && git tag -d v-test` で安全に検証可能
- テスト環境では git tag が存在しない可能性があるため、フォールバックのテストが重要

## プロセス要素の振り返り

### イシュー管理
- 本文: 計画・仕様のソースオブトゥルース（不変に近く保つ）
- コメント: 進捗・エビデンスの時系列記録
- チェックリスト: 本文内で進捗を反映（エビデンスはコメントで補完）
