# 公式イメージをベースにする
FROM jbarlow83/ocrmypdf:latest

# システムを更新し、日本語のTesseract言語パックをインストールする
RUN apt-get update && apt-get install -y --no-install-recommends \
    tesseract-ocr-jpn \
    && apt-get clean \
    && rm -rf /var/lib/apt/lists/*