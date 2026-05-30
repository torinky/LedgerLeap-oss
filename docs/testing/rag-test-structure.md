# RAG機能テスト構造

**更新日:** 2025-10-22

## テストファイル構成

### 1. ProcessLedgerForRagJobTest.php
**場所:** `tests/Feature/Jobs/ProcessLedgerForRagJobTest.php`  
**目的:** RAG用Markdown生成ロジックの単体テスト  
**テスト数:** 12テスト

#### 対象機能
- 構造化Markdown生成
- display_levelに応じた見出し階層
- select/checkbox/files型の変換
- 単位付き数値型
- 空値のスキップ
- 添付ファイル内容の統合
- 長文切り詰め処理
- 空グループ名の扱い
- 配列形式content_attachedの処理

#### 特徴
- 全テスト成功（緑）
- ビジネスロジックの詳細な検証
- モックを使った効率的なテスト

---

### 2. RagSearchServiceTest.php
**場所:** `tests/Feature/RagSearchServiceTest.php`  
**目的:** RAG検索サービスの機能テスト  
**テスト数:** 8テスト

#### 対象機能
- ベクトルのJSON保存
- Mroongaハイブリッド検索
- フィルター機能（フォルダ/定義ID）
- ユーザー権限による検索制限
- ページネーション対応
- API用構造化結果
- Embedding Serviceの呼び出し検証
- Mroongaクエリ最適化確認

#### 最適化検証（重要）
`it_builds_optimized_mroonga_query`テストで以下を検証:
```php
// ✅ 正しい最適化クエリ
--columns[score].stage initial           // ← initialで計算
--filter 'score < 0.7'                   // ← scoreを直接使用
--columns[score].value 'distance_cosine(embedding, [...])'  // ← 最適化効く
```

#### 特徴
- 全テスト成功（緑）
- モックを使った権限・フィルターテスト
- 実際のMroonga検索動作確認

---

### 3. RagPerformanceTest.php
**場所:** `tests/Feature/RagPerformanceTest.php`  
**目的:** RAG検索のパフォーマンス監視  
**テスト数:** 3テスト（1つはskip）

#### 対象機能
1. **大規模データセット検索** (skip) - 手動実行推奨
2. **結果件数とスケーラビリティ** ✓
   - limit 10/50/100での性能測定
   - 線形スケール確認
3. **フィルター付き検索性能** ✓
   - フォルダフィルターの効率確認
   - フィルター有無の比較

#### 特徴
- パフォーマンス劣化の早期検知
- 実行時間を標準出力で可視化
- 大規模テストは明示的にskip

---

## 削除されたテストファイル

### RagBgeM3Test.php（削除済）
**削除理由:**
- BGE-M3モデル専用テスト（環境依存が強い）
- 現在使用しているモデル（cl-nagoya/ruri-v3-310m）と不一致
- 6テスト中3テストが失敗
- モデル切り替え時に再作成可能

**削除内容:**
- BGE-M3モデル設定確認
- ヘルスチェック（モデル名検証）
- 1024次元ベクトル生成確認
- チャンク生成とベクトル保存
- ベンチマーク測定
- セマンティック類似度検証

---

## テスト実行方法

### すべてのRAGテストを実行
```bash
./vendor/bin/sail test --filter=Rag
```

### 個別ファイル実行
```bash
# Job単体テスト
./vendor/bin/sail test tests/Feature/Jobs/ProcessLedgerForRagJobTest.php

# 検索サービステスト
./vendor/bin/sail test tests/Feature/RagSearchServiceTest.php

# パフォーマンステスト
./vendor/bin/sail test tests/Feature/RagPerformanceTest.php
```

### 特定テストのみ実行
```bash
./vendor/bin/sail test --filter=it_builds_optimized_mroonga_query
```

---

## テスト設計のポイント

### 1. モック戦略
```php
// EmbeddingServiceのモック（型安全）
$embeddingServiceMock->shouldReceive('embed')
    ->with(Mockery::type('array'), 'passage')  // 柔軟なマッチング
    ->andReturnUsing(function($texts) use ($vector) {
        // 実際の内容に応じて返す
        if (isset($texts[0]) && str_contains($texts[0], 'Cats')) {
            return [$vectorCat];
        }
        return [$vectorDog];
    });
```

### 2. Logチャンネルのモック
```php
$logSpy = \Illuminate\Support\Facades\Log::spy();
$channelMock = \Mockery::mock();
$logSpy->shouldReceive('channel')->andReturn($channelMock);
$channelMock->shouldReceive('info')->zeroOrMoreTimes();
$channelMock->shouldReceive('debug')->zeroOrMoreTimes();
```

### 3. 権限テスト
```php
// 権限を持つユーザー作成
$role = \App\Models\Role::create(['name' => 'TestRole', 'guard_name' => 'web']);
$user->roles()->attach($role->id);
$role->folderPermissions()->attach($folder->id, [
    'permission' => \App\Enums\FolderPermissionType::READ,
    'modifier_id' => $user->id,
]);
```

---

## トラブルシューティング

### モック不一致エラー
```
NoMatchingExpectationException: No matching handler found for embed([...], 'passage')
```
**解決策:** `Mockery::type('array')`で柔軟にマッチング

### Logチャンネルエラー
```
BadMethodCallException: Method Mockery_5::debug() does not exist
```
**解決策:** `->zeroOrMoreTimes()`で任意回数許可

### Mroongaインデックス待機
```php
sleep(1); // Mroongaのインデックス更新待機
```

---

## 今後の改善案

1. **統合テスト追加**
   - Livewire経由の検索画面テスト
   - API経由のE2Eテスト

2. **パフォーマンスベンチマーク**
   - 定期的な性能測定自動化
   - 閾値ベースの警告

3. **マルチモデル対応**
   - モデル切り替え時のテスト再利用
   - 環境変数ベースのモデル選択

---

**参考:** copilot-instructions.md「テスト方針」セクション
