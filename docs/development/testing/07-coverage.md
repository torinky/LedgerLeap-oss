# カバレッジ測定

**最終更新:** 2026-03-08
**元ドキュメント:** Testing-Best-Practices.md（2026-02-22版）より分割

---

## カバレッジ測定コマンド

```bash
# 全テストのカバレッジを HTML レポートで生成（基準レポート・直列）
./vendor/bin/sail composer test:coverage

# CI に近い Unit カバレッジ（直列）
./vendor/bin/sail composer test:coverage:unit

# parallel-safe な Unit だけを並列でカバレッジ測定
./vendor/bin/sail composer test:coverage:unit:parallel

# parallel canary 相当の Feature subset を並列でカバレッジ測定
./vendor/bin/sail composer test:coverage:feature-subset:parallel

# 特定ディレクトリのカバレッジ
./vendor/bin/sail pest tests/Unit/Rules --coverage

# 特定ファイルのカバレッジ
./vendor/bin/sail pest tests/Unit/Services/PermissionServiceTest.php --coverage

# 最小カバレッジ率を指定（達成できない場合は失敗）
./vendor/bin/sail pest tests/Unit/Rules --coverage --min=80

# HTML レポート生成（coverage/index.html に出力）
./vendor/bin/sail pest tests/Unit/Rules --coverage-html=coverage
open coverage/index.html  # macOS
```

### 使い分け

- `test:full`: カバレッジなしの全体入口。まず全体を通したいときはこちらを使う。
- `test:coverage`: 全体の基準レポート。時間はかかるが最も単純。
- `test:coverage:unit`: Unit のみを CI 条件に寄せて確認。
- `test:coverage:unit:parallel`: 日常の高速確認向け。PCOV を有効化して並列実行。
- `test:coverage:feature-subset:parallel`: `FeatureParallelSubset` のみ対象。`database-migrations` は含めない。`phpunit.parallel.xml` を使用。

---

## カバレッジレポートの読み方

### コンソール出力

```
Rules/RequiredCheckbox ............................................. 22 / 75.0%
Rules/UniqueAutoNumber ............................................. 89 / 96.4%
Rules/UniqueColumnValue .................................. 92..115 / 63.6%
Rules/ValidAutoLinkPattern ......................................... 100.0%
```

- `75.0%`: カバレッジ率
- `22`: カバーされていない行番号
- `92..115`: カバーされていない行範囲

### HTML レポート

- **緑色**: カバーされたコード
- **赤色**: カバーされていないコード
- **黄色**: 部分的にカバー（条件分岐の一部のみ）

---

## カバレッジ率の目標値

| コンポーネント | 目標 | 理由 |
|:---|---:|:---|
| **Casts** | 100% | データ破損防止の最重要ロジック |
| **Rules** | 95%以上 | バリデーションは高精度が必須 |
| **Services（Core）** | 80%以上 | ビジネスロジックの信頼性確保 |
| **Services（Support）** | 70%以上 | 補助的なサービス |
| **Controllers** | 50%以上 | 統合テストでカバー可能 |
| **Livewire** | 60%以上 | UI ロジックの動作確認 |

---

## Phase 1 の実測カバレッジ結果（2026-02-15）

### Rules（目標: 95%以上）

| ファイル | カバレッジ率 | 評価 |
|:---|---:|:---:|
| RequiredCheckbox | 75.0% | ⚠️ |
| UniqueAutoNumber | 96.4% | ✅ |
| UniqueColumnValue | 63.6% | ⚠️ |
| ValidAutoLinkPattern | 100% | ✅ |

**総合:** 83.8%（目標95%未達）

### Services（目標: PermissionService 80%、NotificationService 70%）

| ファイル | カバレッジ率 | 評価 |
|:---|---:|:---:|
| PermissionService | 10.8% | ❌ |
| NotificationService | 26.0% | ❌ |

**Phase 2 以降の方針:**
- PermissionService: フォルダ階層権限・キャッシュ無効化のテスト追加
- NotificationService: 通知配信ロジック・ユーザー/ロール別通知のテスト追加

---

## 段階的なカバレッジ向上

**Phase 1**: 基本スモークテスト（10-30%）

```bash
./vendor/bin/sail pest tests/Unit/Services --coverage --min=10
```

**Phase 2**: 主要パスのテスト（50-70%）

```bash
./vendor/bin/sail pest tests/Unit/Services --coverage --min=50
```

**Phase 3**: エッジケースのテスト（80-95%）

```bash
./vendor/bin/sail pest tests/Unit/Services --coverage --min=80
```

---

## Mutation Testing との併用

カバレッジ率が高くてもテストの質が低い場合がある。Mutation Testing で確認：

```bash
./vendor/bin/sail composer test:mutation -- \
  --filter=app/Rules/UniqueAutoNumber.php \
  --test-framework-options="--filter=UniqueAutoNumber" \
  --map-source-class-to-test
```

**MSI（Mutation Score Indicator）の目標:**
- Casts: 80%以上
- Rules: 85%以上
- Services: 75%以上

---

## トラブルシューティング

### カバレッジが 0% になる

**原因:** Xdebug / PCOV が有効になっていない

```bash
./vendor/bin/sail php -m | grep -Ei 'xdebug|pcov'
./vendor/bin/sail down && ./vendor/bin/sail up -d
```

### 並列カバレッジを全件で回したくなる

LedgerLeap では `database-migrations` 系テストを通常の並列実行に混ぜない。
まずは `test:coverage:unit:parallel` または `test:coverage:feature-subset:parallel` を使うこと。

### `#[Computed]` プロパティのカバレッジが 0% になる

`assertStatus(200)` だけではメソッドが実行されない。
`instance()` 経由で直接呼び出すこと。詳細は [05-livewire.md](./05-livewire.md) を参照。

### 特定のファイルが表示されない

そのファイルを対象とする `#[CoversClass]` アトリビュートがテストに付いているか確認。
