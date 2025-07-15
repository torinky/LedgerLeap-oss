# 全文検索機能

## 概要

全文検索機能は、LedgerLeap
に登録された台帳の内容やアップロードされたファイルを対象に、高速かつ柔軟な検索を提供する機能です。ユーザーは、キーワードを入力することで、関連する台帳やファイル、レコードを検索できます。この機能は、MroongaデータベースエンジンとApache
Tikaを利用して実現しています。また、類義語の検索も可能です。類義語には、一般的な類語を管理するWordNetと、ユーザーが管理する専門用語を管理する
`TechnicalTermGroup`があります。

## 機能詳細

### 検索対象

* 台帳のタイトルや説明
* 台帳のレコードの内容
* アップロードされたファイルの内容

### 検索方法

* **キーワード検索**: 検索ボックスにキーワードを入力することで、関連する台帳やファイルを検索できます。
* **ファイル検索**: アップロードされたファイルの内容も検索対象となります。
* **類義語検索**: 類義語も検索できます。

### Apache Tika を利用したファイル検索

* アップロードされたファイルは、Apache Tika を通じて解析され、その内容がインデックス化されます。これにより、ファイルの内容をキーワードとして検索することが可能になります。
* `App\Services\TikaService`で管理します。
* アップロードされたファイルは、`App\Models\Ledger`に紐づきます。
*     `app/Filament/Resources/LedgerResource.php`でアップロードします。

### 類義語を使った検索

* 類義語機能を利用することで、関連するキーワードを自動的に検索結果に含めることが可能になります。
* `App\Services\SynonymService`で管理します。
* `App\Services\SearchService`を使って、検索します。
* 類義語の登録や管理は、`App\Http\Controllers\SynonymController`で行います。

#### WordNet

* WordNet は、英単語の概念間の関係を定義した大規模な意味ネットワークであり、日本語の類義語の取得も可能です。
* `App\Services\SynonymService`で利用します。
* 一般的な類義語を検索に利用するために、WordNetを使用しています。

#### TechnicalTermGroup

* `App\Models\Synonym\TechnicalTermGroup` は、ユーザーが定義した専門用語とその類義語を管理するモデルです。
* ユーザーが業界用語や専門用語を登録することで、検索の精度を向上させることができます。
* `App\Http\Controllers\SynonymController`で管理します。

### OCR機能による検索対象の拡張

*   **目的**: 画像ベースのPDFや画像ファイル（例: スキャンされた書類）の内容も全文検索の対象に含めることで、検索能力を大幅に向上させます。
*   **仕組み**: 
    1.  ファイルアップロード後、まずApache Tikaによるテキスト抽出を試みます。
    2.  Tikaでテキストが抽出できなかったファイル（画像PDFや画像ファイルなど）に対して、バックグラウンドでOCR処理（OcrMyPDFを利用）を実行します。
    3.  OCR処理によって抽出されたテキストをデータベースに保存し、Mroongaの全文検索インデックスに含めます。
*   **状態管理**: ファイルの処理状態（Tika処理中、OCR待ち、OCR処理中、完了、エラーなど）は `attached_files` テーブルの `status` カラムで管理されます。

### Mroonga

* Mroonga は、高速な全文検索機能を提供するデータベースエンジンです。日本語にも対応しています。
* LedgerLeap では、Mroonga を利用して全文検索を実現しています。

## 関連ファイル

* `App\Services\TikaService`: ファイルの内容を抽出するサービス。
* `App\Services\SynonymService`: 類義語を管理するサービス。
* `App\Http\Controllers\SynonymController`: 類語を登録、検索する処理を管理する。
* `App\Models\Synonym\TechnicalTermGroup`: ユーザーが管理する専門用語とその類義語を管理するモデルです。
* `Mroonga`: 高速な全文検索を提供するデータベースエンジン。
* `App\Models\Ledger`: 台帳のモデル。
* `app/Filament/Resources/LedgerResource.php`: Ledgerモデルを管理します。
