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

# exam_app ディレクトリの中身を /var/www/html にコピー
COPY ./exam_app/ ./

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

# Apache起動時にログ出力を確認できるようにする
CMD ["sh", "-c", "tail -F /tmp/debug.log & exec docker-entrypoint.sh"]
