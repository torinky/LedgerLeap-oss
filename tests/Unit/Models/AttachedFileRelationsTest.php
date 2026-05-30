<?php

namespace Tests\Unit\Models;

use App\Models\AttachedFile;
use App\Models\User;
use PHPUnit\Framework\Attributes\Test;
use Tests\TestCase;
use Tests\Traits\RefreshDatabaseWithTenant;

class AttachedFileRelationsTest extends TestCase
{
    use RefreshDatabaseWithTenant;

    protected function setUp(): void
    {
        parent::setUp();
        $this->setUpRefreshDatabaseWithTenant();
    }

    #[Test]
    public function it_belongs_to_creator()
    {
        $creator = User::factory()->create();
        $file = AttachedFile::factory()->create([
            'creator_id' => $creator->id,
        ]);

        $this->assertInstanceOf(User::class, $file->creator);
        $this->assertEquals($creator->id, $file->creator->id);
    }

    #[Test]
    public function it_belongs_to_modifier()
    {
        $modifier = User::factory()->create();
        $file = AttachedFile::factory()->create([
            'modifier_id' => $modifier->id,
        ]);

        $this->assertInstanceOf(User::class, $file->modifier);
        $this->assertEquals($modifier->id, $file->modifier->id);
    }

    #[Test]
    public function it_has_many_activities()
    {
        $file = AttachedFile::factory()->create();

        // アクティビティログを記録
        activity()
            ->performedOn($file)
            ->withProperties(['action' => 'uploaded'])
            ->log('File uploaded');

        $this->assertCount(1, $file->activities);
        $this->assertEquals('File uploaded', $file->activities->first()->description);
    }
}
