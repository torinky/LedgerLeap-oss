<?php

require 'vendor/autoload.php';

$app = require 'bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();

config(['ledgerleap.performance.log_destination' => 'none']);
Queue::fake();

use App\Models\AttachedFile;
use App\Models\ColumnDefine;
use App\Models\Folder;
use App\Models\Ledger;
use App\Models\LedgerDefine;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Ledger\ColumnHtmlService;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\App;
use Illuminate\Support\Facades\Queue;

$tenant = Tenant::query()->firstOrCreate(['id' => 'bench-tenant'], ['id' => 'bench-tenant']);
tenancy()->initialize($tenant);

$folder = Folder::factory()->create();
$user = User::factory()->create();
$ledgerDefine = LedgerDefine::factory()->create([
    'folder_id' => $folder->id,
    'column_define' => [
        [
            'id' => 10,
            'name' => '添付',
            'type' => 'files',
            'order' => 1,
            'options' => [],
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => null,
            'file' => [],
            'display_level' => 3,
            'group' => null,
        ],
    ],
]);

$ledger = Ledger::factory()->create([
    'ledger_define_id' => $ledgerDefine->id,
    'tenant_id' => $tenant->id,
    'creator_id' => $user->id,
    'modifier_id' => $user->id,
    'content' => [10 => [
        'bench-a' => 'invoice-a.pdf',
        'bench-b' => 'image-b.png',
        'bench-c' => 'notes-c.txt',
        'bench-d' => 'spec-d.docx',
    ]],
    'content_attached' => [10 => []],
]);
$ledger->load('define');

$attachments = collect([
    AttachedFile::factory()->create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $ledgerDefine->id,
        'column_id' => 10,
        'tenant_id' => $tenant->id,
        'filename' => 'bench-a.pdf',
        'hashedbasename' => 'bench-a',
        'original_mime_type' => 'application/pdf',
        'mime' => 'application/pdf',
        'status' => 'completed',
        'optimized' => true,
    ]),
    AttachedFile::factory()->create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $ledgerDefine->id,
        'column_id' => 10,
        'tenant_id' => $tenant->id,
        'filename' => 'bench-b.png',
        'hashedbasename' => 'bench-b',
        'original_mime_type' => 'image/png',
        'mime' => 'image/png',
        'status' => 'completed',
        'optimized' => true,
    ]),
    AttachedFile::factory()->create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $ledgerDefine->id,
        'column_id' => 10,
        'tenant_id' => $tenant->id,
        'filename' => 'bench-c.txt',
        'hashedbasename' => 'bench-c',
        'original_mime_type' => 'text/plain',
        'mime' => 'text/plain',
        'status' => 'completed',
        'optimized' => true,
    ]),
    AttachedFile::factory()->create([
        'ledger_id' => $ledger->id,
        'ledger_define_id' => $ledgerDefine->id,
        'column_id' => 10,
        'tenant_id' => $tenant->id,
        'filename' => 'bench-d.docx',
        'hashedbasename' => 'bench-d',
        'original_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => 'completed',
        'optimized' => true,
    ]),
])->keyBy('hashedbasename');

$service = App::make(ColumnHtmlService::class);
$columnDefine = new ColumnDefine([
    'id' => 10,
    'name' => '添付',
    'type' => 'files',
    'order' => 1,
    'options' => [],
    'required' => false,
    'unique' => false,
    'sort_index' => null,
    'hint' => null,
    'file' => [],
    'display_level' => 3,
    'group' => null,
]);

$prepareFilesData = Closure::bind(
    fn (?string $highlight = null) => $this->prepareFilesData($highlight),
    $service,
    ColumnHtmlService::class,
);

$measure = static function (int $iterations, callable $callback): array {
    $samples = [];
    for ($i = 0; $i < $iterations; $i++) {
        $start = hrtime(true);
        $callback();
        $samples[] = (hrtime(true) - $start) / 1_000_000;
    }
    sort($samples);
    $count = count($samples);
    $median = $count % 2 === 1 ? $samples[intdiv($count, 2)] : ($samples[$count / 2 - 1] + $samples[$count / 2]) / 2;
    $p90Index = max(0, (int) ceil($count * 0.9) - 1);

    return [
        'count' => $count,
        'min_ms' => round($samples[0], 3),
        'median_ms' => round($median, 3),
        'avg_ms' => round(array_sum($samples) / $count, 3),
        'p90_ms' => round($samples[$p90Index], 3),
        'samples_ms' => array_map(fn ($v) => round($v, 3), $samples),
    ];
};

$inputValue = [
    'bench-a' => 'invoice-a.pdf',
    'bench-b' => 'image-b.png',
    'bench-c' => 'notes-c.txt',
    'bench-d' => 'spec-d.docx',
];

$service->setAttachmentCollection($attachments)->setAttachmentContents([])->setSource('benchmark-table-row')->show(
    $columnDefine,
    $inputValue,
    true,
    [],
    '',
    false,
    $ledger,
    null,
    $tenant->id,
);

$prepareOnly = $measure(
    15,
    static function () use ($service, $attachments, $columnDefine, $inputValue, $prepareFilesData): void {
        $service->mount($columnDefine, $inputValue);
        $service->setAttachmentCollection($attachments)->setAttachmentContents([])->setSource('benchmark-table-row');
        $prepareFilesData(null);
    }
);

$service->mount($columnDefine, $inputValue);
$service->setAttachmentCollection($attachments)->setAttachmentContents([])->setSource('benchmark-table-row');
$files = $prepareFilesData(null);

$bladeOnly = $measure(
    15,
    static function () use ($files, $tenant): void {
        view('components.ledger.attachment-list', [
            'files' => $files,
            'mode' => 'full',
            'tenantId' => $tenant->id,
            'search' => null,
        ])->render();
    }
);

$showTotal = $measure(
    15,
    static function () use ($service, $attachments, $columnDefine, $inputValue, $ledger, $tenant): void {
        $service
            ->setAttachmentCollection($attachments)
            ->setAttachmentContents([])
            ->setSource('benchmark-table-row')
            ->show(
                $columnDefine,
                $inputValue,
                true,
                [],
                '',
                false,
                $ledger,
                null,
                $tenant->id,
            );
    }
);

echo json_encode([
    'scenario' => 'files_column_four_attachments',
    'show_total_ms' => $showTotal,
    'prepare_files_ms' => $prepareOnly,
    'blade_render_ms' => $bladeOnly,
    'ratio_prepare_to_blade_median' => round($prepareOnly['median_ms'] / max($bladeOnly['median_ms'], 0.001), 3),
], JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE), PHP_EOL;
