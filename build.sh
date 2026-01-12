#!/bin/bash

# PixPort 一键构建脚本
# 该脚本将执行所有构建前的预处理步骤

set -e  # 遇到错误时停止执行

echo "🚀 开始构建 PixPort..."

# 检查必要文件
if [ ! -f .env ]; then
    echo "⚠️  警告: .env 文件不存在，将使用默认配置"
    echo "# PixPort 环境配置文件" > .env
    echo "ADMIN_USER=admin" >> .env
    echo "ADMIN_PASSWORD=admin123" >> .env
    echo "✅ 已创建默认 .env 文件"
fi

# 从 .env 文件中读取配置
source .env

# 确保必要目录存在
echo "📁 创建必要目录..."
mkdir -p ./database
mkdir -p ./config
mkdir -p ./converted/pc/{jpeg,webp,avif}
mkdir -p ./converted/pe/{jpeg,webp,avif}
mkdir -p ./images/{pc,pe}
mkdir -p ./data
mkdir -p ./backups

# 设置目录权限
chmod -R 777 ./database ./config ./converted ./images ./data ./backups 2>/dev/null || true

echo "✅ 目录创建完成"

# 执行 Docker Compose 构建
echo "🐳 开始 Docker 构建..."
docker compose down --remove-orphans || true
docker compose up -d --build

echo "✅ 构建完成！"
echo "🌐 应用访问地址: http://localhost:27668"
echo "🔑 管理账户: ${ADMIN_USER:-admin}"
echo "🔒 管理密码: ${ADMIN_PASSWORD:-admin123}"
echo "🗄️  数据库: SQLite (轻量级，内存占用 < 3MB)"