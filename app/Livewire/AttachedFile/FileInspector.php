<?php

namespace App\Livewire\AttachedFile;

use App\Livewire\Traits\InitializesTenantContext;
use App\Models\AttachedFile;
use Illuminate\Support\Facades\Gate;
use Livewire\Attributes\On;
use Livewire\Component;
use Mary\Traits\Toast;

class FileInspector extends Component
{
    use InitializesTenantContext, Toast;

    public bool $open = false;

    public ?int $fileId = null;

    public ?AttachedFile $file = null;

    public string $selectedTab = 'content';

    public function mount(): void
    {
        // 初期状態
        $this->open = false;
        $this->selectedTab = 'content'; // デフォルトは「内容」タブ
    }

    #[On('open-file-inspector')]
    public function openInspector(int $id): void
    {
        \Log::info('FileInspector: openInspector called with id='.$id);

        try {
            $this->fileId = $id;

            // モックデータの場合（id=1-12）はダミーオブジェクトを作成
            if ($id >= 1 && $id <= 12) {
                $mockData = [
                    1 => [
                        'filename' => '領収書_2025-12-01.jpg',
                        'original_filename' => '領収書_2025-12-01.jpg',
                        'mime' => 'image/jpeg',
                        'original_mime_type' => 'image/jpeg',
                        'size' => 2048000,
                        'source' => 'VLM',
                        'confidence' => 0.952,
                        'preview_text' => "株式会社サンプル商事\n領収書\n\n日付: 2025年12月1日\n金額: ¥50,000\n但し: 業務用ソフトウェアライセンス代として\n\n上記金額を正に領収いたしました。",
                        'ledger_title' => '経費精算台帳',
                        'folder_path' => '総務部 > 経理課',
                        'ocr_processed_at' => now()->subDays(2),
                    ],
                    2 => [
                        'filename' => '契約書_2025年度.pdf',
                        'original_filename' => '契約書_2025年度.pdf',
                        'mime' => 'application/pdf',
                        'original_mime_type' => 'application/pdf',
                        'size' => 524288,
                        'source' => 'Tika',
                        'confidence' => 1.0,
                        'preview_text' => "業務委託契約書\n\n第1条（目的）\n本契約は、甲と乙との間における業務委託に関する基本的事項を定めることを目的とする。\n\n第2条（契約期間）\n本契約の有効期間は、2025年4月1日から2026年3月31日までとする。",
                        'ledger_title' => '契約管理台帳',
                        'folder_path' => '総務部 > 法務課',
                        'ocr_processed_at' => now()->subDays(5),
                    ],
                    3 => [
                        'filename' => 'スクリーンショット.png',
                        'original_filename' => 'スクリーンショット.png',
                        'mime' => 'image/png',
                        'original_mime_type' => 'image/png',
                        'size' => 1536000,
                        'source' => 'OCR',
                        'confidence' => 0.0,
                        'preview_text' => null, // 処理中
                        'ledger_title' => '不具合報告台帳',
                        'folder_path' => '開発部 > QA課',
                        'ocr_processed_at' => now()->subHours(3),
                    ],
                    4 => [
                        'filename' => '報告書_第4四半期.docx',
                        'original_filename' => '報告書_第4四半期.docx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'original_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                        'size' => 102400,
                        'source' => 'Tika',
                        'confidence' => 1.0,
                        'preview_text' => "2025年度 第4四半期 業績報告書\n\n1. エグゼクティブサマリー\n第4四半期における売上高は前年同期比15%増の1,250百万円となりました。\n\n2. 主要指標\n- 売上高: 1,250百万円 (+15%)\n- 営業利益: 180百万円 (+22%)\n- 顧客満足度: 92% (+3pt)",
                        'ledger_title' => '業績報告台帳',
                        'folder_path' => '経営企画部',
                        'ocr_processed_at' => null,
                    ],
                    5 => [
                        'filename' => 'スキャン文書_20251213.pdf',
                        'original_filename' => 'スキャン文書_20251213.pdf',
                        'mime' => 'application/pdf',
                        'original_mime_type' => 'application/pdf',
                        'size' => 3145728,
                        'source' => 'OCR',
                        'confidence' => 0.875,
                        'preview_text' => "会議議事録\n\n開催日時: 2025年12月10日 14:00-16:00\n参加者: 山田部長、田中課長、佐藤主任、鈴木\n\n議題1: 来期予算について\n- 各部門より予算案の説明\n- 全体で15%の増額を承認\n\n議題2: 新規プロジェクト立ち上げ\n- AI活用による業務効率化を検討",
                        'ledger_title' => '会議議事録台帳',
                        'folder_path' => '全社 > 会議室予約',
                        'ocr_processed_at' => now()->subDays(1),
                    ],
                    6 => [
                        'filename' => '売上集計表_12月.xlsx',
                        'original_filename' => '売上集計表_12月.xlsx',
                        'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'original_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                        'size' => 81920,
                        'source' => 'Tika',
                        'confidence' => 1.0,
                        'preview_text' => "売上集計表（2025年12月）\n\n商品A: 850万円\n商品B: 620万円\n商品C: 430万円\nサービスX: 1,200万円\nサービスY: 890万円\n\n合計: 3,990万円\n前月比: +8.5%",
                        'ledger_title' => '売上管理台帳',
                        'folder_path' => '営業部 > 営業1課',
                        'ocr_processed_at' => null,
                    ],
                    7 => [
                        'filename' => '資料一式.zip',
                        'original_filename' => '資料一式.zip',
                        'mime' => 'application/zip',
                        'original_mime_type' => 'application/zip',
                        'size' => 10485760,
                        'source' => null,
                        'confidence' => 0.0,
                        'preview_text' => null, // エラー
                        'ledger_title' => 'プロジェクト資料台帳',
                        'folder_path' => '開発部 > プロジェクトA',
                        'ocr_processed_at' => null,
                    ],
                    8 => [
                        'filename' => '議事録_20251213.txt',
                        'original_filename' => '議事録_20251213.txt',
                        'mime' => 'text/plain',
                        'original_mime_type' => 'text/plain',
                        'size' => 4096,
                        'source' => 'Tika',
                        'confidence' => 1.0,
                        'preview_text' => "プロジェクト進捗会議 議事録\n\n日時: 2025年12月13日 10:00-11:30\n場所: 第2会議室\n\n進捗報告:\n1. 開発フェーズ1完了（予定通り）\n2. テスト環境構築中（遅延なし）\n3. ユーザー受入テスト準備開始\n\n次回: 2025年12月20日",
                        'ledger_title' => 'プロジェクト管理台帳',
                        'folder_path' => '開発部 > プロジェクトA',
                        'ocr_processed_at' => null,
                    ],
                    9 => [
                        'filename' => '名刺_田中様.jpg',
                        'original_filename' => '名刺_田中様.jpg',
                        'mime' => 'image/jpeg',
                        'original_mime_type' => 'image/jpeg',
                        'size' => 1024000,
                        'source' => 'VLM',
                        'confidence' => 0.97,
                        'preview_text' => "田中太郎\n営業部長\n株式会社テクノロジー\n\nTEL: 03-1234-5678\nEmail: tanaka@example.com\n〒100-0001 東京都千代田区...",
                        'ledger_title' => '名刺管理台帳',
                        'folder_path' => '営業部',
                        'ocr_processed_at' => now()->subHours(3),
                    ],
                    10 => [
                        'filename' => '見積書_202512.pdf',
                        'original_filename' => '見積書_202512.pdf',
                        'mime' => 'application/pdf',
                        'original_mime_type' => 'application/pdf',
                        'size' => 768000,
                        'source' => 'VLM',
                        'confidence' => 0.91,
                        'preview_text' => "御見積書\n\n株式会社サンプル 御中\n\n下記の通りお見積もり申し上げます。\n\n品名：システム開発一式\n金額：¥5,000,000（税別）\n納期：2026年3月末\n\n有効期限：2025年12月31日まで",
                        'ledger_title' => '見積管理台帳',
                        'folder_path' => '営業部 > 営業2課',
                        'ocr_processed_at' => now()->subDays(3),
                    ],
                    11 => [
                        'filename' => '手書きメモ.png',
                        'original_filename' => '手書きメモ.png',
                        'mime' => 'image/png',
                        'original_mime_type' => 'image/png',
                        'size' => 512000,
                        'source' => 'OCR',
                        'confidence' => 0.65,
                        'preview_text' => "明日の打ち合わせ\n・資料準備\n・会議室予約\n・参加者確認",
                        'ledger_title' => 'メモ台帳',
                        'folder_path' => '個人 > 田中',
                        'ocr_processed_at' => now()->subHours(12),
                    ],
                    12 => [
                        'filename' => 'カタログ_2025.pdf',
                        'original_filename' => 'カタログ_2025.pdf',
                        'mime' => 'application/pdf',
                        'original_mime_type' => 'application/pdf',
                        'size' => 15728640,
                        'source' => 'OCR',
                        'confidence' => 0.82,
                        'preview_text' => "製品カタログ 2025年版\n\n新製品ラインナップ\n・モデルA：高性能タイプ\n・モデルB：標準タイプ\n・モデルC：エントリータイプ\n\n詳細な仕様については各ページをご参照ください...",
                        'ledger_title' => '製品カタログ台帳',
                        'folder_path' => 'マーケティング部',
                        'ocr_processed_at' => now()->subDays(7),
                    ],
                ];

                $data = $mockData[$id] ?? $mockData[1];

                $this->file = new AttachedFile([
                    'filename' => $data['filename'],
                    'original_filename' => $data['original_filename'],
                    'mime' => $data['mime'],
                    'original_mime_type' => $data['original_mime_type'],
                    'size' => $data['size'],
                    'created_at' => now()->subDays(rand(1, 30)),
                    'updated_at' => now()->subDays(rand(0, 5)),
                ]);

                // IDは後から設定（Eloquentの仕様）
                $this->file->id = $id;
                $this->file->exists = false;

                // モック用の追加プロパティ
                $this->file->mock_source = $data['source'];
                $this->file->mock_confidence = $data['confidence'];
                $this->file->mock_preview_text = $data['preview_text'];
                $this->file->mock_ledger_title = $data['ledger_title'];
                $this->file->mock_folder_path = $data['folder_path'];
                $this->file->ocr_processed_at = $data['ocr_processed_at'] ?? null;

                \Log::info('FileInspector: Mock file created', [
                    'id' => $this->file->id,
                    'filename' => $this->file->filename,
                    'mime' => $this->file->mime,
                    'ocr_processed_at' => $this->file->ocr_processed_at,
                ]);

                $this->open = true;
                \Log::info('FileInspector: Drawer opened for mock file id='.$id);

                return;
            }

            $this->file = AttachedFile::with([
                'ledger:id,content_attached,ledger_define_id',
                'ledger.define:id,title',
                'ledger.folder:id,title',
                'creator:id,name',
                'modifier:id,name',
            ])->findOrFail($id);

            if (! Gate::allows('view', [AttachedFile::class, $this->file])) {
                $this->error(__('ledger.no_view_permission'));

                return;
            }

            $this->open = true;
            \Log::info('FileInspector: Drawer opened successfully for file id='.$id);
        } catch (\Exception $e) {
            \Log::error('FileInspector open failed: '.$e->getMessage());
            $this->error(__('ledger.vlm.result_not_found'));
        }
    }

    public function close(): void
    {
        $this->open = false;
        $this->fileId = null;
        $this->file = null;
    }

    public function render()
    {
        return \view('livewire.attached-file.file-inspector');
    }
}
