# PixPort

一个功能完整的图片管理系统，提供本地上传、外链管理、随机图 API 等功能，支持多种图片格式和设备类型适配。

## 🌟 核心功能

- 🖼️ **图片上传管理** - 支持本地上传图片，自动分类存储
- 🔗 **外链图片管理** - 管理和维护外链图片库
- 🎨 **图片画廊** - 展示所有图片的可视化画廊
- 📊 **系统监控** - 实时查看系统和数据库状态
- 🔧 **API 控制面板** - 可视化配置 API 参数和功能开关
- 📱 **设备自适应** - 自动检测设备类型返回对应尺寸
- 🎯 **随机图 API** - 获取随机图片，支持多种参数配置
- 🐳 **Docker 部署** - 一键构建和部署
- ⚡ **SQLite 轻量化** - 默认使用 SQLite，内存占用 < 3MB，对比 MySQL 8.0 的 450MB+

## 🚀 快速开始

### 环境要求

- Docker
- Docker Compose
- Bash (用于运行 build.sh)

### 部署方式

#### 方式1：完整构建（首次部署或配置变更）

```bash
# 进入项目目录
cd /path/PixPort

# 给脚本添加执行权限
chmod +x build.sh

# 运行一键构建脚本
./build.sh
```

**脚本会自动完成：**
- 创建必要的目录结构
- 配置权限
- 初始化环境变量
- 构建 Docker 镜像
- 启动所有服务

#### 方式2：常规启动（配置无变化）

```bash
docker compose up -d
```

#### 方式3：停止服务

```bash
docker compose down
```

### 默认访问信息

| 服务 | 地址 | 用户名 | 密码 |
|------|------|--------|------|
| Web 应用 | `http://localhost:27668` | admin | `admin123` |

## 📁 项目结构

```
PixPort/
├── app/
│   ├── api/
│   │   └── image_api.php          # 随机图 API 接口
│   ├── pages/
│   │   ├── index.php              # 首页
│   │   ├── upload.php             # 图片上传
│   │   ├── api-panel.php          # API 控制面板
│   │   ├── gallery.php            # 图片画廊
│   │   └── panel.php              # 系统监控面板
│   └── config/                    # API 配置 (自动生成)
├── database/
│   ├── init_sqlite.sql            # SQLite 初始化脚本
│   └── pixport.db                 # SQLite 数据库文件 (自动创建)
├── includes/
│   └── Database.php               # 数据库连接类
├── public/
│   ├── assets/                    # 静态资源
│   └── .htaccess                  # Apache 重写规则
├── images/                        # 本地上传图片存储
│   ├── pc/                        # 桌面端图片
│   └── pe/                        # 移动端图片
├── converted/                     # 格式转换后的图片
├── data/                          # 相册数据存储
├── config/                        # 系统配置文件
├── Dockerfile                     # Docker 配置
├── docker-compose.yml             # Docker Compose 配置
├── .env                           # 环境变量 (自动生成)
├── build.sh                       # 一键构建脚本
└── README.md                      # 项目说明文档
```

## ⚙️ 配置说明

### 环境变量 (.env)

首次启动时 `build.sh` 会自动生成 `.env` 文件，可根据需要修改：

```env
# 管理后台账户
 ADMIN_USER=admin
ADMIN_PASSWORD=admin123
```

### 数据库配置

项目默认使用 **SQLite** 轻量级数据库，无需额外配置：

- **内存占用**: < 3MB（MySQL 8.0 需要 450MB+）
- **数据库文件**: `database/pixport.db`
- **自动创建**: 首次访问时自动初始化

#### （可选）使用 MySQL

如果需要使用 MySQL，需自行配置 MySQL 服务并在 `.env` 中添加：

```env
USE_MYSQL=true
DB_HOST=your_mysql_host
DB_PORT=3306
DB_NAME=pixport
DB_USER=pixport
DB_PASSWORD=your_password
```

然后手动创建数据库表结构（参考 `init_sqlite.sql` 转换为 MySQL 语法）。

### API 配置 (app/config/api-config.json)

通过 API 控制面板可视化配置以下参数：

| 参数 | 说明 | 默认值 |
|------|------|--------|
| `api_enabled` | API 服务启用开关 | `true` |
| `max_images_per_request` | 单次请求最多返回的图片数 | `50` |
| `default_image_count` | 未指定时的默认返回数 | `1` |
| `allow_external_mode` | 外链模式开关 | `true` |
| `cors_enabled` | CORS 跨域支持 | `true` |
| `cache_enabled` | 缓存启用 | `true` |
| `cache_ttl` | 缓存时间 (秒) | `3600` |
| `rate_limit_enabled` | 速率限制开关 | `false` |

## 🔌 API 接口

### 随机图 API

**基础请求:**
```
GET /image_api.php
```

**支持参数:**

| 参数 | 类型 | 默认值 | 说明 |
|------|------|--------|------|
| `count` | int | 1 | 返回图片数量 (1-50) |
| `type` | string | auto | 设备类型: `pc`/`pe`/`auto` |
| `format` | string | json | 响应格式: `json`/`text`/`url` |
| `return` | string | json | 返回类型: `json`/`redirect` |
| `external` | bool | false | 是否使用外链模式 |

**使用示例:**

```bash
# 获取 1 张图片 (JSON 格式)
curl http://localhost:27668/image_api.php

# 获取 5 张桌面端图片 (文本格式)
curl "http://localhost:27668/image_api.php?count=5&type=pc&format=text"

# 直接重定向到图片
curl -L "http://localhost:27668/image_api.php?return=redirect"
```

