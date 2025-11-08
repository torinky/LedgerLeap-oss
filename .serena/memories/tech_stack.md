# LedgerLeap 技術スタック

## バックエンド
- **言語**: PHP 8.4
- **フレームワーク**: Laravel 12.0
- **データベース**: MySQL 8.0+ / MariaDB + Mroonga（全文検索エンジン）
- **キャッシュ/キュー**: Redis
- **マルチテナント**: stancl/tenancy (^3.9)
- **権限管理**: spatie/laravel-permission (^6.9)
- **アクティビティログ**: spatie/laravel-activitylog (^4.9)
- **フォルダ階層**: kalnoy/nestedset (^6.0)
- **API認証**: laravel/sanctum (^4)

## フロントエンド
- **UIフレームワーク（一般）**: MaryUI (^2.0), DaisyUI (^5.4)
- **UIフレームワーク（管理）**: Filament PHP (^3.2)
- **JavaScriptフレームワーク**: Alpine.js (^3.15), Livewire (^3.6)
- **CSSフレームワーク**: Tailwind CSS (^4.1)
- **ビルドツール**: Vite (7.1.11)

## ファイル処理
- **テキスト抽出**: Apache Tika (vaites/php-apache-tika ^1.3)
- **OCR**: OcrMyPDF
- **VLM**: PaddleOCR-VL 0.9B（高精度OCR、Markdown生成、構造化データ抽出）
- **日本語形態素解析**: logue/igo-php (^0.2.1)
- **Excel/CSV**: maatwebsite/excel (^3.1.48)

## LLM統合
- **MCP Server**: Laravel MCP (^0.2.1)
- **用途**: AI統合業務管理プラットフォームへの発展

## 開発環境
- **コンテナ**: Laravel Sail (Docker)
- **Webサーバー**: Nginx
- **PHP実行環境**: PHP-FPM
- **テストフレームワーク**: Pest (^3.0), PHPUnit (^11)
- **コード整形**: Laravel Pint (^1.0)
- **IDE補助**: Laravel IDE Helper (^3), Laravel Debugbar (^3.8)

## アーキテクチャ
```
Webサーバー (Nginx) → Laravel (PHP-FPM) → MySQL/Mroonga
                    ↓
                 Redis (キュー/キャッシュ)
                    ↓
            キューワーカー → Apache Tika/OCR/VLM
```

## 重要な制約
- **Mroonga**: 全文検索に必須。複合インデックスは機能しない（OR結合で対応）
- **テスト**: 全文検索機能は`DatabaseMigrations`トレイト必須（`RefreshDatabase`不可）
- **Livewire**: パブリックプロパティはシンプルな連想配列のみ
