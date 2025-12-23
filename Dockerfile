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

# MPMの競合を防ぐ：event/worker を無効化し、prefork を有効化（確実に）
RUN rm /etc/apache2/mods-enabled/mpm_*.load && \
    a2dismod mpm_event mpm_worker || true && \
    a2enmod mpm_prefork

# タイムゾーンを日本時間に設定
RUN ln -fs /usr/share/zoneinfo/Asia/Tokyo /etc/localtime && \
    echo "Asia/Tokyo" > /etc/timezone

# Apacheが全インターフェースでポート80をリッスンするように設定
RUN echo "Listen 0.0.0.0:80" > /etc/apache2/ports.conf

# ServerName 警告を抑制
RUN echo "ServerName localhost" >> /etc/apache2/apache2.conf

# 作業ディレクトリを設定（/var/www/html）
WORKDIR /var/www/html

# アプリ全体をコピー（Dockerfileと同じ階層にある前提）
COPY . ./

# Composer install（必要なら）
RUN composer install --no-dev --optimize-autoloader

# .htaccess を有効にするためのディレクトリ設定
RUN echo '<Directory /var/www/html>\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' >> /etc/apache2/apache2.conf

# Apacheが index.php を優先して読み込むように設定
RUN echo "DirectoryIndex index.php" >> /etc/apache2/apache2.conf

# ポート80を明示的に公開（RailwayやRenderが検出できるように）
EXPOSE 80

# Apache を直接起動（ENTRYPOINT は使わず CMD のみ）
CMD ["apache2-foreground"]
