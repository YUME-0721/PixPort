#!/bin/bash

# 构建前预处理脚本：替换init.sql中的占位符
echo "🔄 正在预处理 init.sql 文件..."

# 从 .env 文件中读取配置
if [ -f .env ]; then
    export $(cat .env | xargs)
fi

# 使用默认值或环境变量替换占位符
DB_NAME=${DB_NAME:-picflow}
DB_USER=${DB_USER:-mzs666}
DB_PASSWORD=${DB_PASSWORD:-MZS0619mzs.}

# 替换 init.sql 中的占位符
sed -i "s/DB_NAME_PLACEHOLDER/$DB_NAME/g" ./database/init.sql
sed -i "s/DB_USER_PLACEHOLDER/$DB_USER/g" ./database/init.sql
sed -i "s/DB_PASSWORD_PLACEHOLDER/$DB_PASSWORD/g" ./database/init.sql

echo "✅ init.sql 预处理完成"
echo "📊 数据库: $DB_NAME"
echo "👤 用户: $DB_USER"
echo "🔒 密码: ***已隐藏***"