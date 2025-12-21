<?php

namespace App\Services\Ledger;

class MockAttachmentService
{
    /**
     * Get the defined mock files with dynamic dates.
     */
    public static function getMockFiles(): array
    {
        return [
            // 画像ファイル（JPG）- OCR処理済み、PDF変換済み
            [
                'id' => 1,
                'filename' => '領収書_2025-12-01.jpg',
                'mime' => 'image/jpeg',
                'status' => 'completed',
                'thumbnailUrl' => 'https://via.placeholder.com/150x150/4CAF50/FFFFFF?text=JPG',
                'downloadUrl' => '#',
                'primary_download' => [
                    'url' => '#original',
                    'label' => 'Download Image',
                    'icon' => 'fa-download',
                ],
                'secondary_download' => [
                    'url' => '#pdf',
                    'label' => 'PDF',
                    'icon' => 'fa-file-pdf',
                ],
                'mock_vlm_text' => "# 株式会社サンプル商事 領収書要約\n\n2025年12月1日付の領収書です。金額は15,000円。書籍代としての支払いです。",
                'mock_ocr_text' => "株式会社サンプル商事\n領収書\n\n日付：2025年12月1日\n金額：¥15,000\n但書：書籍代として",
                'mock_tika_text' => "Sample Corp Receipt\nDate: 2025-12-01\nAmount: 15,000 JPY\nMemo: Books",
                'mock_vlm_status' => 'completed',
                'mock_ocr_status' => 'completed',
                'mock_tika_status' => 'completed',
                'mock_preview_text' => "株式会社サンプル商事\n領収書\n\n日付：2025年12月1日\n金額：¥15,000\n但書：書籍代として\n\n上記正に領収いたしました。",
                'mock_confidence' => 0.95,
                'mock_source' => 'OCR',
                'ocr_processed_at' => now()->subDays(2),
                'original_mime_type' => 'image/jpeg',
                'size' => 1024 * 1500, // 1.5MB
                'created_at' => now()->subDays(2),
                'mock_metadata' => ['dcterms:created' => '2025-12-01T10:30:00Z', 'dc:creator' => 'Camera'],
                'mock_ledger_title' => '2025年12月分 経費精算',
                'mock_folder_path' => '経理 / 2025年度 / 12月',
                'mock_creator_name' => '田中 太郎',
            ],
            // PDF（テキスト付き）- OCRmyPDF最適化済み
            [
                'id' => 2,
                'filename' => '契約書_2025年度.pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'primary_download' => [
                    'url' => '#optimized',
                    'label' => 'Optimized PDF',
                    'icon' => 'fa-file-pdf',
                ],
                'secondary_download' => [
                    'url' => '#original',
                    'label' => 'Original',
                    'icon' => 'fa-file',
                ],
                'mock_vlm_text' => "# 2025年度 業務委託契約書 要約\n\n甲乙間での業務委託に関する基本条項（目的、委託業務内容、契約期間など）を定めた文書です。",
                'mock_ocr_text' => "業務委託契約書\n第一条 ...",
                'mock_tika_text' => "業務委託契約書\n\n第一条（目的）\n本契約は、甲と乙の間における業務委託に関する事項を定めることを目的とする。\n\n第二条（委託業務）\n甲は乙に対し、以下の業務を委託する...",
                'mock_vlm_status' => 'missing',
                'mock_ocr_status' => 'completed',
                'mock_tika_status' => 'completed',
                'mock_preview_text' => "業務委託契約書\n\n第一条（目的）\n本契約は、甲と乙の間における業務委託に関する事項を定めることを目的とする。\n\n第二条（委託業務）\n甲は乙に対し、以下の業務を委託する...",
                'mock_confidence' => 0.88,
                'mock_source' => 'Tika',
                'ocr_processed_at' => now()->subDays(5),
                'original_mime_type' => 'application/pdf',
                'size' => 1024 * 500, // 500KB
                'created_at' => now()->subDays(5),
                'mock_metadata' => ['dcterms:created' => '2024-11-20T14:15:00Z', 'pdf:PDFVersion' => '1.7'],
                'mock_ledger_title' => '2025年度 業務委託契約',
                'mock_folder_path' => '法務 / 契約書 / 2025',
                'mock_creator_name' => '佐藤 次郎',
            ],
            // 画像ファイル（PNG）- OCR処理中
            [
                'id' => 3,
                'filename' => 'スクリーンショット.png',
                'mime' => 'image/png',
                'status' => 'processing',
                'thumbnailUrl' => 'https://via.placeholder.com/150x150/FFC107/FFFFFF?text=PNG',
                'downloadUrl' => '#',
                'mock_vlm_text' => null,
                'mock_ocr_text' => null,
                'mock_tika_text' => 'PNG screenshot file binary data...',
                'mock_vlm_status' => 'processing',
                'mock_ocr_status' => 'processing',
                'mock_tika_status' => 'completed',
                'mock_preview_text' => null,
                'mock_confidence' => 0.0,
                'mock_source' => 'OCR',
                'ocr_processed_at' => null,
                'original_mime_type' => 'image/png',
                'size' => 1024 * 2000, // 2MB
                'created_at' => now()->subMinutes(5),
                'mock_metadata' => ['dcterms:created' => now()->subMinutes(6)->toIso8601String()],
                'mock_ledger_title' => '作業メモ',
                'mock_folder_path' => '個人 / 下書き',
                'mock_creator_name' => '鈴木 花子',
            ],
            // Office文書（Word）- 完了（OCR不要）
            [
                'id' => 4,
                'filename' => '報告書_第4四半期.docx',
                'mime' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'mock_preview_text' => "第4四半期報告書\n\n1. 売上実績\n当四半期の売上は前年同期比120%を達成しました...",
                'mock_confidence' => 0.92,
                'mock_source' => 'Tika',
                'ocr_processed_at' => null,
                'original_mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'size' => 1024 * 300, // 300KB
                'created_at' => now()->subDays(10),
                'mock_metadata' => ['dcterms:created' => '2025-10-15T09:00:00Z', 'meta:last-author' => 'Yamada'],
                'mock_ledger_title' => '四半期報告',
                'mock_folder_path' => '営業 / 報告書 / 2025',
            ],
            // PDF（スキャン画像のみ）- OCR処理済み
            [
                'id' => 5,
                'filename' => 'スキャン文書_20251213.pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'mock_preview_text' => "社内通達\n\n件名：年末年始の営業について\n\n平素より格別のご高配を賜り、厚く御礼申し上げます。\n誠に勝手ながら、下記の期間を年末年始休業とさせていただきます...",
                'mock_confidence' => 0.78,
                'mock_source' => 'OCR',
                'ocr_processed_at' => now()->subDays(1),
                'original_mime_type' => 'application/pdf',
                'size' => 1024 * 2500, // 2.5MB
                'created_at' => now()->subDays(1),
                'mock_metadata' => ['dcterms:created' => '2025-12-13T16:45:00Z', 'pdf:PDFVersion' => '1.4'],
                'mock_ledger_title' => '社内掲示板',
                'mock_folder_path' => '総務 / 内規・通達',
            ],
            // Office文書（Excel）- 完了（OCR不要）
            [
                'id' => 6,
                'filename' => '売上集計表_12月.xlsx',
                'mime' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'mock_preview_text' => "売上集計表 - 12月\n\n商品A: ¥1,250,000\n商品B: ¥980,000\n商品C: ¥1,540,000\n合計: ¥3,770,000",
                'mock_confidence' => 0.85,
                'mock_source' => 'Tika',
                'ocr_processed_at' => null,
                'original_mime_type' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'size' => 1024 * 50, // 50KB
                'created_at' => now()->subDays(3),
                'mock_metadata' => ['dcterms:created' => '2025-12-18T11:20:00Z', 'meta:last-author' => 'Sato'],
                'mock_ledger_title' => '営業成績',
                'mock_folder_path' => '営業 / 週次報告',
            ],
            // その他ファイル（ZIP）- エラー（OCR対象外）
            [
                'id' => 7,
                'filename' => '資料一式.zip',
                'mime' => 'application/zip',
                'status' => 'error',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'mock_vlm_text' => null,
                'mock_ocr_text' => null,
                'mock_tika_text' => null,
                'mock_vlm_status' => 'missing',
                'mock_ocr_status' => 'error',
                'mock_tika_status' => 'error',
                'mock_preview_text' => null,
                'mock_confidence' => null,
                'mock_source' => null,
                'ocr_processed_at' => null,
                'original_mime_type' => 'application/zip',
                'size' => 1024 * 1024 * 10, // 10MB
                'created_at' => now()->subHours(1),
                'mock_metadata' => [],
                'mock_ledger_title' => 'プロジェクト共有',
                'mock_folder_path' => '開発 / プロジェクトX',
            ],
            // テキストファイル - 完了（OCR不要）
            [
                'id' => 8,
                'filename' => '議事録_20251213.txt',
                'mime' => 'text/plain',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'mock_preview_text' => "議事録\n日時：2025年12月13日 14:00-16:00\n場所：会議室A\n\n議題：\n1. 来期の事業計画について\n2. 新製品開発の進捗報告\n3. その他",
                'mock_confidence' => 0.98,
                'mock_source' => 'Tika',
                'ocr_processed_at' => null,
                'original_mime_type' => 'text/plain',
                'size' => 1024 * 2, // 2KB
                'created_at' => now()->subDays(7),
                'mock_metadata' => ['dc:title' => 'Meeting Minutes'],
                'mock_ledger_title' => '定例会議',
                'mock_folder_path' => '一般 / 議事録',
            ],
            // 画像ファイル（JPEG）- VLM解析済み、高信頼度
            [
                'id' => 9,
                'filename' => '名刺_田中様.jpg',
                'mime' => 'image/jpeg',
                'status' => 'completed',
                'thumbnailUrl' => 'https://via.placeholder.com/150x150/2196F3/FFFFFF?text=Card',
                'downloadUrl' => '#',
                'mock_preview_text' => "田中太郎\n営業部長\n株式会社テクノロジー\n\nTEL: 03-1234-5678\nEmail: tanaka@example.com\n〒100-0001 東京都千代田区...",
                'mock_confidence' => 0.97,
                'mock_source' => 'VLM',
                'ocr_processed_at' => now()->subHours(3),
                'original_mime_type' => 'image/jpeg',
                'size' => 1024 * 800, // 800KB
                'created_at' => now()->subHours(3),
                'mock_metadata' => ['dc:format' => 'image/jpeg'],
                'mock_ledger_title' => '名刺管理',
                'mock_folder_path' => '営業 / 顧客',
            ],
            // PDF（複合）- VLM + OCR処理済み
            [
                'id' => 10,
                'filename' => '見積書_202512.pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'primary_download' => [
                    'url' => '#optimized',
                    'label' => 'Optimized PDF',
                    'icon' => 'fa-file-pdf',
                ],
                'secondary_download' => [
                    'url' => '#original',
                    'label' => 'Original',
                    'icon' => 'fa-file',
                ],
                'mock_vlm_text' => "# 見積書 解析結果\n- **宛先**: 株式会社サンプル\n- **合計金額**: 5,000,000円\n- **有効期限**: 2025年12月末\n\nこの文書は標準的なシステム開発見積書です。",
                'mock_ocr_text' => "御見積書\n株式会社サンプル 御中\n...",
                'mock_tika_text' => "Quotation\nTo: Sample Corp\nAmount: 5,000,000 JPY",
                'mock_vlm_status' => 'completed',
                'mock_ocr_status' => 'completed',
                'mock_tika_status' => 'completed',
                'mock_preview_text' => "御見積書\n\n株式会社サンプル 御中\n\n下記の通りお見積もり申し上げます。\n\n品名：システム開発一式\n金額：¥5,000,000（税別）\n納期：2026年3月末\n\n有効期限：2025年12月31日まで",
                'mock_confidence' => 0.91,
                'mock_source' => 'VLM',
                'ocr_processed_at' => now()->subDays(3),
                'original_mime_type' => 'application/pdf',
                'size' => 1024 * 1200, // 1.2MB
                'created_at' => now()->subDays(3),
                'mock_metadata' => ['dcterms:created' => '2025-11-28T10:00:00Z', 'pdf:PDFVersion' => '1.5'],
                'mock_ledger_title' => '案件見積',
                'mock_folder_path' => '営業 / 見積 / 2025',
            ],
            // 画像ファイル（PNG）- OCR低信頼度（手書き）
            [
                'id' => 11,
                'filename' => '手書きメモ.png',
                'mime' => 'image/png',
                'status' => 'completed',
                'thumbnailUrl' => 'https://via.placeholder.com/150x150/FF9800/FFFFFF?text=Note',
                'downloadUrl' => '#',
                'mock_preview_text' => "明日の打ち合わせ\n・資料準備\n・会議室予約\n・参加者確認",
                'mock_confidence' => 0.65,
                'mock_source' => 'OCR',
                'ocr_processed_at' => now()->subHours(12),
                'original_mime_type' => 'image/png',
                'size' => 1024 * 400, // 400KB
                'created_at' => now()->subHours(12),
                'mock_metadata' => [],
                'mock_ledger_title' => 'アイデアメモ',
                'mock_folder_path' => '個人 / メモ',
            ],
            // PDF（大容量）- OCRmyPDF最適化で大幅サイズ削減
            [
                'id' => 12,
                'filename' => 'カタログ_2025.pdf',
                'mime' => 'application/pdf',
                'status' => 'completed',
                'thumbnailUrl' => null,
                'downloadUrl' => '#',
                'primary_download' => [
                    'url' => '#optimized',
                    'label' => 'Optimized PDF',
                    'icon' => 'fa-file-pdf',
                ],
                'secondary_download' => [
                    'url' => '#original',
                    'label' => 'Original',
                    'icon' => 'fa-file',
                ],
                'mock_preview_text' => "製品カタログ 2025年版\n\n新製品ラインナップ\n・モデルA：高性能タイプ\n・モデルB：標準タイプ\n・モデルC：エントリータイプ\n\n詳細な仕様については各ページをご参照ください...",
                'mock_confidence' => 0.82,
                'mock_source' => 'OCR',
                'ocr_processed_at' => now()->subDays(7),
                'original_mime_type' => 'application/pdf',
                'size' => 1024 * 1024 * 15, // 15MB
                'created_at' => now()->subDays(7),
                'mock_metadata' => ['dcterms:created' => '2025-01-10T12:00:00Z'],
                'mock_ledger_title' => '製品資料',
                'mock_folder_path' => 'マーケティング / カタログ',
            ],
        ];
    }

    public static function isMockColumn(int|string|null $columnId): bool
    {
        return $columnId == config('mock.attachment.column_id', -1);
    }

    public static function isEnabled(): bool
    {
        return config('mock.attachment.enabled', false);
    }

    public static function getMockColumnDefine(): array
    {
        return [
            'id' => config('mock.attachment.column_id', -1),
            'type' => 'files',
            'name' => 'Attachments (Mock)',
            'group' => 'Mock',
            'display_level' => 1,
            'order' => 9999,
            'required' => false,
            'unique' => false,
            'sort_index' => null,
            'hint' => 'モックデータ表示用の添付ファイルカラム',
            'file' => [],
            'options' => [],
            'useOptions' => false,
        ];
    }
}
