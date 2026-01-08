<?php
// 密码保护验证
session_start();

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /index.php');
    exit;
}

// 引入数据库连接类
require_once __DIR__ . '/../../includes/Database.php';

// 加载系统配置
$systemConfigFile = dirname(__DIR__, 2) . '/config/system-config.json';
$systemConfig = [
    'background_url' => '/public/assets/images/home-backend.jpg'
];
if (file_exists($systemConfigFile)) {
    $loadedConfig = json_decode(file_get_contents($systemConfigFile), true);
    if (is_array($loadedConfig)) {
        $systemConfig = array_merge($systemConfig, $loadedConfig);
    }
}
$currentBg = $systemConfig['background_url'];

// 相册管理函数
function getAlbumsData() {
    $albumFile = __DIR__ . '/data/albums.json';
    if (!file_exists($albumFile)) {
        $dir = dirname($albumFile);
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        $defaultData = ['albums' => []];
        file_put_contents($albumFile, json_encode($defaultData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        return $defaultData;
    }
    return json_decode(file_get_contents($albumFile), true) ?? ['albums' => []];
}

function saveAlbumsData($data) {
    $albumFile = __DIR__ . '/data/albums.json';
    $dir = dirname($albumFile);
    if (!is_dir($dir)) {
        mkdir($dir, 0755, true);
    }
    return file_put_contents($albumFile, json_encode($data, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

// 处理相册相关请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    // 创建相册
    if ($_POST['action'] === 'create_album') {
        $albumName = trim($_POST['name'] ?? '');
        if (empty($albumName)) {
            echo json_encode(['success' => false, 'message' => '相册名称不能为空']);
            exit;
        }
        
        $data = getAlbumsData();
        $albumId = 'album_' . time() . '_' . uniqid();
        $data['albums'][$albumId] = [
            'name' => $albumName,
            'created_at' => date('Y-m-d H:i:s'),
            'images' => []
        ];
        
        if (saveAlbumsData($data)) {
            echo json_encode(['success' => true, 'message' => '相册创建成功', 'album_id' => $albumId]);
        } else {
            echo json_encode(['success' => false, 'message' => '相册创建失败']);
        }
        exit;
    }
    
    // 删除相册
    if ($_POST['action'] === 'delete_album') {
        $albumId = $_POST['album_id'] ?? '';
        if (empty($albumId)) {
            echo json_encode(['success' => false, 'message' => '相册ID不能为空']);
            exit;
        }
        
        $data = getAlbumsData();
        if (!isset($data['albums'][$albumId])) {
            echo json_encode(['success' => false, 'message' => '相册不存在']);
            exit;
        }
        
        unset($data['albums'][$albumId]);
        
        if (saveAlbumsData($data)) {
            echo json_encode(['success' => true, 'message' => '相册删除成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '相册删除失败']);
        }
        exit;
    }
    
    // 重命名相册
    if ($_POST['action'] === 'rename_album') {
        $albumId = $_POST['album_id'] ?? '';
        $newName = trim($_POST['name'] ?? '');
        
        if (empty($albumId) || empty($newName)) {
            echo json_encode(['success' => false, 'message' => '参数不完整']);
            exit;
        }
        
        $data = getAlbumsData();
        if (!isset($data['albums'][$albumId])) {
            echo json_encode(['success' => false, 'message' => '相册不存在']);
            exit;
        }
        
        $data['albums'][$albumId]['name'] = $newName;
        
        if (saveAlbumsData($data)) {
            echo json_encode(['success' => true, 'message' => '相册重命名成功']);
        } else {
            echo json_encode(['success' => false, 'message' => '相册重命名失败']);
        }
        exit;
    }
    
    // 添加图片到相册
    if ($_POST['action'] === 'add_to_album') {
        $albumId = $_POST['album_id'] ?? '';
        $imagePaths = json_decode($_POST['paths'] ?? '[]', true);
        
        if (empty($albumId) || empty($imagePaths)) {
            echo json_encode(['success' => false, 'message' => '参数不完整']);
            exit;
        }
        
        $data = getAlbumsData();
        if (!isset($data['albums'][$albumId])) {
            echo json_encode(['success' => false, 'message' => '相册不存在']);
            exit;
        }
        
        $added = 0;
        foreach ($imagePaths as $path) {
            if (!in_array($path, $data['albums'][$albumId]['images'])) {
                $data['albums'][$albumId]['images'][] = $path;
                $added++;
            }
        }
        
        if (saveAlbumsData($data)) {
            echo json_encode(['success' => true, 'message' => "成功添加 {$added} 张图片到相册", 'added' => $added]);
        } else {
            echo json_encode(['success' => false, 'message' => '添加失败']);
        }
        exit;
    }
    
    // 从相册移除图片
    if ($_POST['action'] === 'remove_from_album') {
        $albumId = $_POST['album_id'] ?? '';
        $imagePaths = json_decode($_POST['paths'] ?? '[]', true);
        
        if (empty($albumId) || empty($imagePaths)) {
            echo json_encode(['success' => false, 'message' => '参数不完整']);
            exit;
        }
        
        $data = getAlbumsData();
        if (!isset($data['albums'][$albumId])) {
            echo json_encode(['success' => false, 'message' => '相册不存在']);
            exit;
        }
        
        $removed = 0;
        foreach ($imagePaths as $path) {
            $key = array_search($path, $data['albums'][$albumId]['images']);
            if ($key !== false) {
                unset($data['albums'][$albumId]['images'][$key]);
                $removed++;
            }
        }
        
        // 重新索引数组
        $data['albums'][$albumId]['images'] = array_values($data['albums'][$albumId]['images']);
        
        if (saveAlbumsData($data)) {
            echo json_encode(['success' => true, 'message' => "成功从相册移除 {$removed} 张图片", 'removed' => $removed]);
        } else {
            echo json_encode(['success' => false, 'message' => '移除失败']);
        }
        exit;
    }
}

// 处理删除请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    if ($_POST['action'] === 'delete') {
        $imagePath = $_POST['path'] ?? '';
        $imageUrl = $_POST['url'] ?? '';
        
        $db = Database::getInstance();
        $success = false;
        $message = '';

        // 处理本地图片删除
        if (!empty($imagePath) && file_exists($imagePath)) {
            if (unlink($imagePath)) {
                // 从元数据中删除
                $dir = dirname($imagePath);
                $filename = basename($imagePath);
                $metaFile = $dir . '/.metadata.json';
                
                if (file_exists($metaFile)) {
                    $metadata = json_decode(file_get_contents($metaFile), true) ?? [];
                    if (isset($metadata[$filename])) {
                        unset($metadata[$filename]);
                        file_put_contents($metaFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                    }
                }
                
                // 从数据库中删除
                try {
                    $db->delete('images', 'local_path = ?', [$imagePath]);
                } catch (Exception $e) {
                    error_log("从数据库删除本地图片记录失败: " . $e->getMessage());
                }
                
                $success = true;
                $message = '删除成功';
            } else {
                $message = '文件删除失败';
            }
        } 
        // 处理外链图片删除
        elseif (!empty($imageUrl)) {
            try {
                $rowCount = $db->delete('images', "storage_type = 'external' AND url = ?", [$imageUrl]);
                if ($rowCount > 0) {
                    $success = true;
                    $message = '外链图片删除成功';
                } else {
                    $message = '未找到该外链图片记录';
                }
            } catch (Exception $e) {
                $message = '数据库删除失败: ' . $e->getMessage();
            }
        } else {
            $message = '图片路径无效或文件不存在';
        }

        if ($success) {
            // 异步刷新图片统计
            session_write_close();
            @file_get_contents('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/panel.php?refresh=stats', false, stream_context_create([
                'http' => ['timeout' => 1, 'ignore_errors' => true]
            ]));
            echo json_encode(['success' => true, 'message' => $message]);
        } else {
            echo json_encode(['success' => false, 'message' => $message]);
        }
        exit;
    }
    
    if ($_POST['action'] === 'batch_delete') {
        $items = json_decode($_POST['items'] ?? '[]', true);
        $deleted = 0;
        $failed = 0;
        
        $db = Database::getInstance();
        
        foreach ($items as $item) {
            $imagePath = $item['path'] ?? '';
            $imageUrl = $item['url'] ?? '';
            
            if (!empty($imagePath)) {
                // 本地删除
                if (file_exists($imagePath) && unlink($imagePath)) {
                    $dir = dirname($imagePath);
                    $filename = basename($imagePath);
                    $metaFile = $dir . '/.metadata.json';
                    
                    if (file_exists($metaFile)) {
                        $metadata = json_decode(file_get_contents($metaFile), true) ?? [];
                        if (isset($metadata[$filename])) {
                            unset($metadata[$filename]);
                            file_put_contents($metaFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
                        }
                    }
                    try {
                        $db->delete('images', 'local_path = ?', [$imagePath]);
                    } catch (Exception $e) {}
                    $deleted++;
                } else {
                    $failed++;
                }
            } elseif (!empty($imageUrl)) {
                // 外链删除
                try {
                    $rowCount = $db->delete('images', "storage_type = 'external' AND url = ?", [$imageUrl]);
                    if ($rowCount > 0) {
                        $deleted++;
                    } else {
                        $failed++;
                    }
                } catch (Exception $e) {
                    $failed++;
                }
            }
        }
        
        // 异步刷新图片统计
        session_write_close();
        @file_get_contents('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/panel.php?refresh=stats', false, stream_context_create([
            'http' => ['timeout' => 1, 'ignore_errors' => true]
        ]));
        
        echo json_encode([
            'success' => true,
            'message' => "已删除 {$deleted} 张图片" . ($failed > 0 ? "，{$failed} 张失败" : ""),
            'deleted' => $deleted,
            'failed' => $failed
        ]);
        exit;
    }
}

// 获取图片详细信息（支持分页）
function getImageDetails($page = 1, $perPage = 50, $type = 'all', $format = 'all', $storage = 'all', $sortBy = 'time_desc', $albumId = 'all') {
    $images = [];
    
    // 处理本地存储图片 - 优先从数据库读取
    if ($storage === 'all' || $storage === 'local') {
        try {
            $db = Database::getInstance();
            
            // 构建 SQL 查询
            $sql = "SELECT * FROM images WHERE storage_type = 'local'";
            $params = [];
            
            // 添加设备类型筛选
            if ($type !== 'all') {
                $sql .= " AND device_type = :device_type";
                $params['device_type'] = $type;
            }
            
            // 添加格式筛选
            if ($format !== 'all') {
                $sql .= " AND format = :format";
                $params['format'] = $format;
            }
            
            // 执行查询
            $dbImages = $db->fetchAll($sql, $params);
            
            foreach ($dbImages as $row) {
                // 检查文件是否还存在（数据库记录可能有，但文件已被删除）
                if ($row['local_path'] && !file_exists($row['local_path'])) {
                    continue;
                }
                
                // 原始文件名处理逻辑
                $originalName = $row['original_name'] ?: $row['filename'];
                
                // 如果数据库中没有原始文件名，尝试从元数据中读取
                if ($originalName === $row['filename'] && $row['local_path']) {
                    $dir = dirname($row['local_path']);
                    $metaFile = $dir . '/.metadata.json';
                    if (file_exists($metaFile)) {
                        $metadata = json_decode(file_get_contents($metaFile), true) ?: [];
                        if (isset($metadata[$row['filename']]['original_name'])) {
                            $originalName = $metadata[$row['filename']]['original_name'];
                        }
                    }
                }
                
                $images[] = [
                    'filename' => $row['filename'],
                    'original_name' => $originalName,
                    'path' => $row['local_path'],
                    'url' => $row['url'],
                    'size' => $row['file_size'] ?? 0,
                    'size_formatted' => formatBytes($row['file_size'] ?? 0),
                    'type' => $row['device_type'],
                    'format' => $row['format'],
                    'width' => $row['width'] ?? 0,
                    'height' => $row['height'] ?? 0,
                    'dimensions' => ($row['width'] && $row['height']) ? "{$row['width']}x{$row['height']}" : 'N/A',
                    'upload_time' => $row['upload_time'],
                    'uploader_ip' => $row['uploader_ip'] ?? 'N/A',
                    'timestamp' => strtotime($row['upload_time']),
                    'storage' => 'local'
                ];
            }
        } catch (Exception $e) {
            // 数据库读取失败，回退到文件系统读取
            error_log("从数据库读取图片失败，回退到文件系统: " . $e->getMessage());
            $images = array_merge($images, getImagesFromFileSystem($type, $format));
        }
    }
    
    // 处理外链图片 - 优先从数据库读取
    if ($storage === 'all' || $storage === 'external') {
        try {
            $db = Database::getInstance();
            
            // 构建 SQL 查询
            $sql = "SELECT * FROM images WHERE storage_type = 'external'";
            $params = [];
            
            // 添加设备类型筛选
            if ($type !== 'all') {
                $sql .= " AND device_type = :device_type";
                $params['device_type'] = $type;
            }
            
            // 添加格式筛选
            if ($format !== 'all') {
                $sql .= " AND format = :format";
                $params['format'] = $format;
            }
            
            // 执行查询
            $dbImages = $db->fetchAll($sql, $params);
            
            foreach ($dbImages as $row) {
                $originalName = $row['original_name'] ?: basename(parse_url($row['url'], PHP_URL_PATH));
                $images[] = [
                    'filename' => $row['filename'],
                    'original_name' => $originalName,
                    'path' => '',
                    'url' => $row['url'],
                    'size' => $row['file_size'] ?? 0,
                    'size_formatted' => $row['file_size'] ? formatBytes($row['file_size']) : 'N/A',
                    'type' => $row['device_type'],
                    'format' => $row['format'],
                    'width' => $row['width'] ?? 0,
                    'height' => $row['height'] ?? 0,
                    'dimensions' => ($row['width'] && $row['height']) ? "{$row['width']}x{$row['height']}" : 'N/A',
                    'upload_time' => $row['upload_time'],
                    'uploader_ip' => $row['uploader_ip'] ?? 'External',
                    'timestamp' => strtotime($row['upload_time']),
                    'storage' => 'external'
                ];
            }
        } catch (Exception $e) {
            // 数据库读取失败，回退到文件系统读取
            error_log("从数据库读取外链图片失败，回退到文件系统: " . $e->getMessage());
            $images = array_merge($images, getExternalImagesFromFileSystem($type, $format));
        }
    }
    
    // 相册筛选
    if ($albumId !== 'all') {
        $albumsData = getAlbumsData();
        if (isset($albumsData['albums'][$albumId])) {
            $albumImages = $albumsData['albums'][$albumId]['images'];
            $images = array_filter($images, function($img) use ($albumImages) {
                return in_array($img['path'], $albumImages) || (empty($img['path']) && in_array($img['url'], $albumImages));
            });
        }
    }
    
    // 排序逻辑
    usort($images, function($a, $b) use ($sortBy) {
        switch ($sortBy) {
            case 'time_asc':
                return $a['timestamp'] - $b['timestamp'];
            case 'time_desc':
            default:
                return $b['timestamp'] - $a['timestamp'];
            case 'name_asc':
                return strcmp($a['filename'], $b['filename']);
            case 'name_desc':
                return strcmp($b['filename'], $a['filename']);
            case 'size_asc':
                return $a['size'] - $b['size'];
            case 'size_desc':
                return $b['size'] - $a['size'];
        }
    });
    
    $total = count($images);
    $offset = ($page - 1) * $perPage;
    $images = array_slice($images, $offset, $perPage);
    
    return ['images' => $images, 'total' => $total];
}

// 从文件系统读取图片（向后兼容）
function getImagesFromFileSystem($type = 'all', $format = 'all') {
    $images = [];
    $imageDir = dirname(__DIR__, 1) . '/images';
    
    if (!is_dir($imageDir)) {
        return $images;
    }
    
    $types = ($type === 'all') ? ['pc', 'pe'] : [$type];
    $formats = ($format === 'all') ? ['jpeg', 'webp', 'avif', 'png', 'gif'] : [$format];
    
    foreach ($types as $deviceType) {
        $typeDir = $imageDir . '/' . $deviceType;
        if (!is_dir($typeDir)) continue;
        
        // 扫描扁平化目录
        $files = glob($typeDir . '/*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE);
        
        // 读取该目录下的元数据
        $rootMetaFile = $typeDir . '/.metadata.json';
        $rootMetadata = [];
        if (file_exists($rootMetaFile)) {
            $rootMetadata = json_decode(file_get_contents($rootMetaFile), true) ?? [];
        }
        
        foreach ($files as $filePath) {
            $filename = basename($filePath);
            $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
            if ($ext === 'jpg') $ext = 'jpeg';
            
            // 如果指定了格式筛选，且不匹配，则跳过
            if ($format !== 'all' && $ext !== $format) continue;
            
            $images[] = processFileSystemImage($filePath, $rootMetadata, $deviceType, $ext);
        }
        
        // 扫描旧的格式子目录结构
        foreach ($formats as $imageFormat) {
            $formatDir = $typeDir . '/' . $imageFormat;
            if (!is_dir($formatDir)) continue;
            
            $metaFile = $formatDir . '/.metadata.json';
            $metadata = [];
            if (file_exists($metaFile)) {
                $metadata = json_decode(file_get_contents($metaFile), true) ?? [];
            }
            
            $subFiles = glob($formatDir . '/*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE);
            foreach ($subFiles as $filePath) {
                $images[] = processFileSystemImage($filePath, $metadata, $deviceType, $imageFormat);
            }
        }
    }
    
    return $images;
}

// 辅助函数：处理单个文件系统图片数据
function processFileSystemImage($filePath, $metadata, $deviceType, $imageFormat) {
    $filename = basename($filePath);
    $fileSize = filesize($filePath);
    $fileMtime = filemtime($filePath);
    
    // 获取图片尺寸
    $imageSize = @getimagesize($filePath);
    $width = $imageSize ? $imageSize[0] : 0;
    $height = $imageSize ? $imageSize[1] : 0;
    
    // 生成访问URL
    $baseUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'];
    $relativePath = str_replace(dirname(__DIR__, 2), '', realpath($filePath));
    $relativePath = str_replace('\\', '/', $relativePath); // Windows 兼容
    $url = $baseUrl . $relativePath;
    
    // 获取上传者信息
    $uploaderIP = 'N/A';
    $uploadTime = date('Y-m-d H:i:s', $fileMtime);
    $originalName = $filename;
    
    if (isset($metadata[$filename])) {
        $uploaderIP = $metadata[$filename]['uploader_ip'] ?? 'N/A';
        $uploadTime = $metadata[$filename]['upload_time'] ?? $uploadTime;
        $originalName = $metadata[$filename]['original_name'] ?? $filename;
    }
    
    return [
        'filename' => $filename,
        'original_name' => $originalName,
        'path' => $filePath,
        'url' => $url,
        'size' => $fileSize,
        'size_formatted' => formatBytes($fileSize),
        'type' => $deviceType,
        'format' => $imageFormat,
        'width' => $width,
        'height' => $height,
        'dimensions' => ($width && $height) ? "{$width}x{$height}" : 'N/A',
        'upload_time' => $uploadTime,
        'uploader_ip' => $uploaderIP,
        'timestamp' => $fileMtime,
        'storage' => 'local'
    ];
}

// 从文件系统读取外链图片（向后兼容）
function getExternalImagesFromFileSystem($type = 'all', $format = 'all') {
    $images = [];
    $externalDir = __DIR__ . '/external-images';
    
    if (!is_dir($externalDir)) {
        return $images;
    }
    
    $types = ($type === 'all') ? ['pc', 'pe'] : [$type];
    $formats = ($format === 'all') ? ['jpeg', 'webp', 'avif', 'png', 'gif'] : [$format];
    
    foreach ($types as $deviceType) {
        $typeDir = $externalDir . '/' . $deviceType;
        if (!is_dir($typeDir)) continue;
        
        foreach ($formats as $imageFormat) {
            $formatDir = $typeDir . '/' . $imageFormat;
            if (!is_dir($formatDir)) continue;
            
            // 读取外链列表文件
            $linkFile = $formatDir . '/' . $deviceType . '-' . $imageFormat . '.txt';
            if (file_exists($linkFile)) {
                $links = file($linkFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                $index = 1;
                foreach ($links as $link) {
                    $link = trim($link);
                    if (empty($link) || strpos($link, '#') === 0) continue;
                    
                    $images[] = [
                        'filename' => 'external_' . $deviceType . '_' . $imageFormat . '_' . $index,
                        'original_name' => basename(parse_url($link, PHP_URL_PATH)),
                        'path' => '',
                        'url' => $link,
                        'size' => 0,
                        'size_formatted' => 'N/A',
                        'type' => $deviceType,
                        'format' => $imageFormat,
                        'width' => 0,
                        'height' => 0,
                        'dimensions' => 'N/A',
                        'upload_time' => 'N/A',
                        'uploader_ip' => 'External',
                        'timestamp' => time(),
                        'storage' => 'external'
                    ];
                    $index++;
                }
            }
        }
    }
    
    return $images;
}

// 格式化字节数
function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < 3; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// 获取筛选参数
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = isset($_GET['per_page']) ? intval($_GET['per_page']) : 10;
// 限制每页显示数量在有效范围内
if (!in_array($perPage, [10, 20, 50])) {
    $perPage = 10;
}
$filterType = isset($_GET['type']) ? $_GET['type'] : 'all';
$filterFormat = isset($_GET['format']) ? $_GET['format'] : 'all';
$filterStorage = isset($_GET['storage']) ? $_GET['storage'] : 'all';
$sortBy = isset($_GET['sort']) ? $_GET['sort'] : 'time_desc';
$filterAlbum = isset($_GET['album']) ? $_GET['album'] : 'all';

// 获取相册数据
$albumsData = getAlbumsData();
$albums = $albumsData['albums'];

$result = getImageDetails($page, $perPage, $filterType, $filterFormat, $filterStorage, $sortBy, $filterAlbum);
$images = $result['images'];
$totalImages = $result['total'];
$totalPages = ceil($totalImages / $perPage);
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>画廊 - PixPort</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: url('<?php echo $currentBg; ?>') no-repeat center center fixed;
            background-size: cover;
            min-height: 100vh;
            padding: 20px;
            position: relative;
        }
        body::before {
            content: '';
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0, 0, 0, 0.2);
            z-index: 0;
        }
        .header {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 20px 30px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            align-items: center;
            justify-content: space-between;
            position: relative;
            z-index: 1;
        }
        .header h1 {
            display: flex;
            align-items: center;
            gap: 10px;
            font-size: 24px;
            color: white;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
            margin: 0;
        }
        .header .logo-img {
            height: 48px;
            width: auto;
        }
        .logout-btn {
            padding: 10px 20px;
            background: #dc3545;
            color: white;
            text-decoration: none;
            border-radius: 8px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .logout-btn:hover {
            background: #c82333;
            transform: translateY(-2px);
        }
        /* 侧边栏样式 */
        .sidebar {
            position: fixed;
            left: 10px;
            top: 50%;
            transform: translateY(-50%);
            display: flex;
            flex-direction: column;
            gap: 10px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(25px);
            -webkit-backdrop-filter: blur(25px);
            border-radius: 16px;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            z-index: 1000;
            transition: width 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            width: 200px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.2);
            overflow: hidden;
        }
        .sidebar.collapsed {
            width: 66px;
        }
        .nav-item {
            display: flex;
            align-items: center;
            gap: 12px;
            padding: 12px 18px;
            background: rgba(255, 255, 255, 0.05);
            border: 1px solid rgba(255, 255, 255, 0.1);
            border-radius: 10px;
            color: rgba(255, 255, 255, 0.8);
            cursor: pointer;
            font-weight: 600;
            transition: all 0.3s;
            white-space: nowrap;
            width: 100%;
            justify-content: flex-start;
            font-size: 15px;
            text-decoration: none;
        }
        .nav-item:hover {
            background: rgba(255, 255, 255, 0.15);
            color: white;
        }
        .nav-item.active {
            background: rgba(255, 255, 255, 0.25);
            color: white;
            border-color: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.15);
        }
        .sidebar.collapsed .nav-item {
            padding: 12px;
            justify-content: center;
        }
        .sidebar.collapsed .btn-text {
            display: none;
        }
        .toggle-btn {
            margin-top: 5px;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
            padding-top: 15px;
            background: transparent !important;
            border: none !important;
            box-shadow: none !important;
            justify-content: center;
            font-size: 20px;
            cursor: pointer;
            color: white;
            display: flex;
            width: 100%;
        }
        
        /* 悬浮退出按钮 */
        .floating-logout {
            position: fixed;
            bottom: 30px;
            right: 30px;
            width: 54px;
            height: 54px;
            background: rgba(220, 53, 69, 0.2);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            color: white;
            text-decoration: none;
            z-index: 9999;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
        }
        .floating-logout:hover {
            background: rgba(220, 53, 69, 0.5);
            transform: scale(1.1) rotate(-10deg);
            border-color: rgba(255, 255, 255, 0.5);
            box-shadow: 0 12px 40px rgba(220, 53, 69, 0.4);
        }
        .floating-logout svg {
            width: 26px;
            height: 26px;
        }
        .container {
            max-width: 1400px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }
        .filter-bar {
            display: flex;
            gap: 15px;
            margin-bottom: 25px;
            flex-wrap: wrap;
            align-items: center;
        }
        .filter-group {
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .filter-group label {
            color: white;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .filter-group select {
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .filter-group select option {
            background: #2d2d2d;
            color: white;
        }
        .stats-info {
            color: white;
            font-weight: 600;
            margin-left: auto;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .batch-actions {
            display: flex;
            gap: 10px;
            align-items: center;
        }
        .batch-btn {
            padding: 8px 16px;
            border: none;
            border-radius: 6px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
        }
        .batch-btn.select-all {
            background: rgba(102, 126, 234, 0.8);
        }
        .batch-btn.select-all:hover {
            background: rgba(102, 126, 234, 1);
        }
        .batch-btn.delete-selected {
            background: rgba(220, 53, 69, 0.8);
        }
        .batch-btn.delete-selected:hover {
            background: rgba(220, 53, 69, 1);
        }
        .batch-btn.add-to-album,
        .batch-btn.remove-from-album {
            background: rgba(40, 167, 69, 0.8);
        }
        .batch-btn.add-to-album:hover,
        .batch-btn.remove-from-album:hover {
            background: rgba(40, 167, 69, 1);
        }
        .batch-btn.manage-album {
            background: rgba(255, 193, 7, 0.8);
            color: #333;
        }
        .batch-btn.manage-album:hover {
            background: rgba(255, 193, 7, 1);
        }
        .batch-btn:disabled {
            opacity: 0.5;
            cursor: not-allowed;
        }
        .gallery-grid {
            display: grid;
            grid-template-columns: repeat(5, 1fr);
            gap: 20px;
            margin-bottom: 30px;
        }
        .image-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 10px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.2);
            cursor: pointer;
            transition: all 0.3s;
            position: relative;
        }
        .image-card.selected {
            border-color: #667eea;
            box-shadow: 0 0 20px rgba(102, 126, 234, 0.5);
        }
        .select-checkbox {
            position: absolute;
            top: 10px;
            left: 10px;
            width: 24px;
            height: 24px;
            cursor: pointer;
            z-index: 10;
            opacity: 0.8;
        }
        .select-checkbox:hover {
            opacity: 1;
            transform: scale(1.1);
        }
        .image-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 25px rgba(0, 0, 0, 0.3);
            border-color: rgba(255, 255, 255, 0.4);
        }
        .image-card img {
            width: 100%;
            height: 200px;
            object-fit: cover;
            display: block;
        }
        .image-info {
            padding: 12px;
        }
        .image-name {
            color: white;
            font-size: 14px;
            font-weight: 600;
            margin-bottom: 8px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .image-meta {
            display: flex;
            gap: 8px;
            flex-wrap: wrap;
        }
        .meta-tag {
            padding: 3px 8px;
            border-radius: 4px;
            font-size: 11px;
            font-weight: 600;
        }
        .meta-tag.type-pc { background: rgba(102, 126, 234, 0.8); color: white; }
        .meta-tag.type-pe { background: rgba(118, 75, 162, 0.8); color: white; }
        .meta-tag.format { background: rgba(75, 192, 192, 0.8); color: white; }
        .meta-tag.size { background: rgba(255, 159, 64, 0.8); color: white; }
        .meta-tag.storage { background: rgba(153, 102, 255, 0.8); color: white; }
        
        /* 自定义相册下拉框 */
        .album-select-wrapper {
            position: relative;
            min-width: 220px;
            z-index: 100;
        }
        .album-current-selected {
            padding: 8px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            cursor: pointer;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .album-options-list {
            display: none;
            position: absolute;
            top: 100%;
            left: 0;
            right: 0;
            margin-top: 5px;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            border: 1px solid rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            box-shadow: 0 15px 35px rgba(0, 0, 0, 0.2);
            max-height: 400px;
            overflow-y: auto;
            overflow-x: hidden;
        }
        .album-options-list.show {
            display: block;
        }
        .album-option-item {
            padding: 12px 15px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            color: #333;
            cursor: pointer;
            transition: all 0.2s;
            border-bottom: 1px solid rgba(0, 0, 0, 0.05);
        }
        .album-option-item:hover {
            background: rgba(102, 126, 234, 0.1);
            color: #667eea;
        }
        .album-option-item.active {
            background: rgba(102, 126, 234, 0.15);
            color: #667eea;
            font-weight: 600;
        }
        .album-name-text {
            flex: 1;
            font-size: 14px;
            white-space: nowrap;
            overflow: hidden;
            text-overflow: ellipsis;
        }
        .album-item-actions {
            display: flex;
            gap: 8px;
            margin-left: 10px;
        }
        .action-icon-btn {
            background: transparent;
            border: none;
            color: #999;
            cursor: pointer;
            padding: 6px;
            display: flex;
            align-items: center;
            justify-content: center;
            border-radius: 6px;
            transition: all 0.2s;
        }
        .action-icon-btn:hover {
            background: rgba(0, 0, 0, 0.05);
            color: #667eea;
        }
        .action-icon-btn svg {
            width: 16px;
            height: 16px;
        }
        .album-create-row {
            padding: 14px 15px;
            background: rgba(40, 167, 69, 0.08);
            color: #28a745;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 10px;
            font-weight: 700;
            cursor: pointer;
            transition: all 0.2s;
            border-top: 1px solid rgba(0, 0, 0, 0.05);
            position: sticky;
            bottom: 0;
            z-index: 10;
        }
        .album-create-row:hover {
            background: rgba(40, 167, 69, 0.15);
        }
        .album-inline-input {
            background: #fff;
            border: 1px solid #e0e0e0;
            border-radius: 8px;
            color: #333;
            padding: 6px 35px 6px 12px;
            width: 100%;
            font-size: 14px;
            outline: none;
            box-shadow: inset 0 1px 3px rgba(0,0,0,0.05);
            transition: all 0.2s;
        }
        .album-inline-input:focus {
            border-color: #28a745;
            box-shadow: 0 0 0 3px rgba(40, 167, 69, 0.1);
        }
        .album-rename-input {
            border-color: #667eea;
        }
        .album-rename-input:focus {
            border-color: #667eea;
            box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
        }
        .album-input-wrapper {
            position: relative;
            display: none;
            width: 100%;
            padding: 2px 0;
        }
        .album-input-wrapper.show {
            display: block;
        }
        .album-confirm-btn {
            position: absolute;
            right: 6px;
            top: 50%;
            transform: translateY(-50%);
            width: 26px;
            height: 26px;
            background: #28a745;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            padding: 4px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.2s;
            box-shadow: 0 2px 4px rgba(40, 167, 69, 0.2);
        }
        .album-confirm-btn:hover {
            background: #218838;
            transform: translateY(-50%) scale(1.05);
            box-shadow: 0 4px 8px rgba(40, 167, 69, 0.3);
        }
        .album-confirm-btn svg {
            width: 14px;
            height: 14px;
            fill: white;
        }
        .album-create-row svg {
            width: 18px;
            height: 18px;
        }
        
        /* 模态框 */
        .modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            max-width: 90%;
            max-height: 90vh;
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            overflow: hidden;
            display: flex;
            gap: 20px;
            padding: 20px;
            /* 优化垂直居中布局 */
            align-items: center;
        }
        @media (min-width: 769px) {
            .modal-content {
                max-width: 85%;
            }
            /* 当显示移动端图片时，调整布局 */
            .modal-content.mobile-image {
                max-width: 75%;
            }
        }
        @media (max-width: 768px) {
            .modal-content {
                flex-direction: column;
                max-width: 95%;
                max-height: 95%;
                overflow-y: auto;
                padding: 15px;
                gap: 15px;
            }
            .modal-details {
                width: 100% !important;
            }
        }
        .modal-image {
            flex: 1;
            display: flex;
            align-items: center;
            justify-content: center;
            min-width: 0;
        }
        .modal-image img {
            max-width: 100%;
            max-height: 50vh;
            object-fit: contain;
            border-radius: 8px;
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3Cline x1='11' y1='8' x2='11' y2='14'%3E%3C/line%3E%3Cline x1='8' y1='11' x2='14' y2='11'%3E%3C/line%3E%3C/svg%3E") 16 16, zoom-in;
            transition: transform 0.3s cubic-bezier(0.4, 0, 0.2, 1);
        }
        .modal-image img:hover {
            transform: scale(1.03);
            filter: brightness(1.1);
        }
        
        /* 全屏查看器 */
        .fullscreen-viewer {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.98);
            z-index: 10001;
            cursor: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='32' height='32' viewBox='0 0 24 24' fill='none' stroke='white' stroke-width='2' stroke-linecap='round' stroke-linejoin='round'%3E%3Ccircle cx='11' cy='11' r='8'%3E%3C/circle%3E%3Cline x1='21' y1='21' x2='16.65' y2='16.65'%3E%3C/line%3E%3Cline x1='8' y1='11' x2='14' y2='11'%3E%3C/line%3E%3C/svg%3E") 16 16, zoom-out;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.4s ease;
        }
        .fullscreen-viewer.active {
            display: flex;
            opacity: 1;
        }
        .fullscreen-viewer img {
            max-width: 95%;
            max-height: 95%;
            object-fit: contain;
            box-shadow: 0 0 30px rgba(0, 0, 0, 0.5);
            user-select: none;
        }
        @media (min-width: 769px) {
            .modal-content.mobile-image .modal-image img {
                max-height: 60vh;
            }
        }
        .modal-image-wrapper {
            flex: 1;
            display: flex;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            min-width: 0;
            height: 100%;
        }
        .modal-details {
            width: 320px;
            max-height: calc(90vh - 40px);
            color: white;
            overflow-y: auto;
            flex-shrink: 0;
            padding-left: 10px;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
        }
        @media (max-width: 768px) {
            .modal-details {
                width: 100% !important;
                border-left: none;
                border-top: 1px solid rgba(255, 255, 255, 0.1);
                padding-top: 20px;
                padding-left: 0;
            }
        }
        @media (min-width: 769px) {
            .modal-content.mobile-image .modal-details {
                width: 280px;
            }
        }
        .modal-details h3 {
            margin-bottom: 15px;
            padding-bottom: 10px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
            font-size: 22px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .delete-single-btn {
            position: absolute;
            top: 25px;
            right: 85px;
            width: 44px;
            height: 44px;
            background: rgba(255, 255, 255, 0.1);
            color: #ff4d4f;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 10px;
            cursor: pointer;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: all 0.3s;
            z-index: 10000;
            backdrop-filter: blur(5px);
        }
        .delete-single-btn:hover {
            background: rgba(220, 53, 69, 0.8);
            color: white;
            border-color: transparent;
            transform: translateY(-2px);
            box-shadow: 0 5px 15px rgba(220, 53, 69, 0.4);
        }
        .delete-single-btn svg {
            width: 24px;
            height: 24px;
        }
        .detail-item {
            margin-bottom: 10px;
            display: flex;
            align-items: center;
            justify-content: space-between;
            gap: 10px;
        }
        .detail-label {
            font-weight: 600;
            opacity: 0.6;
            font-size: 13px;
            white-space: nowrap;
            flex-shrink: 0;
        }
        .detail-value {
            font-size: 13px;
            padding: 4px 8px;
            background: rgba(0, 0, 0, 0.2);
            border-radius: 4px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
            flex: 1;
            text-align: right;
            max-width: 200px;
        }
        .tab-container {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 12px;
            margin-top: 35px;
            width: 100%;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .tab-buttons {
            display: flex;
            gap: 8px;
            margin-bottom: 10px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
            flex-wrap: wrap;
        }
        .tab-buttons .tab-btn {
            padding: 8px 12px;
            border: none;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            cursor: pointer;
            border-radius: 6px 6px 0 0;
            transition: all 0.3s;
            font-size: 13px;
        }
        .tab-buttons .tab-btn.active {
            background: rgba(255, 255, 255, 0.4);
            color: white;
        }
        .tab-content {
            margin-bottom: 0;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .url-input-wrapper {
            position: relative;
            margin-bottom: 0;
        }
        .link-input {
            width: 100%;
            padding: 8px 40px 8px 8px;
            border-radius: 6px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(0, 0, 0, 0.2);
            color: white;
            font-size: 12px;
            font-family: 'Consolas', monospace;
        }
        .copy-inline-btn {
            position: absolute;
            right: 4px;
            top: 50%;
            transform: translateY(-50%);
            padding: 6px;
            background: rgba(102, 126, 234, 0.9);
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
            line-height: 0;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .copy-inline-btn svg {
            width: 16px;
            height: 16px;
            stroke: currentColor;
        }
        .copy-inline-btn:hover {
            background: rgba(102, 126, 234, 1);
            transform: translateY(-50%) scale(1.05);
        }
        .copy-inline-btn.copied {
            background: rgba(40, 167, 69, 0.9);
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 30px;
            font-size: 40px;
            color: white;
            cursor: pointer;
            font-weight: 300;
            line-height: 1;
            transition: all 0.3s;
        }
        .close-btn:hover {
            transform: scale(1.2);
        }
        .notification {
            position: fixed;
            top: 20px;
            right: 20px;
            padding: 15px 25px;
            background: rgba(40, 167, 69, 0.95);
            color: white;
            border-radius: 8px;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.3);
            z-index: 99999;
            animation: slideIn 0.3s ease-out;
            font-weight: 600;
        }
        .notification.error {
            background: rgba(220, 53, 69, 0.95);
        }
        @keyframes slideIn {
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        
        /* 分页 */
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 30px;
        }
        .pagination a, .pagination span {
            padding: 8px 15px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            text-decoration: none;
            border-radius: 6px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.4);
            transform: translateY(-2px);
        }
        .pagination .active {
            background: rgba(102, 126, 234, 0.8);
        }
        .empty-state {
            text-align: center;
            padding: 60px 20px;
            color: white;
        }
        .empty-state h3 {
            font-size: 24px;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        
        /* 相册管理弹窗 */
        .album-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .album-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .album-modal-content {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            max-width: 600px;
            width: 90%;
            max-height: 80vh;
            overflow-y: auto;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
        }
        .album-modal h2 {
            color: white;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .album-input-group {
            display: flex;
            gap: 10px;
            margin-bottom: 20px;
        }
        .album-input {
            flex: 1;
            padding: 10px 15px;
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .album-input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        .album-btn {
            padding: 10px 20px;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
        }
        .album-btn.create {
            background: rgba(40, 167, 69, 0.8);
        }
        .album-btn.create:hover {
            background: rgba(40, 167, 69, 1);
        }
        .album-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-bottom: 20px;
        }
        .album-item {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        .album-item-info {
            color: white;
            flex: 1;
        }
        .album-item-name {
            font-weight: 600;
            font-size: 16px;
            margin-bottom: 5px;
        }
        .album-item-meta {
            font-size: 12px;
            opacity: 0.8;
        }
        .album-item-actions {
            display: flex;
            gap: 8px;
        }
        .album-btn.view {
            background: rgba(23, 162, 184, 0.8);
            padding: 6px 12px;
            font-size: 13px;
        }
        .album-btn.view:hover {
            background: rgba(23, 162, 184, 1);
        }
        .album-btn.rename {
            background: rgba(102, 126, 234, 0.8);
            padding: 6px 12px;
            font-size: 13px;
        }
        .album-btn.rename:hover {
            background: rgba(102, 126, 234, 1);
        }
        .album-btn.delete {
            background: rgba(220, 53, 69, 0.8);
            padding: 6px 12px;
            font-size: 13px;
        }
        /* 状态遮罩层 */
        .status-overlay {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.85);
            backdrop-filter: blur(15px);
            z-index: 100000;
            flex-direction: column;
            align-items: center;
            justify-content: center;
            color: white;
            opacity: 0;
            transition: opacity 0.4s ease;
        }
        .status-overlay.active {
            display: flex;
            opacity: 1;
        }
        .status-icon {
            width: 80px;
            height: 80px;
            margin-bottom: 20px;
        }
        .status-text {
            font-size: 22px;
            font-weight: 600;
            letter-spacing: 1px;
        }
        .spinner {
            width: 60px;
            height: 60px;
            border: 5px solid rgba(255, 255, 255, 0.1);
            border-top: 5px solid #667eea;
            border-radius: 50%;
            animation: spin 1s linear infinite;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* 优雅的确认对话框 */
        .confirm-modal {
            display: none;
            position: fixed;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(8px);
            z-index: 99998;
            align-items: center;
            justify-content: center;
            opacity: 0;
            transition: all 0.3s ease;
        }
        .confirm-modal.active {
            display: flex;
            opacity: 1;
        }
        .confirm-content {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 16px;
            padding: 35px;
            width: 90%;
            max-width: 420px;
            text-align: center;
            color: white;
            transform: scale(0.9);
            transition: all 0.3s cubic-bezier(0.34, 1.56, 0.64, 1);
        }
        .confirm-modal.active .confirm-content {
            transform: scale(1);
        }
        .confirm-title {
            font-size: 20px;
            font-weight: 700;
            margin-bottom: 15px;
            color: #ff4d4f;
        }
        .confirm-message {
            font-size: 16px;
            opacity: 0.9;
            margin-bottom: 30px;
            line-height: 1.6;
        }
        .confirm-btns {
            display: flex;
            gap: 15px;
            justify-content: center;
        }
        .confirm-btn {
            padding: 12px 25px;
            border-radius: 10px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            border: none;
            min-width: 100px;
        }
        .confirm-btn.cancel {
            background: rgba(255, 255, 255, 0.1);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .confirm-btn.cancel:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .confirm-btn.danger {
            background: #ff4d4f;
            color: white;
            box-shadow: 0 4px 15px rgba(255, 77, 79, 0.3);
        }
        .confirm-btn.danger:hover {
            background: #ff7875;
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(255, 77, 79, 0.4);
        }
            background: rgba(220, 53, 69, 1);
        }
        .album-select-close {
            position: absolute;
            top: 15px;
            right: 15px;
            width: 32px;
            height: 32px;
            color: rgba(255, 255, 255, 0.4);
            cursor: pointer;
            transition: all 0.3s cubic-bezier(0.4, 0, 0.2, 1);
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
        }
        .album-select-close:hover {
            color: #ff4d4f;
            transform: rotate(90deg) scale(1.1);
        }
        .album-select-close svg {
            width: 24px;
            height: 24px;
        }
        .album-select-modal {
            display: none;
            position: fixed;
            z-index: 9999;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.7);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
        }
        .album-select-modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .album-select-content {
            background: rgba(255, 255, 255, 0.15);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
        }
        .album-select-list {
            display: flex;
            flex-direction: column;
            gap: 10px;
            margin-top: 20px;
        }
        .album-select-item {
            background: rgba(255, 255, 255, 0.2);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 8px;
            padding: 15px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            font-weight: 600;
        }
        .album-select-item.create-mode {
            cursor: default;
            padding: 10px 15px;
        }
        .album-select-item .create-label {
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            width: 100%;
            cursor: pointer;
        }
        .album-select-item .create-input-wrapper {
            display: none;
            align-items: center;
            gap: 10px;
            width: 100%;
        }
        .album-select-item.active-input .create-label {
            display: none;
        }
        .album-select-item.active-input .create-input-wrapper {
            display: flex;
        }
        .create-inline-input {
            flex: 1;
            background: rgba(0, 0, 0, 0.2);
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 6px;
            padding: 8px 12px;
            color: white;
            font-size: 14px;
            outline: none;
        }
        .create-inline-input:focus {
            border-color: #28a745;
            background: rgba(0, 0, 0, 0.3);
        }
        .create-confirm-btn {
            background: transparent;
            border: none;
            color: #28a745;
            cursor: pointer;
            padding: 5px;
            display: flex;
            align-items: center;
            justify-content: center;
            transition: transform 0.2s;
            line-height: 0;
        }
        .create-confirm-btn:hover {
            transform: scale(1.2);
        }
        .create-confirm-btn svg {
            width: 24px;
            height: 24px;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/upload.php" style="text-decoration: none;">
            <h1 style="cursor: pointer;">
                <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
                <span>- 图片画廊</span>
            </h1>
        </a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="/upload.php" class="nav-item">
            <span class="btn-icon">📤</span>
            <span class="btn-text">上传图片</span>
        </a>
        <div class="nav-item active">
            <span class="btn-icon">🎨</span>
            <span class="btn-text">图片画廊</span>
        </div>
        <a href="/panel.php" class="nav-item">
            <span class="btn-icon">📊</span>
            <span class="btn-text">监控面板</span>
        </a>
        <a href="/api-panel.php" class="nav-item">
            <span class="btn-icon">🔧</span>
            <span class="btn-text">API管理</span>
        </a>
        <a href="/system-panel.php" class="nav-item">
            <span class="btn-icon">⚙️</span>
            <span class="btn-text">系统设置</span>
        </a>
        <div class="toggle-btn" onclick="toggleSidebar()">
            <span id="toggleIcon">⬅️</span>
        </div>
    </div>

    <div class="container">
        <div class="filter-bar">
            <div class="filter-group">
                <label>设备类型:</label>
                <select onchange="updateFilter('type', this.value)">
                    <option value="all" <?php echo $filterType === 'all' ? 'selected' : ''; ?>>全部</option>
                    <option value="pc" <?php echo $filterType === 'pc' ? 'selected' : ''; ?>>PC端</option>
                    <option value="pe" <?php echo $filterType === 'pe' ? 'selected' : ''; ?>>移动端</option>
                </select>
            </div>
            <div class="filter-group">
                <label>图片格式:</label>
                <select onchange="updateFilter('format', this.value)">
                    <option value="all" <?php echo $filterFormat === 'all' ? 'selected' : ''; ?>>全部</option>
                    <option value="jpeg" <?php echo $filterFormat === 'jpeg' ? 'selected' : ''; ?>>JPEG</option>
                    <option value="png" <?php echo $filterFormat === 'png' ? 'selected' : ''; ?>>PNG</option>
                    <option value="gif" <?php echo $filterFormat === 'gif' ? 'selected' : ''; ?>>GIF</option>
                    <option value="webp" <?php echo $filterFormat === 'webp' ? 'selected' : ''; ?>>WebP</option>
                    <option value="avif" <?php echo $filterFormat === 'avif' ? 'selected' : ''; ?>>AVIF</option>
                </select>
            </div>
            <div class="filter-group">
                <label>存储方式:</label>
                <select onchange="updateFilter('storage', this.value)">
                    <option value="all" <?php echo $filterStorage === 'all' ? 'selected' : ''; ?>>全部</option>
                    <option value="local" <?php echo $filterStorage === 'local' ? 'selected' : ''; ?>>本地存储</option>
                    <option value="external" <?php echo $filterStorage === 'external' ? 'selected' : ''; ?>>外链存储</option>
                </select>
            </div>
            <div class="filter-group">
                <label>排序方式:</label>
                <select onchange="updateFilter('sort', this.value)">
                    <option value="time_desc" <?php echo $sortBy === 'time_desc' ? 'selected' : ''; ?>>时间↓(新→旧)</option>
                    <option value="time_asc" <?php echo $sortBy === 'time_asc' ? 'selected' : ''; ?>>时间↑(旧→新)</option>
                    <option value="name_asc" <?php echo $sortBy === 'name_asc' ? 'selected' : ''; ?>>文件名↑(A→Z)</option>
                    <option value="name_desc" <?php echo $sortBy === 'name_desc' ? 'selected' : ''; ?>>文件名↓(Z→A)</option>
                    <option value="size_asc" <?php echo $sortBy === 'size_asc' ? 'selected' : ''; ?>>文件大小↑(小→大)</option>
                    <option value="size_desc" <?php echo $sortBy === 'size_desc' ? 'selected' : ''; ?>>文件大小↓(大→小)</option>
                </select>
            </div>
            <div class="filter-group">
                <label>相册:</label>
                <div class="album-select-wrapper" id="albumSelectWrapper">
                    <div class="album-current-selected" onclick="toggleAlbumDropdown(event)">
                        <span>
                            <?php 
                            if ($filterAlbum === 'all') {
                                echo '全部图片';
                            } else {
                                echo htmlspecialchars($albums[$filterAlbum]['name'] ?? '全部图片');
                            }
                            ?>
                        </span>
                        <svg width="12" height="12" viewBox="0 0 12 12" fill="none" style="margin-left: 8px; opacity: 0.6;">
                            <path d="M2 4L6 8L10 4" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round"/>
                        </svg>
                    </div>
                    <div class="album-options-list" id="albumDropdown">
                        <div class="album-option-item <?php echo $filterAlbum === 'all' ? 'active' : ''; ?>" onclick="updateFilter('album', 'all')">
                            <span class="album-name-text">全部图片</span>
                        </div>
                        <?php foreach ($albums as $albumId => $album): ?>
                            <div class="album-option-item <?php echo $filterAlbum === $albumId ? 'active' : ''; ?>" onclick="updateFilter('album', '<?php echo $albumId; ?>')" id="album-item-<?php echo $albumId; ?>">
                                <span class="album-name-text"><?php echo htmlspecialchars($album['name']); ?> (<?php echo count($album['images']); ?>)</span>
                                <div class="album-input-wrapper">
                                    <input type="text" class="album-inline-input album-rename-input" value="<?php echo htmlspecialchars($album['name']); ?>" 
                                        onclick="event.stopPropagation()" 
                                        onkeyup="handleRenameKey(event, '<?php echo $albumId; ?>', '<?php echo addslashes($album['name']); ?>')">
                                    <button class="album-confirm-btn" onclick="event.stopPropagation(); confirmRename('<?php echo $albumId; ?>', '<?php echo addslashes($album['name']); ?>')" title="确认">
                                        <svg viewBox="0 0 20 20"><g fill="currentColor"><path d="M17.414 2.586a2 2 0 0 0-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 0 0 0-2.828"/><path fill-rule="evenodd" d="M2 6a2 2 0 0 1 2-2h4a1 1 0 0 1 0 2H4v10h10v-4a1 1 0 1 1 2 0v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" clip-rule="evenodd"/></g></svg>
                                    </button>
                                </div>
                                <div class="album-item-actions">
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); showRenameInput('<?php echo $albumId; ?>')" title="重命名">
                                        <svg viewBox="0 0 20 20"><g fill="currentColor"><path d="M17.414 2.586a2 2 0 0 0-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 0 0 0-2.828"/><path fill-rule="evenodd" d="M2 6a2 2 0 0 1 2-2h4a1 1 0 0 1 0 2H4v10h10v-4a1 1 0 1 1 2 0v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" clip-rule="evenodd"/></g></svg>
                                    </button>
                                    <button class="action-icon-btn" onclick="event.stopPropagation(); deleteAlbumById('<?php echo $albumId; ?>', '<?php echo addslashes($album['name']); ?>')" title="删除">
                                        <svg viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M9.774 5L3.758 3.94l.174-.986a.5.5 0 0 1 .58-.405L18.411 5h.088h-.087l1.855.327a.5.5 0 0 1 .406.58l-.174.984l-2.09-.368l-.8 13.594A2 2 0 0 1 15.615 22H8.386a2 2 0 0 1-1.997-1.883L5.59 6.5h12.69zH5.5zM9 9l.5 9H11l-.4-9zm4.5 0l-.5 9h1.5l.5-9zm-2.646-7.871l3.94.694a.5.5 0 0 1 .405.58l-.174.984l-4.924-.868l.174-.985a.5.5 0 0 1 .58-.405z"/></svg>
                                    </button>
                                </div>
                            </div>
                        <?php endforeach; ?>
                        <div class="album-create-row" id="createAlbumRow" onclick="showCreateInput(event)">
                            <div class="create-label" style="display: flex; align-items: center; gap: 10px;">
                                <svg viewBox="0 0 48 48" style="width: 20px; height: 20px;"><g fill="none"><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M25 40H7C5.34315 40 4 38.6569 4 37V11C4 9.34315 5.34315 8 7 8H41C42.6569 8 44 9.34315 44 11V24.9412"/><path fill="currentColor" opacity="0.2" d="M4 11C4 9.34315 5.34315 8 7 8H41C42.6569 8 44 9.34315 44 11V20H4V11Z"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M32 35H44"/><path stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4" d="M38 29V41"/><circle r="2" fill="currentColor" transform="matrix(0 -1 -1 0 10 14)"/><circle r="2" fill="currentColor" transform="matrix(0 -1 -1 0 16 14)"/></g></svg>
                                <span>新建相册</span>
                            </div>
                            <div class="album-input-wrapper">
                                <input type="text" class="album-inline-input" id="createAlbumInput" placeholder="输入相册名并回车..." 
                                    onclick="event.stopPropagation()" 
                                    onkeyup="handleCreateKey(event)">
                                <button class="album-confirm-btn" onclick="event.stopPropagation(); confirmCreate()" title="确认">
                                    <svg viewBox="0 0 20 20"><g fill="currentColor"><path d="M17.414 2.586a2 2 0 0 0-2.828 0L7 10.172V13h2.828l7.586-7.586a2 2 0 0 0 0-2.828"/><path fill-rule="evenodd" d="M2 6a2 2 0 0 1 2-2h4a1 1 0 0 1 0 2H4v10h10v-4a1 1 0 1 1 2 0v4a2 2 0 0 1-2 2H4a2 2 0 0 1-2-2z" clip-rule="evenodd"/></g></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="filter-group">
                <label>每页显示:</label>
                <select onchange="updateFilter('per_page', this.value)">
                    <option value="10" <?php echo $perPage === 10 ? 'selected' : ''; ?>>10个</option>
                    <option value="20" <?php echo $perPage === 20 ? 'selected' : ''; ?>>20个</option>
                    <option value="50" <?php echo $perPage === 50 ? 'selected' : ''; ?>>50个</option>
                </select>
            </div>
            <div class="batch-actions">
                <button class="batch-btn select-all" onclick="toggleSelectAll()">全选</button>
                <button class="batch-btn delete-selected" onclick="batchDelete()" disabled id="batchDeleteBtn">删除选中 (<span id="selectedCount">0</span>)</button>
                <button class="batch-btn add-to-album" onclick="showAddToAlbumModal()" disabled id="addToAlbumBtn">添加到相册</button>
                <?php if ($filterAlbum !== 'all'): ?>
                <button class="batch-btn remove-from-album" onclick="batchRemoveFromAlbum()" disabled id="removeFromAlbumBtn">从相册移除</button>
                <?php endif; ?>

            </div>
            <div class="stats-info">
                共 <?php echo $totalImages; ?> 张图片 | 第 <?php echo $page; ?>/<?php echo max(1, $totalPages); ?> 页
            </div>
        </div>

        <?php if (empty($images)): ?>
            <div class="empty-state">
                <h3>📭 暂无图片</h3>
                <p>请先上传图片或在 images 目录中放置图片文件</p>
            </div>
        <?php else: ?>
            <div class="gallery-grid">
                <?php foreach ($images as $img): ?>
                    <div class="image-card" data-path="<?php echo htmlspecialchars($img['path']); ?>" data-url="<?php echo htmlspecialchars($img['url']); ?>">
                        <input type="checkbox" class="select-checkbox" onclick="event.stopPropagation(); toggleSelect(this)">
                        <img src="<?php echo htmlspecialchars($img['url']); ?>" alt="<?php echo htmlspecialchars($img['filename']); ?>" loading="lazy" onclick="showModal(<?php echo htmlspecialchars(json_encode($img, JSON_HEX_APOS | JSON_HEX_QUOT)); ?>)">
                        <div class="image-info">
                            <div class="image-name" title="<?php echo htmlspecialchars($img['original_name']); ?>">
                                <?php echo htmlspecialchars($img['original_name']); ?>
                            </div>
                            <div class="image-meta">
                                <span class="meta-tag type-<?php echo $img['type']; ?>">
                                    <?php echo strtoupper($img['type']); ?>
                                </span>
                                <span class="meta-tag format">
                                    <?php echo strtoupper($img['format']); ?>
                                </span>
                                <span class="meta-tag size">
                                    <?php echo $img['size_formatted']; ?>
                                </span>
                                <span class="meta-tag storage">
                                    <?php echo $img['storage'] === 'local' ? '本地' : '外链'; ?>
                                </span>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=<?php echo $page - 1; ?>&type=<?php echo $filterType; ?>&format=<?php echo $filterFormat; ?>&storage=<?php echo $filterStorage; ?>&sort=<?php echo $sortBy; ?>&per_page=<?php echo $perPage; ?>&album=<?php echo $filterAlbum; ?>">« 上一页</a>
                    <?php endif; ?>
                    
                    <?php
                    $startPage = max(1, $page - 2);
                    $endPage = min($totalPages, $page + 2);
                    
                    for ($i = $startPage; $i <= $endPage; $i++):
                    ?>
                        <?php if ($i == $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>&type=<?php echo $filterType; ?>&format=<?php echo $filterFormat; ?>&storage=<?php echo $filterStorage; ?>&sort=<?php echo $sortBy; ?>&per_page=<?php echo $perPage; ?>&album=<?php echo $filterAlbum; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>&type=<?php echo $filterType; ?>&format=<?php echo $filterFormat; ?>&storage=<?php echo $filterStorage; ?>&sort=<?php echo $sortBy; ?>&per_page=<?php echo $perPage; ?>&album=<?php echo $filterAlbum; ?>">下一页 »</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>
        <?php endif; ?>
    </div>

    <!-- 模态框 -->
    <div id="imageModal" class="modal">
        <span class="close-btn" onclick="closeModal()">&times;</span>
        <button class="delete-single-btn" onclick="deleteSingleImage()" title="删除图片">
            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="currentColor" fill-rule="evenodd" d="M9.774 5L3.758 3.94l.174-.986a.5.5 0 0 1 .58-.405L18.411 5h.088h-.087l1.855.327a.5.5 0 0 1 .406.58l-.174.984l-2.09-.368l-.8 13.594A2 2 0 0 1 15.615 22H8.386a2 2 0 0 1-1.997-1.883L5.59 6.5h12.69zH5.5zM9 9l.5 9H11l-.4-9zm4.5 0l-.5 9h1.5l.5-9zm-2.646-7.871l3.94.694a.5.5 0 0 1 .405.58l-.174.984l-4.924-.868l.174-.985a.5.5 0 0 1 .58-.405z"/></svg>
        </button>
        <div class="modal-content">
            <div class="modal-image-wrapper">
                <div class="modal-image">
                    <img id="modalImage" src="" alt="">
                </div>
                <div class="tab-container">
                    <div class="tab-buttons">
                        <button class="tab-btn active" data-tab="url">URL</button>
                        <button class="tab-btn" data-tab="html">HTML</button>
                        <button class="tab-btn" data-tab="markdown">Markdown</button>
                        <button class="tab-btn" data-tab="bbcode">BBCode</button>
                    </div>
                    <div class="tab-content">
                        <div class="tab-pane active" id="url-tab-modal">
                            <div class="url-input-wrapper">
                                <input type="text" class="link-input" id="urlLink-modal" readonly>
                                <button class="copy-inline-btn" onclick="copyFromInput('urlLink-modal', this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                                </button>
                            </div>
                        </div>
                        <div class="tab-pane" id="html-tab-modal">
                            <div class="url-input-wrapper">
                                <input type="text" class="link-input" id="htmlLink-modal" readonly>
                                <button class="copy-inline-btn" onclick="copyFromInput('htmlLink-modal', this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                                </button>
                            </div>
                        </div>
                        <div class="tab-pane" id="markdown-tab-modal">
                            <div class="url-input-wrapper">
                                <input type="text" class="link-input" id="markdownLink-modal" readonly>
                                <button class="copy-inline-btn" onclick="copyFromInput('markdownLink-modal', this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                                </button>
                            </div>
                        </div>
                        <div class="tab-pane" id="bbcode-tab-modal">
                            <div class="url-input-wrapper">
                                <input type="text" class="link-input" id="bbcodeLink-modal" readonly>
                                <button class="copy-inline-btn" onclick="copyFromInput('bbcodeLink-modal', this)">
                                    <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <div class="modal-details">
                <h3>
                    <span>📄 图片详情</span>
                </h3>
                <div class="detail-item">
                    <div class="detail-label">文件名</div>
                    <div class="detail-value" id="detailFilename"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">原始文件名</div>
                    <div class="detail-value" id="detailOriginalName"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">文件路径</div>
                    <div class="detail-value" id="detailPath"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">访问URL</div>
                    <div class="detail-value" id="detailUrl"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">文件大小</div>
                    <div class="detail-value" id="detailSize"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">图片尺寸</div>
                    <div class="detail-value" id="detailDimensions"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">设备类型</div>
                    <div class="detail-value" id="detailType"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">图片格式</div>
                    <div class="detail-value" id="detailFormat"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">上传时间</div>
                    <div class="detail-value" id="detailTime"></div>
                </div>
                <div class="detail-item">
                    <div class="detail-label">上传者IP</div>
                    <div class="detail-value" id="detailUploaderIP"></div>
                </div>
            </div>
        </div>
    </div>



    <!-- 选择相册弹窗 -->
    <div id="albumSelectModal" class="album-select-modal" onclick="if(event.target === this) closeAlbumSelectModal()">
        <div class="album-select-content">
            <div class="album-select-close" onclick="closeAlbumSelectModal()" title="关闭">
                <svg xmlns="http://www.w3.org/2000/svg" width="2048" height="2048" viewBox="0 0 2048 2048"><path fill="currentColor" d="M1024 0q141 0 272 36t244 104t207 160t161 207t103 245t37 272q0 141-36 272t-104 244t-160 207t-207 161t-245 103t-272 37q-141 0-272-36t-244-104t-207-160t-161-207t-103-245t-37-272q0-141 36-272t104-244t160-207t207-161T752 37t272-37m0 1920q124 0 238-32t214-90t181-140t140-181t91-214t32-239t-32-238t-90-214t-140-181t-181-140t-214-91t-239-32t-238 32t-214 90t-181 140t-140 181t-91 214t-32 239t32 238t90 214t140 181t181 140t214 91t239 32m443-1249l-352 353l352 353l-90 90l-353-352l-353 352l-90-90l352-353l-352-353l90-90l353 352l353-352z"/></svg>
            </div>
            <h2>📚 选择相册</h2>
            <div class="album-select-list" id="albumSelectList">
                <?php foreach ($albums as $albumId => $album): ?>
                <div class="album-select-item" onclick="addToAlbum('<?php echo htmlspecialchars($albumId); ?>')">
                    <?php echo htmlspecialchars($album['name']); ?> (<?php echo count($album['images']); ?>)
                </div>
                <?php endforeach; ?>
                
                <!-- 新增相册项 -->
                <div class="album-select-item create-mode" id="createAlbumItemModal" style="border-style: dashed; background: rgba(40, 167, 69, 0.1); color: #28a745;">
                    <div class="create-label" onclick="toggleCreateInputModal(event)">
                        <svg viewBox="0 0 24 24" width="18" height="18" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><line x1="12" y1="5" x2="12" y2="19"></line><line x1="5" y1="12" x2="19" y2="12"></line></svg>
                        <span>新增相册</span>
                    </div>
                    <div class="create-input-wrapper">
                        <input type="text" class="create-inline-input" id="createAlbumInputModal" placeholder="相册名称..." onclick="event.stopPropagation()" onkeyup="handleCreateKeyModal(event)">
                        <button class="create-confirm-btn" onclick="confirmCreateModal(event)" title="确认创建">
                            <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path stroke-dasharray="60" d="M3 12c0 -4.97 4.03 -9 9 -9c4.97 0 9 4.03 9 9c0 4.97 -4.03 9 -9 9c-4.97 0 -9 -4.03 -9 -9Z"><animate fill="freeze" attributeName="stroke-dashoffset" dur="0.6s" values="60;0"/></path><path stroke-dasharray="14" stroke-dashoffset="14" d="M8 12l3 3l5 -5"><animate fill="freeze" attributeName="stroke-dashoffset" begin="0.6s" dur="0.2s" to="0"/></path></g></svg>
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- 全屏查看器 -->
    <div id="fullscreenViewer" class="fullscreen-viewer" onclick="closeFullscreen()">
        <img id="fullscreenImage" src="" alt="Full Screen">
    </div>

    <!-- 优雅的确认对话框 -->
    <div id="confirmModal" class="confirm-modal">
        <div class="confirm-content">
            <div id="confirmTitle" class="confirm-title">确认删除</div>
            <div id="confirmMessage" class="confirm-message">确认执行此操作吗？</div>
            <div class="confirm-btns">
                <button class="confirm-btn cancel" onclick="closeConfirmModal()">取消</button>
                <button id="confirmBtn" class="confirm-btn danger">确认删除</button>
            </div>
        </div>
    </div>

    <!-- 状态遮罩层 -->
    <div id="statusOverlay" class="status-overlay">
        <div id="statusIconContainer" class="status-icon">
            <div class="spinner"></div>
        </div>
        <div id="statusText" class="status-text">正在处理...</div>
    </div>

    <a href="?logout=1" class="floating-logout" title="退出登录">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 4.001H5v14a2 2 0 0 0 2 2h8m1-5l3-3m0 0l-3-3m3 3H9"/></svg>
    </a>

    <script>
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const icon = document.getElementById('toggleIcon');
            sidebar.classList.toggle('collapsed');
            
            if (sidebar.classList.contains('collapsed')) {
                icon.innerText = '➡️';
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                icon.innerText = '⬅️';
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }

        // 页面加载时恢复侧边栏状态
        window.addEventListener('DOMContentLoaded', () => {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                const sidebar = document.getElementById('sidebar');
                const icon = document.getElementById('toggleIcon');
                if (sidebar) sidebar.classList.add('collapsed');
                if (icon) icon.innerText = '➡️';
            }
        });

        let selectedImages = new Map(); // 使用 Map 存储 {identifier: {path, url}}
        let currentImagePath = '';
        let currentImageUrl = '';
        
        function updateFilter(key, value) {
            const url = new URL(window.location);
            url.searchParams.set(key, value);
            url.searchParams.set('page', '1');
            window.location = url.toString();
        }

        // 相册下拉框控制
        function toggleAlbumDropdown(event) {
            event.stopPropagation();
            document.getElementById('albumDropdown').classList.toggle('show');
        }

        // 点击页面其他地方关闭下拉框
        window.addEventListener('click', function() {
            const dropdown = document.getElementById('albumDropdown');
            if (dropdown && dropdown.classList.contains('show')) {
                dropdown.classList.remove('show');
            }
        });

        // 行内重命名逻辑
        function showRenameInput(albumId) {
            const item = document.getElementById(`album-item-${albumId}`);
            const text = item.querySelector('.album-name-text');
            const inputWrapper = item.querySelector('.album-input-wrapper');
            const input = inputWrapper.querySelector('.album-inline-input');
            const actions = item.querySelector('.album-item-actions');
            
            text.style.display = 'none';
            actions.style.display = 'none';
            inputWrapper.classList.add('show');
            input.focus();
            input.setSelectionRange(0, input.value.length);
        }

        function handleRenameKey(event, albumId, oldName) {
            if (event.key === 'Enter') {
                confirmRename(albumId, oldName);
            } else if (event.key === 'Escape') {
                cancelRename(albumId);
            }
        }

        function confirmRename(albumId, oldName) {
            const item = document.getElementById(`album-item-${albumId}`);
            const input = item.querySelector('.album-inline-input');
            const newName = input.value.trim();
            
            if (newName && newName !== oldName) {
                renameAlbum(albumId, newName);
            } else {
                cancelRename(albumId);
            }
        }

        function cancelRename(albumId) {
            const item = document.getElementById(`album-item-${albumId}`);
            const text = item.querySelector('.album-name-text');
            const inputWrapper = item.querySelector('.album-input-wrapper');
            const actions = item.querySelector('.album-item-actions');
            
            inputWrapper.classList.remove('show');
            text.style.display = 'inline';
            actions.style.display = 'flex';
        }

        // 行内新建逻辑
        function showCreateInput(event) {
            event.stopPropagation();
            const row = document.getElementById('createAlbumRow');
            const label = row.querySelector('.create-label');
            const inputWrapper = row.querySelector('.album-input-wrapper');
            const input = inputWrapper.querySelector('#createAlbumInput');
            
            label.style.display = 'none';
            inputWrapper.classList.add('show');
            input.focus();
        }

        function handleCreateKey(event) {
            if (event.key === 'Enter') {
                confirmCreate();
            } else if (event.key === 'Escape') {
                cancelCreate();
            }
        }

        function confirmCreate() {
            const input = document.getElementById('createAlbumInput');
            const name = input.value.trim();
            
            if (name) {
                createAlbumByName(name);
            } else {
                cancelCreate();
            }
        }

        function cancelCreate() {
            const row = document.getElementById('createAlbumRow');
            const label = row.querySelector('.create-label');
            const inputWrapper = row.querySelector('.album-input-wrapper');
            const input = inputWrapper.querySelector('#createAlbumInput');
            
            inputWrapper.classList.remove('show');
            input.value = '';
            label.style.display = 'flex';
        }

        function renameAlbumById(albumId, currentName) {
            showRenameInput(albumId);
        }

        function deleteAlbumById(albumId, albumName) {
            if (confirm(`确认删除相册 "${albumName}"？（图片不会被删除）`)) {
                deleteAlbum(albumId);
            }
        }
        
        function toggleSelect(checkbox) {
            const card = checkbox.closest('.image-card');
            const path = card.dataset.path;
            const url = card.dataset.url;
            const identifier = path || url;
            
            if (checkbox.checked) {
                selectedImages.set(identifier, {path, url});
                card.classList.add('selected');
            } else {
                selectedImages.delete(identifier);
                card.classList.remove('selected');
            }
            
            updateBatchButton();
        }
        
        function toggleSelectAll() {
            const checkboxes = document.querySelectorAll('.select-checkbox');
            const allSelected = selectedImages.size === checkboxes.length;
            
            checkboxes.forEach(checkbox => {
                checkbox.checked = !allSelected;
                const card = checkbox.closest('.image-card');
                const path = card.dataset.path;
                const url = card.dataset.url;
                const identifier = path || url;
                
                if (!allSelected) {
                    selectedImages.set(identifier, {path, url});
                    card.classList.add('selected');
                } else {
                    selectedImages.delete(identifier);
                    card.classList.remove('selected');
                }
            });
            
            updateBatchButton();
        }
        
        function updateBatchButton() {
            const count = selectedImages.size;
            document.getElementById('selectedCount').textContent = count;
            document.getElementById('batchDeleteBtn').disabled = count === 0;
            const addBtn = document.getElementById('addToAlbumBtn');
            if (addBtn) addBtn.disabled = count === 0;
            const removeBtn = document.getElementById('removeFromAlbumBtn');
            if (removeBtn) removeBtn.disabled = count === 0;
        }
        
        // 状态遮罩管理
        function showStatusOverlay(text, type = 'loading') {
            const overlay = document.getElementById('statusOverlay');
            const iconContainer = document.getElementById('statusIconContainer');
            const textEl = document.getElementById('statusText');
            
            textEl.textContent = text;
            
            if (type === 'loading') {
                iconContainer.innerHTML = '<div class="spinner"></div>';
            } else if (type === 'success') {
                iconContainer.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#28a745" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>';
            } else if (type === 'error') {
                iconContainer.innerHTML = '<svg viewBox="0 0 24 24" fill="none" stroke="#dc3545" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"></circle><line x1="15" y1="9" x2="9" y2="15"></line><line x1="9" y1="9" x2="15" y2="15"></line></svg>';
            }
            
            overlay.classList.add('active');
        }

        function hideStatusOverlay() {
            document.getElementById('statusOverlay').classList.remove('active');
        }

        // 自定义确认框
        function showConfirm(title, message, callback) {
            const modal = document.getElementById('confirmModal');
            document.getElementById('confirmTitle').textContent = title;
            document.getElementById('confirmMessage').textContent = message;
            const btn = document.getElementById('confirmBtn');
            
            btn.onclick = () => {
                closeConfirmModal();
                callback();
            };
            
            modal.classList.add('active');
        }

        function closeConfirmModal() {
            document.getElementById('confirmModal').classList.remove('active');
        }

        async function batchDelete() {
            if (selectedImages.size === 0) return;
            
            showConfirm('批量删除', `确认删除选中的 ${selectedImages.size} 张图片吗？此操作不可撤销。`, async () => {
                const items = Array.from(selectedImages.values());
                showStatusOverlay('正在删除...', 'loading');
                
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=batch_delete&items=${encodeURIComponent(JSON.stringify(items))}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showStatusOverlay('删除成功', 'success');
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        hideStatusOverlay();
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    hideStatusOverlay();
                    showNotification('删除失败', 'error');
                }
            });
        }
        
        async function deleteSingleImage() {
            if (!currentImagePath && !currentImageUrl) return;
            
            showConfirm('确认删除', '确认删除这张图片吗？此操作不可撤销。', async () => {
                showStatusOverlay('正在删除...', 'loading');
                
                try {
                    const response = await fetch(window.location.href, {
                        method: 'POST',
                        headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                        body: `action=delete&path=${encodeURIComponent(currentImagePath)}&url=${encodeURIComponent(currentImageUrl)}`
                    });
                    
                    const result = await response.json();
                    
                    if (result.success) {
                        showStatusOverlay('删除成功', 'success');
                        closeModal();
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        hideStatusOverlay();
                        showNotification(result.message, 'error');
                    }
                } catch (error) {
                    hideStatusOverlay();
                    showNotification('删除失败', 'error');
                }
            });
        }
        
        // 相册管理函数
        function createAlbumPrompt() {
            const albumName = prompt('请输入相册名称:');
            if (!albumName) return;
            
            createAlbumByName(albumName);
        }
        
        function renameAlbumPrompt() {
            const selectElement = document.querySelector('select[onchange*="updateFilter(\'album\'"]');
            const selectedAlbumId = selectElement.value;
            
            if (selectedAlbumId === 'all') {
                showNotification('请先选择一个相册', 'error');
                return;
            }
            
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const currentName = selectedOption.text.split(' (')[0]; // 提取相册名（去掉图片数量）
            const newName = prompt('请输入新的相册名称:', currentName);
            
            if (!newName || newName === currentName) return;
            
            renameAlbum(selectedAlbumId, newName);
        }
        
        function deleteAlbumPrompt() {
            const selectElement = document.querySelector('select[onchange*="updateFilter(\'album\'"]');
            const selectedAlbumId = selectElement.value;
            
            if (selectedAlbumId === 'all') {
                showNotification('请先选择一个相册', 'error');
                return;
            }
            
            const selectedOption = selectElement.options[selectElement.selectedIndex];
            const albumName = selectedOption.text.split(' (')[0]; // 提取相册名（去掉图片数量）
            
            if (confirm(`确认删除相册 "${albumName}"？（图片不会被删除）`)) {
                deleteAlbum(selectedAlbumId);
            }
        }
        
        async function createAlbumByName(name) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=create_album&name=${encodeURIComponent(name)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('创建失败', 'error');
            }
        }
        
        async function renameAlbum(albumId, newName) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=rename_album&album_id=${encodeURIComponent(albumId)}&name=${encodeURIComponent(newName)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('重命名失败', 'error');
            }
        }
        
        async function deleteAlbum(albumId) {
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete_album&album_id=${encodeURIComponent(albumId)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('删除失败', 'error');
            }
        }
        
        function viewAlbum(albumId) {
            const url = new URL(window.location);
            url.searchParams.set('album', albumId);
            url.searchParams.set('page', '1');
            window.location = url.toString();
        }
        
        function showAddToAlbumModal() {
            if (selectedImages.size === 0) return;
            document.getElementById('albumSelectModal').classList.add('active');
        }

        function toggleCreateInputModal(event) {
            event.stopPropagation();
            document.getElementById('createAlbumItemModal').classList.add('active-input');
            const input = document.getElementById('createAlbumInputModal');
            input.focus();
        }

        function handleCreateKeyModal(event) {
            if (event.key === 'Enter') {
                confirmCreateModal(event);
            } else if (event.key === 'Escape') {
                cancelCreateModal();
            }
        }

        function cancelCreateModal() {
            const item = document.getElementById('createAlbumItemModal');
            const input = document.getElementById('createAlbumInputModal');
            item.classList.remove('active-input');
            input.value = '';
        }

        async function confirmCreateModal(event) {
            if (event) event.stopPropagation();
            const input = document.getElementById('createAlbumInputModal');
            const name = input.value.trim();
            
            if (!name) {
                cancelCreateModal();
                return;
            }

            try {
                showStatusOverlay('正在创建相册...', 'loading');
                // 1. 创建相册
                const createResponse = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=create_album&name=${encodeURIComponent(name)}`
                });
                const createResult = await createResponse.json();

                if (!createResult.success) {
                    hideStatusOverlay();
                    showNotification(createResult.message, 'error');
                    return;
                }

                // 2. 将图片添加到新创建的相册
                await addToAlbum(createResult.album_id);
            } catch (error) {
                hideStatusOverlay();
                showNotification('操作失败', 'error');
            }
        }
        
        function closeAlbumSelectModal() {
            document.getElementById('albumSelectModal').classList.remove('active');
        }
        
        async function createAlbum() {
            const nameInput = document.getElementById('newAlbumName');
            const name = nameInput.value.trim();
            
            if (!name) {
                showNotification('请输入相册名称', 'error');
                return;
            }
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=create_album&name=${encodeURIComponent(name)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    nameInput.value = '';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('创建失败', 'error');
            }
        }
        
        async function deleteAlbum(albumId) {
            if (!confirm('确认删除该相册？（图片不会被删除）')) return;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=delete_album&album_id=${encodeURIComponent(albumId)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('删除失败', 'error');
            }
        }
        
        async function renameAlbum(albumId, oldName) {
            const newName = prompt('请输入新的相册名称:', oldName);
            if (!newName || newName === oldName) return;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=rename_album&album_id=${encodeURIComponent(albumId)}&name=${encodeURIComponent(newName)}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('重命名失败', 'error');
            }
        }
        
        async function addToAlbum(albumId) {
            if (selectedImages.size === 0) return;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=add_to_album&album_id=${encodeURIComponent(albumId)}&paths=${encodeURIComponent(JSON.stringify(Array.from(selectedImages)))}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    closeAlbumSelectModal();
                    selectedImages.clear();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('添加失败', 'error');
            }
        }
        
        async function batchRemoveFromAlbum() {
            if (selectedImages.size === 0) return;
            
            const albumId = new URLSearchParams(window.location.search).get('album');
            if (!albumId || albumId === 'all') return;
            
            if (!confirm(`确认从当前相册移除选中的 ${selectedImages.size} 张图片？`)) return;
            
            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=remove_from_album&album_id=${encodeURIComponent(albumId)}&paths=${encodeURIComponent(JSON.stringify(Array.from(selectedImages)))}`
                });
                
                const result = await response.json();
                
                if (result.success) {
                    showNotification(result.message);
                    selectedImages.clear();
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(result.message, 'error');
                }
            } catch (error) {
                showNotification('移除失败', 'error');
            }
        }

        function showModal(imageData) {
            currentImagePath = imageData.path || '';
            currentImageUrl = imageData.url || '';
            const modalImg = document.getElementById('modalImage');
            modalImg.src = imageData.url;
            modalImg.onclick = () => openFullscreen(imageData.url);
            
            // 填充详情并添加 title 提示，确保单行截断
            const setDetail = (id, text) => {
                const el = document.getElementById(id);
                el.textContent = text || 'N/A';
                el.title = text || 'N/A';
            };

            setDetail('detailFilename', imageData.filename);
            setDetail('detailOriginalName', imageData.original_name);
            setDetail('detailPath', imageData.path || imageData.url);
            setDetail('detailUrl', imageData.url);
            setDetail('detailSize', imageData.size_formatted + ' (' + imageData.size + ' bytes)');
            setDetail('detailDimensions', imageData.dimensions);
            setDetail('detailType', imageData.type.toUpperCase() + ' (' + (imageData.type === 'pc' ? '桌面端' : '移动端') + ')');
            setDetail('detailFormat', imageData.format.toUpperCase());
            setDetail('detailTime', imageData.upload_time);
            setDetail('detailUploaderIP', imageData.uploader_ip);
            
            // 设置各种格式的链接
            document.getElementById('urlLink-modal').value = imageData.url;
            document.getElementById('htmlLink-modal').value = `<img src="${imageData.url}" alt="${imageData.original_name}">`;
            document.getElementById('markdownLink-modal').value = `![${imageData.original_name}](${imageData.url})`;
            document.getElementById('bbcodeLink-modal').value = `[img]${imageData.url}[/img]`;
            
            // 如果是移动端图片，在PC端显示时优化布局
            const modalContent = document.querySelector('.modal-content');
            if (imageData.type === 'pe') {
                modalContent.classList.add('mobile-image');
            } else {
                modalContent.classList.remove('mobile-image');
            }
            
            document.getElementById('imageModal').classList.add('active');
        }

        function closeModal() {
            document.getElementById('imageModal').classList.remove('active');
            currentImagePath = '';
            currentImageUrl = '';
        }

        // 全屏查看功能
        function openFullscreen(url) {
            const viewer = document.getElementById('fullscreenViewer');
            const img = document.getElementById('fullscreenImage');
            img.src = url;
            viewer.classList.add('active');
            document.body.style.overflow = 'hidden'; // 禁止背景滚动
        }

        function closeFullscreen() {
            const viewer = document.getElementById('fullscreenViewer');
            viewer.classList.remove('active');
            if (!document.getElementById('imageModal').classList.contains('active')) {
                document.body.style.overflow = '';
            }
        }

        document.getElementById('imageModal').addEventListener('click', function(e) {
            if (e.target === this) {
                closeModal();
            }
        });

        document.addEventListener('keydown', function(e) {
            if (e.key === 'Escape') {
                closeModal();
                closeAlbumSelectModal();
                closeFullscreen();
            }
        });
        
        // 标签页切换
        document.querySelectorAll('.tab-buttons .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                const container = btn.closest('.tab-container');
                
                // 移除所有激活状态
                container.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                container.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                // 添加当前激活状态
                btn.classList.add('active');
                container.querySelector(`#${tabName}-tab-modal`).classList.add('active');
            });
        });
        
        // 从输入框复制
        function copyFromInput(elementId, btn) {
            const input = document.getElementById(elementId);
            input.select();
            document.execCommand('copy');
            
            const originalSVG = btn.innerHTML;
            btn.innerHTML = '<svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20 6L9 17l-5-5"/></svg>';
            btn.classList.add('copied');
            
            setTimeout(() => {
                btn.innerHTML = originalSVG;
                btn.classList.remove('copied');
            }, 2000);
            
            showNotification('已复制到剪贴板');
        }
        
        function toggleSidebar() {
            const sidebar = document.getElementById('sidebar');
            const icon = document.getElementById('toggleIcon');
            sidebar.classList.toggle('collapsed');
            
            if (sidebar.classList.contains('collapsed')) {
                icon.innerText = '➡️';
                localStorage.setItem('sidebarCollapsed', 'true');
            } else {
                icon.innerText = '⬅️';
                localStorage.setItem('sidebarCollapsed', 'false');
            }
        }

        // 页面加载时恢复侧边栏状态
        window.addEventListener('DOMContentLoaded', () => {
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                const sidebar = document.getElementById('sidebar');
                const icon = document.getElementById('toggleIcon');
                if (sidebar) sidebar.classList.add('collapsed');
                if (icon) icon.innerText = '➡️';
            }
        });

        function showNotification(message, type = 'success') {
            const notification = document.createElement('div');
            notification.className = `notification ${type}`;
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
    </script>
</body>
</html>
