# 使用官方 PHP 8.2 Apache 镜像
FROM php:8.2-apache

# 设置工作目录
WORKDIR /var/www/html

# 更换国内镜像源（阿里云）
RUN sed -i 's/deb.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources \
    && sed -i 's/security.debian.org/mirrors.aliyun.com/g' /etc/apt/sources.list.d/debian.sources

# 安装系统依赖和 GD 扩展所需的库
RUN apt-get update && apt-get install -y \
    libpng-dev \
    libjpeg-dev \
    libwebp-dev \
    libavif-dev \
    libfreetype6-dev \
    libzip-dev \
    && rm -rf /var/lib/apt/lists/*

# 配置并安装 GD 扩展（支持 WebP 和 AVIF）和 PDO MySQL 扩展
RUN docker-php-ext-configure gd \
    --with-freetype \
    --with-jpeg \
    --with-webp \
    --with-avif \
    && docker-php-ext-install -j$(nproc) gd pdo_mysql zip

# 启用 Apache mod_rewrite
RUN a2enmod rewrite

# 配置 PHP 上传限制
RUN echo 'upload_max_filesize = 50M' >> /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo 'post_max_size = 50M' >> /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo 'max_execution_time = 300' >> /usr/local/etc/php/conf.d/upload-limit.ini \
    && echo 'max_input_time = 300' >> /usr/local/etc/php/conf.d/upload-limit.ini

# 配置 Apache
RUN echo 'ServerName localhost' >> /etc/apache2/apache2.conf \
    && echo '<Directory /var/www/html>\n\
    Options Indexes FollowSymLinks\n\
    AllowOverride All\n\
    Require all granted\n\
</Directory>' > /etc/apache2/conf-available/picflow.conf \
    && a2enconf picflow

# 复制项目文件
COPY --chown=www-data:www-data . /var/www/html/

# 确保 init.sql 文件权限正确
RUN chmod 644 /var/www/html/database/init.sql

# 暴露 80 端口
EXPOSE 80

# 创建启动脚本，自动创建并修复挂载目录权限
RUN echo '#!/bin/bash\n\
# 创建必要的目录结构\n\
mkdir -p /var/www/html/converted/pc/{jpeg,webp,avif} 2>/dev/null || true\n\
mkdir -p /var/www/html/converted/pe/{jpeg,webp,avif} 2>/dev/null || true\n\
mkdir -p /var/www/html/images/{pc,pe} 2>/dev/null || true\n\
mkdir -p /var/www/html/data 2>/dev/null || true\n\
mkdir -p /var/www/html/app/config 2>/dev/null || true\n\
mkdir -p /var/www/html/backups 2>/dev/null || true\n\
# 修复权限\n\
chmod -R 777 /var/www/html/converted 2>/dev/null || true\n\
chmod -R 777 /var/www/html/images 2>/dev/null || true\n\
chmod -R 777 /var/www/html/data 2>/dev/null || true\n\
chmod -R 777 /var/www/html/app/config 2>/dev/null || true\n\
chmod -R 777 /var/www/html/backups 2>/dev/null || true\n\
chown -R www-data:www-data /var/www/html/converted 2>/dev/null || true\n\
chown -R www-data:www-data /var/www/html/images 2>/dev/null || true\n\
chown -R www-data:www-data /var/www/html/data 2>/dev/null || true\n\
chown -R www-data:www-data /var/www/html/app/config 2>/dev/null || true\n\
chown -R www-data:www-data /var/www/html/backups 2>/dev/null || true\n\
echo "✅ 目录初始化完成"\n\
exec apache2-foreground' > /start.sh \
	&& chmod +x /start.sh

# 启动 Apache
CMD ["/start.sh"]
