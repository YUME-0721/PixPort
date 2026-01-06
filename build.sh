#!/bin/bash

# PixPort 一键构建脚本
# 该脚本将执行所有构建前的预处理步骤

set -e  # 遇到错误时停止执行

echo "🚀 开始构建 PixPort..."

# 检查必要文件
if [ ! -f .env ]; then
    echo "⚠️  警告: .env 文件不存在，将使用默认配置"
    echo "# PixPort 环境配置文件" > .env
    echo "ADMIN_PASSWORD=admin123" >> .env
    echo "# MySQL 数据库配置" >> .env
    echo "DB_HOST=mysql" >> .env
    echo "DB_NAME=pixport" >> .env
    echo "DB_USER=pixport" >> .env
    echo "DB_PASSWORD=pixport123" >> .env
    echo "DB_ROOT_PASSWORD=pixport123" >> .env
    echo "✅ 已创建默认 .env 文件"
fi

echo "🔄 预处理 init.sql 文件..."
# 从 .env 文件中读取配置
source .env

# 替换 init.sql 中的占位符
sed -i "s/DB_NAME_PLACEHOLDER/${DB_NAME:-pixport}/g" ./database/init.sql
sed -i "s/DB_USER_PLACEHOLDER/${DB_USER:-pixport}/g" ./database/init.sql
sed -i "s/DB_PASSWORD_PLACEHOLDER/${DB_PASSWORD:-pixport123}/g" ./database/init.sql

echo "✅ init.sql 预处理完成"

# 确保 init.sql 文件权限正确
echo "🔧 修复 init.sql 文件权限..."
chmod 644 ./database/init.sql
echo "✅ init.sql 权限已修复"

# 确保必要目录存在
echo "📁 创建必要目录..."
mkdir -p ./mysql-data
mkdir -p ./converted/pc/{jpeg,webp,avif}
mkdir -p ./converted/pe/{jpeg,webp,avif}
mkdir -p ./images/{pc,pe}
mkdir -p ./data
mkdir -p ./app/config

# 设置目录权限
chmod -R 777 ./converted ./images ./data ./app/config 2>/dev/null || true

echo "✅ 目录创建完成"

# 执行 Docker Compose 构建
echo "🐳 开始 Docker 构建..."
docker compose down --remove-orphans || true
docker compose up -d --build

echo "✅ 构建完成！"
echo "🌐 应用访问地址: http://localhost:27668"
echo "🔒 管理密码: ${ADMIN_PASSWORD:-admin123}"
echo "🗄️  MySQL 访问: localhost:13308 (root密码: ${DB_ROOT_PASSWORD:-pixport123})"