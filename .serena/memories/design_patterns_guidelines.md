プロジェクトには以下のガイドラインやデザインパターンがあります。
- システムアーキテクチャ概要: `/docs/architecture/overview.md`
- キューワーカと非同期処理: `/docs/architecture/QueueProcessing.md`
- データベーススキーマ概要: `/docs/database/schema.md`
- API仕様概要: `/docs/api/README.md`
- 各機能の詳細ドキュメント: `/docs/function/` 以下

特に、ファットコントローラを避け、サービスクラスにビジネスロジックを分離する設計思想が採用されています。