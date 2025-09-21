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
use Livewire\Livewire;
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
            'id' => 1, // 文字列から整数に変更
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

    /** @test */
    public function it_correctly_prepares_initial_files_for_filepond()
    {
        // 準備 (Arrange)
        $hashedBasename = 'test_hashed_basename.jpg';
        $originalFilename = 'test_original_filename.jpg';

        // 添付ファイル情報を持つLedgerレコードを作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                1 => [ // 文字列キーから整数キーに変更
                    $hashedBasename => $originalFilename,
                ],
            ],
        ]);

        // Ledgerに紐づくAttachedFileレコードを作成
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $hashedBasename,
            'filename' => $originalFilename,
            'column_id' => 1, // 文字列から整数に変更
        ]);

        // 実行 (Act) & 検証 (Assert)
        $livewireTest = Livewire::test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // 1. トレイトによってtenantIdが正しくセットされているか確認
        $livewireTest->assertSet('tenantId', $this->tenant->id);

        // 2. filePondInitialFilesが空でないことを確認
        $livewireTest->assertNotEmptied('filePondInitialFiles');

        // 3. filePondInitialFilesの中身を詳細に検証
        $filePondFiles = $livewireTest->get('filePondInitialFiles');
        $this->assertIsArray($filePondFiles);

        // 該当カラムのファイル情報を取得
        $filesForColumn = $filePondFiles[1] ?? null; // 文字列キーから整数キーに変更
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
}
