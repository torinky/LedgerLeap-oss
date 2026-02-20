<?php

namespace Tests\Unit\Modules;

use App\Modules\ImageUpload\LocalImageManager;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;

/**
 * LocalImageManager のユニットテスト
 *
 * Phase 2 Sprint 1: Modules/ImageUpload のカバレッジ向上
 *
 * @see app/Modules/ImageUpload/LocalImageManager.php
 */
class LocalImageManagerTest extends TestCase
{
    protected LocalImageManager $manager;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->manager = new LocalImageManager;
    }

    #[Test]
    public function save_stores_file_and_returns_filename(): void
    {
        $file = UploadedFile::fake()->image('test.jpg');

        $filename = $this->manager->save($file);

        $this->assertNotEmpty($filename);
        $this->assertIsString($filename);
        // Storage::fake によりファイルが保存されたことを確認
        Storage::assertExists('public/images/'.$filename);
    }

    #[Test]
    public function save_returns_only_filename_not_full_path(): void
    {
        $file = UploadedFile::fake()->image('photo.png');

        $filename = $this->manager->save($file);

        // ファイル名のみが返却される（パス区切り文字を含まない）
        $this->assertStringNotContainsString('/', $filename);
    }

    #[Test]
    public function delete_removes_existing_file(): void
    {
        // 事前にファイルを配置
        Storage::put('public/images/test-delete.jpg', 'dummy content');
        Storage::assertExists('public/images/test-delete.jpg');

        $this->manager->delete('test-delete.jpg');

        Storage::assertMissing('public/images/test-delete.jpg');
    }

    #[Test]
    public function delete_does_not_throw_when_file_not_exists(): void
    {
        // ファイルが存在しない状態で削除しても例外が発生しない
        $this->expectNotToPerformAssertions();
        $this->manager->delete('nonexistent-file.jpg');
    }

    #[Test]
    public function delete_only_removes_target_file(): void
    {
        // 複数ファイルを配置
        Storage::put('public/images/file-a.jpg', 'content a');
        Storage::put('public/images/file-b.jpg', 'content b');

        $this->manager->delete('file-a.jpg');

        Storage::assertMissing('public/images/file-a.jpg');
        Storage::assertExists('public/images/file-b.jpg');
    }
}

