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

# 作業ディレクトリを設定
WORKDIR /var/www/html/exam_app

# アプリのファイルをコピー
COPY . .

# Composer install（必要なら）
RUN composer install --no-dev --optimize-autoloader

# 🔧 カスタムエントリポイントスクリプトをコピーして実行権限を付与
COPY docker-entrypoint.sh /usr/local/bin/docker-entrypoint.sh
RUN chmod +x /usr/local/bin/docker-entrypoint.sh

# 🔧 Apache起動をカスタムスクリプトに任せる
CMD ["docker-entrypoint.sh"]

RUN echo '<Directory /var/www/html/exam_app>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Apacheのドキュメントルートを /exam_app に変更
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/exam_app|g' /etc/apache2/sites-available/000-default.conf

# .htaccess を有効にするためのディレクトリ設定
RUN echo '<Directory /var/www/html/exam_app>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# dummy change to force rebuild
