# .env.developmentをコピー
cp .env.development .env

# docker-composeを開発環境用の設定で実行
#docker compose -f docker-compose.yml -f docker-compose.dev.yml up -d
docker compose -f docker-compose.yml up -d
