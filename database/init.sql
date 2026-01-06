-- PicFlow-API 数据库初始化脚本

CREATE DATABASE IF NOT EXISTS DB_NAME_PLACEHOLDER DEFAULT CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE DB_NAME_PLACEHOLDER;

-- 图片信息表
CREATE TABLE IF NOT EXISTS images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '图片ID',
    filename VARCHAR(255) NOT NULL COMMENT '文件名',
    url VARCHAR(500) NOT NULL COMMENT '访问URL',
    storage_type ENUM('local', 'external') NOT NULL DEFAULT 'local' COMMENT '存储类型：本地/外链',
    device_type ENUM('pc', 'pe') NOT NULL COMMENT '设备类型：PC端/移动端',
    format VARCHAR(20) NOT NULL COMMENT '图片格式',
    local_path VARCHAR(500) DEFAULT NULL COMMENT '本地文件路径（仅本地存储）',
    width INT UNSIGNED DEFAULT NULL COMMENT '图片宽度',
    height INT UNSIGNED DEFAULT NULL COMMENT '图片高度',
    file_size BIGINT UNSIGNED DEFAULT NULL COMMENT '文件大小（字节）',
    uploader_ip VARCHAR(45) DEFAULT NULL COMMENT '上传者IP',
    upload_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP COMMENT '上传时间',
    tags VARCHAR(500) DEFAULT NULL COMMENT '标签（逗号分隔）',
    description TEXT DEFAULT NULL COMMENT '图片描述',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX idx_storage_type (storage_type),
    INDEX idx_device_type (device_type),
    INDEX idx_format (format),
    INDEX idx_upload_time (upload_time),
    INDEX idx_storage_device (storage_type, device_type)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='图片信息表';

-- 相册表
CREATE TABLE IF NOT EXISTS albums (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '相册ID',
    album_id VARCHAR(50) NOT NULL UNIQUE COMMENT '相册唯一标识',
    name VARCHAR(100) NOT NULL COMMENT '相册名称',
    description TEXT DEFAULT NULL COMMENT '相册描述',
    cover_image_id INT UNSIGNED DEFAULT NULL COMMENT '封面图片ID',
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '创建时间',
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP COMMENT '更新时间',
    INDEX idx_album_id (album_id),
    FOREIGN KEY (cover_image_id) REFERENCES images(id) ON DELETE SET NULL
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='相册表';

-- 相册图片关联表
CREATE TABLE IF NOT EXISTS album_images (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY COMMENT '关联ID',
    album_id INT UNSIGNED NOT NULL COMMENT '相册ID',
    image_id INT UNSIGNED NOT NULL COMMENT '图片ID',
    sort_order INT DEFAULT 0 COMMENT '排序顺序',
    added_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP COMMENT '添加时间',
    UNIQUE KEY uk_album_image (album_id, image_id),
    INDEX idx_album_id (album_id),
    INDEX idx_image_id (image_id),
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci COMMENT='相册图片关联表';

-- 插入初始数据示例（可选）
-- INSERT INTO images (filename, url, storage_type, device_type, format, uploader_ip) 
-- VALUES ('sample.jpg', 'https://example.com/sample.jpg', 'external', 'pc', 'jpeg', '127.0.0.1');

-- 创建普通用户并授予权限
CREATE USER IF NOT EXISTS 'DB_USER_PLACEHOLDER'@'%' IDENTIFIED BY 'DB_PASSWORD_PLACEHOLDER';
GRANT ALL PRIVILEGES ON DB_NAME_PLACEHOLDER.* TO 'DB_USER_PLACEHOLDER'@'%';
FLUSH PRIVILEGES;
