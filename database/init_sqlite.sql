-- PixPort SQLite 数据库初始化脚本
-- SQLite 轻量级数据库方案（内存占用 < 3MB）

-- 图片信息表
CREATE TABLE IF NOT EXISTS images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    filename TEXT NOT NULL,
    original_name TEXT DEFAULT NULL,
    url TEXT NOT NULL,
    storage_type TEXT NOT NULL DEFAULT 'local' CHECK(storage_type IN ('local', 'external')),
    device_type TEXT NOT NULL CHECK(device_type IN ('pc', 'pe')),
    format TEXT NOT NULL,
    local_path TEXT DEFAULT NULL,
    width INTEGER DEFAULT NULL,
    height INTEGER DEFAULT NULL,
    file_size INTEGER DEFAULT NULL,
    uploader_ip TEXT DEFAULT NULL,
    upload_time DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
    tags TEXT DEFAULT NULL,
    description TEXT DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP
);

-- 创建索引以优化查询性能
CREATE INDEX IF NOT EXISTS idx_storage_type ON images(storage_type);
CREATE INDEX IF NOT EXISTS idx_device_type ON images(device_type);
CREATE INDEX IF NOT EXISTS idx_format ON images(format);
CREATE INDEX IF NOT EXISTS idx_upload_time ON images(upload_time);
CREATE INDEX IF NOT EXISTS idx_storage_device ON images(storage_type, device_type);

-- 相册表
CREATE TABLE IF NOT EXISTS albums (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    album_id TEXT NOT NULL UNIQUE,
    name TEXT NOT NULL,
    description TEXT DEFAULT NULL,
    cover_image_id INTEGER DEFAULT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    updated_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (cover_image_id) REFERENCES images(id) ON DELETE SET NULL
);

-- 相册索引
CREATE INDEX IF NOT EXISTS idx_album_id ON albums(album_id);

-- 相册图片关联表
CREATE TABLE IF NOT EXISTS album_images (
    id INTEGER PRIMARY KEY AUTOINCREMENT,
    album_id INTEGER NOT NULL,
    image_id INTEGER NOT NULL,
    sort_order INTEGER DEFAULT 0,
    added_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (album_id) REFERENCES albums(id) ON DELETE CASCADE,
    FOREIGN KEY (image_id) REFERENCES images(id) ON DELETE CASCADE,
    UNIQUE(album_id, image_id)
);

-- 关联表索引
CREATE INDEX IF NOT EXISTS idx_album_images_album ON album_images(album_id);
CREATE INDEX IF NOT EXISTS idx_album_images_image ON album_images(image_id);

-- 创建触发器以自动更新 updated_at 字段
CREATE TRIGGER IF NOT EXISTS update_images_timestamp 
AFTER UPDATE ON images
BEGIN
    UPDATE images SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;

CREATE TRIGGER IF NOT EXISTS update_albums_timestamp 
AFTER UPDATE ON albums
BEGIN
    UPDATE albums SET updated_at = CURRENT_TIMESTAMP WHERE id = NEW.id;
END;