**JavaScript 使用:**

```javascript
// 获取随机图片
fetch('/image_api.php?count=5')
  .then(r => r.json())
  .then(data => {
    if (data.success) {
      data.images.forEach(img => {
        console.log(img.url);
      });
    }
  });
```

**HTML 直接使用:**

```html
<!-- 直接显示图片 -->
<img src="/image_api.php?return=redirect" alt="Random Image">

<!-- 背景图片 -->
<div style="background-image: url('/image_api.php?return=redirect&type=pc')"></div>
```

### 管理页面

| 页面 | 路径 | 说明 |
|------|------|------|
| 首页 | `/index.php` | 登录页面 |
| 图片上传 | `/upload.php` | 上传本地图片 |
| 图片画廊 | `/gallery.php` | 图片预览和管理 |
| 系统监控 | `/panel.php` | 系统状态和数据库信息 |
| API 控制 | `/api-panel.php` | API 参数配置和文档 |

## 🗄️ 数据库设计

项目默认使用 **SQLite** 轻量级数据库，也兼容 MySQL。

### images 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键 |
| `filename` | TEXT | 文件名 |
| `url` | TEXT | 访问 URL |
| `storage_type` | TEXT | 存储类型: `local`/`external` |
| `device_type` | TEXT | 设备类型: `pc`/`pe` |
| `format` | TEXT | 图片格式: `jpeg`/`webp`/`avif` |
| `local_path` | TEXT | 本地路径 |
| `width` | INTEGER | 图片宽度 |
| `height` | INTEGER | 图片高度 |
| `file_size` | INTEGER | 文件大小 |
| `tags` | TEXT | 标签 |
| `description` | TEXT | 描述 |
| `uploader_ip` | TEXT | 上传者 IP |
| `upload_time` | DATETIME | 上传时间 |

### albums 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键 |
| `album_id` | TEXT | 相册唯一标识 |
| `name` | TEXT | 相册名称 |
| `description` | TEXT | 相册描述 |
| `cover_image_id` | INTEGER | 封面图片ID |
| `created_at` | DATETIME | 创建时间 |
| `updated_at` | DATETIME | 更新时间 |

### album_images 表

| 字段 | 类型 | 说明 |
|------|------|------|
| `id` | INTEGER | 主键 |
| `album_id` | INTEGER | 相册ID |
| `image_id` | INTEGER | 图片ID |
| `sort_order` | INTEGER | 排序顺序 |
| `added_at` | DATETIME | 添加时间 |

## 📚 管理功能

### 权限说明

系统使用简单的会话认证，所有管理功能需要输入管理密码（默认: `admin123`）。

### 目录权限

系统启动时会自动创建并配置以下目录的权限：

```
./app/config         # API 配置目录 (777)
./images/{pc,pe}     # 图片存储目录 (777)
./converted/         # 转换后图片目录 (777)
./data/              # 相册数据目录 (777)
```

## 🐳 Docker 相关

### 端口映射

| 容器端口 | 主机端口 | 服务 |
|---------|---------|------|
| 80 | 27668 | PHP/Apache |

### 数据卷

```bash
# SQLite 数据库持久化
./database:/var/www/html/database

# 配置文件持久化
./config:/var/www/html/config

# 本地图片存储
./images:/var/www/html/images

# 转换后图片存储
./converted:/var/www/html/converted

# 相册数据存储
./data:/var/www/html/data
```

## 🔧 维护指南

### 查看日志

```bash
# PHP/Apache 日志
docker logs -f pixport
```

### 数据备份

```bash
# 备份 SQLite 数据库
cp database/pixport.db database/pixport.db.backup

# 恢复数据库
cp database/pixport.db.backup database/pixport.db
```

### 重置系统

```bash
# 停止所有服务
docker compose down

# 删除数据卷（注意：会丢失所有数据）
docker volume prune

# 重新启动
./build.sh
```

## 🐛 故障排除

### 无法访问应用

- 检查端口映射: `docker ps` 查看端口是否正确
- 检查防火墙设置，确保 27668 端口开放
- 查看容器日志: `docker logs pixport`

### 数据库连接失败

- SQLite 数据库会自动创建，无需额外配置
- 检查 `database/` 目录权限：`ls -la database/`
- 如使用 MySQL，确认环境变量 `USE_MYSQL=true`

### 上传文件失败

- 检查 `images/` 目录权限: `ls -la images/`
- 确认磁盘空间充足
- 检查 PHP 上传配置限制

### API 返回错误

- 确认 `api-config.json` 文件权限正确
- 检查是否在 API 控制面板中启用了 API 服务
- 查看 PHP 错误日志

## 📝 常见命令

```bash
# 启动服务
./build.sh                 # 完整构建
docker compose up -d       # 启动

# 停止服务
docker compose down        # 停止并删除容器
docker compose stop        # 仅停止容器

# 查看状态
docker compose ps          # 查看运行中的容器
docker logs pixport        # 查看 PHP 日志

# 进入容器
docker exec -it pixport bash          # 进入 PHP 容器

# 清理资源
docker compose down --remove-orphans  # 删除孤立的容器
docker volume prune                   # 删除未使用的数据卷
```

## 📄 许可证

本项目采用 MIT 许可证，详见 [LICENSE](LICENSE) 文件。

---

**最后更新:** 2026-01-11  
**版本:** v2.0 (SQLite 轻量化版本)  
**维护者:** YUME
