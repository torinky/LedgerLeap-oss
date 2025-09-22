<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\ModifyColumn;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

class ModifyColumnTest extends TestCase
{
    use RefreshDatabase;

    protected Tenant $tenant;
    protected User $user;
    protected LedgerDefine $ledgerDefine;

    protected function setUp(): void
    {
        parent::setUp();

        $this->tenant = Tenant::factory()->create();
        $this->user = User::factory()->create();

        // ユーザーを認証し、テナンシーを初期化
        $this->actingAs($this->user);
        tenancy()->initialize($this->tenant);

        // ファイルカラムを持つ台帳定義を作成
        $fileColumn = new ColumnDefine((object)[
            'id' => 0, // 1から0に変更
            'name' => 'Attachment',
            'type' => 'files',
            'order' => 1,
            'required' => false,
            'unique' => false,
            'options' => [],
            'group' => 'Files',
            'file' => null,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [$fileColumn],
        ]);
    }

    #[Test]
    public function it_correctly_prepares_initial_files_for_filepond()
    {
        // ストレージをフェイク
        Storage::fake('public');

        // 準備 (Arrange)
        $hashedBasename = 'test_hashed_basename.jpg';
        $originalFilename = 'test_original_filename.jpg';

        // ダミーファイルを作成
        $dummyFile = UploadedFile::fake()->image($originalFilename);
        $path = \App\Helpers\AttachedFilePathHelper::getAttachmentPath($this->ledgerDefine->id, $hashedBasename);
        Storage::disk('public')->put($path, $dummyFile->get());

        // 添付ファイル情報を持つLedgerレコードを作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => [ // 数値インデックス 0 をキーとする
                    $hashedBasename => $originalFilename,
                ],
            ],
        ]);

        // Ledgerに紐づくAttachedFileレコードを作成
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $hashedBasename,
            'filename' => $originalFilename,
            'column_id' => 0, // 1から0に変更
            'path' => $path, // 生成したパスを設定
        ]);

        // 実行 (Act) & 検証 (Assert)
        tenancy()->initialize($this->tenant); // 追加
        $livewireTest = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        $livewireTest->set('tenantId', $this->tenant->id); // 追加

        // 1. トレイトによってtenantIdが正しくセットされているか確認
        $livewireTest->assertSet('tenantId', $this->tenant->id);

        // 2. filePondInitialFilesが空でないことを確認
        $this->assertNotEmpty($livewireTest->get('filePondInitialFiles'));

        // 3. filePondInitialFilesの中身を詳細に検証
        $filePondFiles = $livewireTest->get('filePondInitialFiles');
        $this->assertIsArray($filePondFiles);

        // 該当カラムのファイル情報を取得
        $filesForColumn = $filePondFiles[0] ?? null; // 数値インデックス 0 をキーとする
        $this->assertNotNull($filesForColumn, 'Files for column not found in filePondInitialFiles.');
        $this->assertCount(1, $filesForColumn);

        // 最初のファイルを取得
        $firstFile = $filesForColumn[0] ?? null;
        $this->assertNotNull($firstFile, 'File object not found.');

        // 4. sourceとposterのURLが期待通りか厳密に検証
        $expectedSourceUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $attachedFile->id]);
        $expectedPosterUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $attachedFile->id, 'thumbnail' => true]);

        $this->assertEquals($expectedSourceUrl, $firstFile['source'], 'The file source URL is incorrect.');
        $this->assertEquals($expectedPosterUrl, $firstFile['options']['metadata']['poster'], 'The file poster URL is incorrect.');
    }

    #[Test]
    public function it_handles_multiple_column_types_correctly()
    {
        // ストレージをフェイク
        Storage::fake('public');

        // 準備 (Arrange)
        // 複数のカラム定義を持つLedgerDefineをセットアップ
        $multiColumnDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine((object)[
                    'id' => 0,
                    'name' => 'TextField',
                    'type' => 'text',
                    'order' => 1,
                ]),
                new ColumnDefine((object)[
                    'id' => 1,
                    'name' => 'AttachmentField',
                    'type' => 'files',
                    'order' => 2,
                ]),
            ],
        ]);

        $hashedBasename = 'multi_col_test.jpg';
        $originalFilename = 'multi_col_test.jpg';

        // ダミーファイルを作成
        $dummyFile = UploadedFile::fake()->image($originalFilename);
        $path = \App\Helpers\AttachedFilePathHelper::getAttachmentPath($multiColumnDefine->id, $hashedBasename);
        Storage::disk('public')->put($path, $dummyFile->get());

        // 複数のカラムに対応するcontentを持つLedgerレコードを作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $multiColumnDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => 'Some text value', // textカラムのデータ
                1 => [ // filesカラムのデータ
                    $hashedBasename => $originalFilename,
                ],
            ],
        ]);

        // filesカラムに紐づくAttachedFileレコードを作成
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $hashedBasename,
            'filename' => $originalFilename,
            'column_id' => 1, // filesカラムのID
            'path' => $path,
        ]);

        // 実行 (Act) & 検証 (Assert)
        $livewireTest = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // filePondInitialFilesの中身を検証
        $filePondFiles = $livewireTest->get('filePondInitialFiles');

        // 1. textカラム(ID:0)に対応するエントリは存在しないはず
        $this->assertArrayNotHasKey(0, $filePondFiles, 'FilePond data should not be generated for text columns.');

        // 2. filesカラム(ID:1)に対応するエントリは存在するはず
        $this->assertArrayHasKey(1, $filePondFiles, 'FilePond data should be generated for files columns.');
        $this->assertCount(1, $filePondFiles[1]);

        // 3. ファイル情報が正しいことを確認
        $fileData = $filePondFiles[1][0];
        $expectedSourceUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $attachedFile->id]);
        $this->assertEquals($expectedSourceUrl, $fileData['source']);
    }

    #[Test]
    public function it_handles_sparse_column_ids_correctly()
    {
        // ストレージをフェイク
        Storage::fake('public');

        // 準備 (Arrange)
        // カラムIDが飛んでいるLedgerDefineをセットアップ
        $sparseColumnDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine((object)[
                    'id' => 0,
                    'name' => 'TextField',
                    'type' => 'text',
                    'order' => 1,
                ]),
                // ID 1 は意図的に欠番
                new ColumnDefine((object)[
                    'id' => 2,
                    'name' => 'AttachmentField',
                    'type' => 'files',
                    'order' => 2,
                ]),
            ],
        ]);

        $hashedBasename = 'sparse_col_test.jpg';
        $originalFilename = 'sparse_col_test.jpg';

        // ダミーファイルを作成
        $dummyFile = UploadedFile::fake()->image($originalFilename);
        $path = \App\Helpers\AttachedFilePathHelper::getAttachmentPath($sparseColumnDefine->id, $hashedBasename);
        Storage::disk('public')->put($path, $dummyFile->get());

        // 疎なcontentを持つLedgerレコードを作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $sparseColumnDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => 'Some text value', // textカラムのデータ
                1 => null, // 欠番のカラムに対応する空の要素
                2 => [ // filesカラムのデータ
                    $hashedBasename => $originalFilename,
                ],
            ],
        ]);

        // filesカラムに紐づくAttachedFileレコードを作成
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $hashedBasename,
            'filename' => $originalFilename,
            'column_id' => 2, // filesカラムのID
            'path' => $path,
        ]);

        // 実行 (Act) & 検証 (Assert)
        $livewireTest = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // filePondInitialFilesの中身を検証
        $filePondFiles = $livewireTest->get('filePondInitialFiles');

        // 1. textカラム(ID:0)と空のカラム(ID:1)に対応するエントリは存在しないはず
        $this->assertArrayNotHasKey(0, $filePondFiles);
        $this->assertArrayNotHasKey(1, $filePondFiles);

        // 2. filesカラム(ID:2)に対応するエントリは存在するはず
        $this->assertArrayHasKey(2, $filePondFiles);
        $this->assertCount(1, $filePondFiles[2]);

        // 3. ファイル情報が正しいことを確認
        $fileData = $filePondFiles[2][0];
        $expectedSourceUrl = route('file.download', ['tenant' => $this->tenant->id, 'attachedFile' => $attachedFile->id]);
        $this->assertEquals($expectedSourceUrl, $fileData['source']);
    }
}
