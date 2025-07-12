FROM sail-8.4/app

# Docker CLI のインストール
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    # 既存のキーファイルが存在する場合に備えて、一度削除する
    && rm -f /etc/apt/keyrings/docker.gpg \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo \
    "deb [arch=\"$(dpkg --print-architecture)\" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
    \"$(. /etc/os-release && echo \"$VERSION_CODENAME\")\" stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli

# dockerグループの作成（既存でもOK）＆ sailユーザをdockerグループに追加
RUN groupadd -f docker \
    && usermod -aG docker sail \
    && usermod -aG docker www-data

# 不要なキャッシュをクリーンアップ
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*