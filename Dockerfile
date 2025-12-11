FROM php:8.2-apache

# MySQL拡張をインストール
RUN docker-php-ext-install mysqli pdo pdo_mysql

# Apacheのrewriteモジュールを有効化（任意）
RUN a2enmod rewrite

# タイムゾーンを日本時間に設定（任意）
RUN ln -fs /usr/share/zoneinfo/Asia/Tokyo /etc/localtime && \
    echo "Asia/Tokyo" > /etc/timezone
