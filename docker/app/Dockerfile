FROM sail-8.4/app

# Docker CLI のインストール
# Ubuntu ベースのイメージを想定し、より堅牢な GPG キーの取得方法を採用
RUN apt-get update && apt-get install -y --no-install-recommends \
    ca-certificates \
    curl \
    gnupg \
    && install -m 0755 -d /etc/apt/keyrings \
    && curl -fsSL https://download.docker.com/linux/ubuntu/gpg | gpg --dearmor -o /etc/apt/keyrings/docker.gpg \
    && chmod a+r /etc/apt/keyrings/docker.gpg \
    && echo \
    "deb [arch=\"$(dpkg --print-architecture)\" signed-by=/etc/apt/keyrings/docker.gpg] https://download.docker.com/linux/ubuntu \
    \"$(. /etc/os-release && echo \"$VERSION_CODENAME\")\" stable" | tee /etc/apt/sources.list.d/docker.list > /dev/null \
    && apt-get update \
    && apt-get install -y docker-ce-cli

# sail ユーザーを docker グループに追加
# これにより、sail ユーザーが docker コマンドを実行できるようになる
RUN groupadd -f docker \
    && usermod -aG docker sail

# 不要なキャッシュをクリーンアップ
RUN apt-get clean && rm -rf /var/lib/apt/lists/* /tmp/* /var/tmp/*