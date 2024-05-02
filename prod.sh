# .env.productionをコピー
cp .env.production .env

# docker-composeを本番環境用の設定で実行
#docker-compose -f docker-compose.yml -f docker-compose.prod.yml up -d
docker compose -f docker-compose.prod.yml up -d
