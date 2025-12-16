FROM php:8.2-apache

# 必要なPHP拡張をインストール
RUN apt-get update && apt-get install -y \
    unzip \
    git \
    libzip-dev \
    libonig-dev \
    libxml2-dev \
    && docker-php-ext-install mysqli pdo pdo_mysql zip mbstring

# Composerをインストール（軽量な方法）
COPY --from=composer:latest /usr/bin/composer /usr/bin/composer

# Apacheのrewriteモジュールを有効化
RUN a2enmod rewrite

# タイムゾーンを日本時間に設定
RUN ln -fs /usr/share/zoneinfo/Asia/Tokyo /etc/localtime && \
    echo "Asia/Tokyo" > /etc/timezone

# 作業ディレクトリを設定（/var/www/html）
WORKDIR /var/www/html

# メインアプリのファイルをコピー（index.php, test.php など）
COPY . ./

# video_app を明示的にサブディレクトリにコピー（上書き防止のため）
COPY ./video_app/ ./video_app/

# Composer install（必要なら）
RUN composer install --no-dev --optimize-autoloader

# カスタムエントリポイントスクリプトをコピーして実行権限を付与
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# .htaccess を有効にするためのディレクトリ設定
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Apacheが index.php を優先して読み込むように設定
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# ENTRYPOINT を明示的に指定（Apache起動スクリプト）
ENTRYPOINT ["/usr/local/bin/docker-entrypoint.sh"]

# CMD は Apache の標準起動コマンド
CMD ["apache2-foreground"]
