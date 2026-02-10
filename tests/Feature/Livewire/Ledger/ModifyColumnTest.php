<?php

namespace Tests\Feature\Livewire\Ledger;

use App\Livewire\Ledger\ModifyColumn;
use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Bus;
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

        // 権限設定
        $role = \App\Models\Role::firstOrCreate(['name' => 'test-editor-role', 'guard_name' => 'web']);
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'view_ledgers', 'guard_name' => 'web']));
        $role->givePermissionTo(\Spatie\Permission\Models\Permission::firstOrCreate(['name' => 'update_ledgers', 'guard_name' => 'web']));
        $this->user->assignRole($role);

        // ファイルカラムを持つ台帳定義を作成
        $fileColumn = new ColumnDefine((object) [
            'id' => 0, // 1から0に変更
            'name' => 'Attachment',
            'type' => 'files',
            'order' => 1,
            'required' => false,
            'unique' => false,
            'options' => [],
            'group' => 'Files',
            'file' => null,
            'sort_index' => null,
        ]);

        $this->ledgerDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [$fileColumn],
        ]);

        // フォルダ権限設定
        if ($this->ledgerDefine->folder) {
            \App\Models\RoleFolderPermission::create([
                'role_id' => $role->id,
                'folder_id' => $this->ledgerDefine->folder_id,
                'permission' => \App\Enums\FolderPermissionType::WRITE,
                'modifier_id' => $this->user->id,
            ]);
        }
    }

    protected function assignFolderPermission(\App\Models\Folder $folder): void
    {
        $role = \App\Models\Role::findByName('test-editor-role', 'web');
        \App\Models\RoleFolderPermission::create([
            'role_id' => $role->id,
            'folder_id' => $folder->id,
            'permission' => \App\Enums\FolderPermissionType::WRITE,
            'modifier_id' => $this->user->id,
        ]);
    }

    #[Test]
    public function it_correctly_prepares_initial_files_for_filepond()
    {
        // ストレージをフェイク
        Storage::fake('public');

        // 準備 (Arrange)
        tenancy()->initialize($this->tenant);
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
            'mime' => 'image/jpeg', // MIMEタイプを明示的に設定
            'tenant_id' => $this->tenant->id,
        ]);

        // 実行 (Act) & 検証 (Assert)
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
    public function it_correctly_sets_icon_flag_for_non_image_files()
    {
        // ストレージをフェイク
        Storage::fake('public');

        // 準備 (Arrange)
        tenancy()->initialize($this->tenant);
        $hashedBasename = 'test_document.pdf';
        $originalFilename = 'test_document.pdf';

        // ダミーPDFファイルを作成
        $dummyFile = UploadedFile::fake()->create($originalFilename, 100, 'application/pdf');
        $path = \App\Helpers\AttachedFilePathHelper::getAttachmentPath($this->ledgerDefine->id, $hashedBasename);
        Storage::disk('public')->put($path, $dummyFile->get());

        // 添付ファイル情報を持つLedgerレコードを作成
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => [
                    $hashedBasename => $originalFilename,
                ],
            ],
        ]);

        // Ledgerに紐づくAttachedFileレコードを作成（非画像ファイル）
        $attachedFile = AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $hashedBasename,
            'filename' => $originalFilename,
            'column_id' => 0,
            'path' => $path,
            'mime' => 'application/pdf',
            'tenant_id' => $this->tenant->id,
        ]);

        // 実行 (Act)
        $livewireTest = Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        $livewireTest->set('tenantId', $this->tenant->id);

        // 検証 (Assert)
        $filePondFiles = $livewireTest->get('filePondInitialFiles');
        $filesForColumn = $filePondFiles[0] ?? null;
        $this->assertNotNull($filesForColumn);
        $this->assertCount(1, $filesForColumn);

        $firstFile = $filesForColumn[0] ?? null;
        $this->assertNotNull($firstFile);

        // 1. is_iconフラグがtrueであることを確認（非画像ファイル）
        $this->assertArrayHasKey('is_icon', $firstFile['options']['metadata']);
        $this->assertTrue($firstFile['options']['metadata']['is_icon'], 'is_icon should be true for non-image files.');

        // 2. posterURLがアイコンAPIのルートを指していることを確認
        $expectedPosterUrl = route('api.fontawesome.icon.by_mime', ['type' => 'application/pdf']);
        $this->assertEquals($expectedPosterUrl, $firstFile['options']['metadata']['poster'], 'Poster URL should point to icon API for non-image files.');
    }

    #[Test]
    public function it_handles_multiple_column_types_correctly()
    {
        // ストレージをフェイク
        Storage::fake('public');

        // 準備 (Arrange)
        tenancy()->initialize($this->tenant);
        // 複数のカラム定義を持つLedgerDefineをセットアップ
        $multiColumnDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'TextField',
                    'type' => 'text',
                    'order' => 1,
                ]),
                new ColumnDefine((object) [
                    'id' => 1,
                    'name' => 'AttachmentField',
                    'type' => 'files',
                    'order' => 2,
                ]),
            ],
        ]);
        if ($multiColumnDefine->folder) {
            $this->assignFolderPermission($multiColumnDefine->folder);
        }

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
            'tenant_id' => $this->tenant->id,
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
        tenancy()->initialize($this->tenant);
        // カラムIDが飛んでいるLedgerDefineをセットアップ
        $sparseColumnDefine = LedgerDefine::factory()->create([
            'tenant_id' => $this->tenant->id,
            'column_define' => [
                new ColumnDefine((object) [
                    'id' => 0,
                    'name' => 'TextField',
                    'type' => 'text',
                    'order' => 1,
                ]),
                // ID 1 は意図的に欠番
                new ColumnDefine((object) [
                    'id' => 2,
                    'name' => 'AttachmentField',
                    'type' => 'files',
                    'order' => 2,
                ]),
            ],
        ]);
        if ($sparseColumnDefine->folder) {
            $this->assignFolderPermission($sparseColumnDefine->folder);
        }

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
            'tenant_id' => $this->tenant->id,
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

    #[Test]
    public function it_can_upload_new_files()
    {
        Bus::fake();
        Storage::fake('public');

        // 準備: ファイルを持たないLedgerを作成
        tenancy()->initialize($this->tenant);
        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => [], // ファイルカラムは空
            ],
        ]);

        // 新しいダミーファイルを準備
        $newFile = UploadedFile::fake()->image('new_upload.jpg');

        // 実行 & 検証
        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->set('content.0', [$newFile]) // 新しいファイルをセット
            ->call('saveDirectly');

        // DBにAttachedFileレコードが作成されたか確認
        $this->assertDatabaseHas('attached_files', [
            'ledger_id' => $ledger->id,
            'filename' => 'new_upload.jpg',
            'column_id' => 0,
            'tenant_id' => $this->tenant->id, // テナントIDを検証
        ]);

        // ストレージにファイルが保存されたか確認
        $attachedFile = AttachedFile::where('filename', 'new_upload.jpg')->first();
        $this->assertNotNull($attachedFile);
        Storage::disk('public')->assertExists($attachedFile->path);
    }

    #[Test]
    public function it_can_remove_existing_files()
    {
        Storage::fake('public');

        // 準備: 既存ファイルを持つLedgerを作成
        tenancy()->initialize($this->tenant);
        $originalFile = UploadedFile::fake()->image('original.jpg');
        $path = $originalFile->store('attachments', 'public');
        $hashedBasename = basename($path);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => [
                    $hashedBasename => 'original.jpg',
                ],
            ],
        ]);

        AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $hashedBasename,
            'filename' => 'original.jpg',
            'column_id' => 0,
            'path' => $path,
            'tenant_id' => $this->tenant->id,
        ]);

        // 実行 & 検証
        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->call('handleFileRemoval', 0, $hashedBasename) // ファイルを削除対象に
            ->call('saveDirectly');

        // DBからAttachedFileレコードが論理削除されたか確認
        $this->assertSoftDeleted('attached_files', [
            'hashedbasename' => $hashedBasename,
            'ledger_id' => $ledger->id,
        ]);

    }

    #[Test]
    public function it_can_add_and_remove_files_simultaneously()
    {
        Bus::fake();
        Storage::fake('public');

        // 準備: 既存ファイルを持つLedgerを作成
        tenancy()->initialize($this->tenant);
        $originalFile = UploadedFile::fake()->image('original_to_delete.jpg');
        $originalPath = $originalFile->store('attachments', 'public');
        $originalHashedBasename = basename($originalPath);

        $ledger = Ledger::factory()->create([
            'ledger_define_id' => $this->ledgerDefine->id,
            'tenant_id' => $this->tenant->id,
            'content' => [
                0 => [
                    $originalHashedBasename => 'original_to_delete.jpg',
                ],
            ],
        ]);

        AttachedFile::factory()->create([
            'ledger_id' => $ledger->id,
            'hashedbasename' => $originalHashedBasename,
            'filename' => 'original_to_delete.jpg',
            'column_id' => 0,
            'path' => $originalPath,
            'tenant_id' => $this->tenant->id,
        ]);

        // 新しいダミーファイルを準備
        $newFile = UploadedFile::fake()->image('new_upload_simultaneously.jpg');

        // 実行 & 検証
        Livewire::actingAs($this->user)
            ->test(ModifyColumn::class, ['ledgerId' => $ledger->id])
            ->call('handleFileRemoval', 0, $originalHashedBasename) // 既存ファイルを削除対象に
            ->set('content.0', [$newFile]) // 新しいファイルをセット
            ->call('saveDirectly');

        // 既存ファイルがDBから論理削除されたか確認
        $this->assertSoftDeleted('attached_files', [
            'hashedbasename' => $originalHashedBasename,
            'ledger_id' => $ledger->id,
        ]);

        // 新しいファイルがDBに作成されたか確認
        $this->assertDatabaseHas('attached_files', [
            'ledger_id' => $ledger->id,
            'filename' => 'new_upload_simultaneously.jpg',
            'column_id' => 0,
            'tenant_id' => $this->tenant->id,
        ]);

        // 新しいファイルがストレージに保存されたか確認
        $newAttachedFile = AttachedFile::where('filename', 'new_upload_simultaneously.jpg')->first();
        $this->assertNotNull($newAttachedFile);
        Storage::disk('public')->assertExists($newAttachedFile->path);
    }

    #[Test]
    public function it_displays_delete_button_for_existing_unlocked_ledger(): void
    {
        // 既存の台帳を作成
        $ledger = Ledger::factory()->create([
            'tenant_id' => $this->tenant->id,
            'ledger_define_id' => $this->ledgerDefine->id,
            'content' => [0 => []],
            'creator_id' => $this->user->id,
        ]);

        // コンポーネントをレンダリング
        $component = Livewire::test(ModifyColumn::class, ['ledgerId' => $ledger->id]);

        // 削除ボタンが表示されていることを確認
        $component->assertSee(__('ledger.delete'));
        $component->assertSeeHtml('for="delete-modal"');

        // 削除モーダルも存在することを確認
        $component->assertSeeHtml('id="delete-modal"');
    }
}
