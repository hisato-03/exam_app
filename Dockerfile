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

# 🔧 MPMの競合を解消！
RUN a2dismod mpm_event && a2enmod mpm_prefork

# タイムゾーンを日本時間に設定
RUN ln -fs /usr/share/zoneinfo/Asia/Tokyo /etc/localtime && \
    echo "Asia/Tokyo" > /etc/timezone

# 作業ディレクトリを設定
WORKDIR /var/www/html/exam_app

# アプリのファイルをコピー
COPY . .

# Composer install（必要なら）
RUN composer install --no-dev --optimize-autoloader

# Apacheのドキュメントルートを exam_app に変更
RUN sed -i 's|DocumentRoot /var/www/html|DocumentRoot /var/www/html/exam_app|g' /etc/apache2/sites-available/000-default.conf

# 🔧 RailwayのPORTに対応
RUN sed -i "s/80/${PORT}/g" /etc/apache2/ports.conf /etc/apache2/sites-available/000-default.conf

# 🔧 Apache起動コマンド（PORT対応も後で追加できる）
CMD ["apache2ctl", "-D", "FOREGROUND"]
