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

// 获取图片尺寸和大小的辅助函数
function getImageInfo($url) {
    $imageInfo = [
        'width' => null,
        'height' => null,
        'file_size' => null,
        'mime' => null
    ];
    
    // 使用 cURL 获取图片信息
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
    curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
    curl_setopt($ch, CURLOPT_TIMEOUT, 10);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    curl_setopt($ch, CURLOPT_USERAGENT, 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/120.0.0.0 Safari/537.36');
    
    // 先获取文件头信息
    curl_setopt($ch, CURLOPT_HEADER, true);
    curl_setopt($ch, CURLOPT_NOBODY, true);
    
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $contentLength = curl_getinfo($ch, CURLINFO_CONTENT_LENGTH_DOWNLOAD);
    
    if ($httpCode == 200) {
        if ($contentLength > 0) {
            $imageInfo['file_size'] = (int)$contentLength;
        }
        
        // 获取部分图片数据以获取尺寸和类型
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_NOBODY, false);
        // 只读取前 32KB 数据通常就足够识别图片头信息了，节省流量和时间
        curl_setopt($ch, CURLOPT_RANGE, '0-32768'); 
        
        $imageData = curl_exec($ch);
        
        if ($imageData !== false) {
            $image = @getimagesizefromstring($imageData);
            if ($image !== false) {
                $imageInfo['width'] = $image[0];
                $imageInfo['height'] = $image[1];
                $imageInfo['mime'] = $image['mime'];
            }
        }
    }
    
    curl_close($ch);
    return $imageInfo;
}

// 处理外链图片的 API 请求
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    try {
        $db = Database::getInstance();
        
        // 添加外链图片
        if ($_POST['action'] === 'add') {
            $url = trim($_POST['url'] ?? '');
            
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URL 不能为空']);
                exit;
            }
            
            // 验证 URL 格式
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'message' => '无效的 URL 格式']);
                exit;
            }
            
            // 检查 URL 是否已存在
            $existing = $db->fetchOne("SELECT id FROM images WHERE url = :url", ['url' => $url]);
            if ($existing) {
                echo json_encode(['success' => false, 'message' => '此 URL 已存在']);
                exit;
            }
            
            // 获取图片信息
            $imageInfo = getImageInfo($url);
            
            if (!$imageInfo['width'] || !$imageInfo['height']) {
                echo json_encode(['success' => false, 'message' => '无法读取远程图片信息，请检查 URL 是否有效']);
                exit;
            }

            // 自动判断设备类型：宽 >= 高 为 PC，否则为 PE
            $device_type = ($imageInfo['width'] >= $imageInfo['height']) ? 'pc' : 'pe';
            
            // 自动判断格式
            $mime = $imageInfo['mime'];
            $format = match($mime) {
                'image/webp' => 'webp',
                'image/avif' => 'avif',
                'image/png' => 'png',
                'image/gif' => 'gif',
                'image/jpeg' => 'jpeg',
                default => 'jpeg'
            };
            
            // 生成文件名
            $filename = 'external_' . $device_type . '_' . $format . '_' . time() . '_' . uniqid();
            
            $imageId = $db->insert('images', [
                'filename' => $filename,
                'url' => $url,
                'storage_type' => 'external',
                'device_type' => $device_type,
                'format' => $format,
                'width' => $imageInfo['width'],
                'height' => $imageInfo['height'],
                'file_size' => $imageInfo['file_size'],
                'uploader_ip' => $_SERVER['REMOTE_ADDR'] ?? 'N/A',
                'upload_time' => date('Y-m-d H:i:s')
            ]);
            
            echo json_encode([
                'success' => true,
                'message' => '外链图片添加成功',
                'image_id' => $imageId
            ]);
            exit;
        }
        
        // 编辑外链图片
        if ($_POST['action'] === 'edit') {
            $id = intval($_POST['id'] ?? 0);
            $url = trim($_POST['url'] ?? '');
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => '图片ID无效']);
                exit;
            }
            
            if (empty($url)) {
                echo json_encode(['success' => false, 'message' => 'URL 不能为空']);
                exit;
            }
            
            if (!filter_var($url, FILTER_VALIDATE_URL)) {
                echo json_encode(['success' => false, 'message' => '无效的 URL 格式']);
                exit;
            }
            
            // 检查图片是否存在
            $image = $db->fetchOne("SELECT * FROM images WHERE id = :id AND storage_type = 'external'", ['id' => $id]);
            if (!$image) {
                echo json_encode(['success' => false, 'message' => '图片不存在']);
                exit;
            }
            
            // 检查新 URL 是否被其他图片使用
            $duplicate = $db->fetchOne("SELECT id FROM images WHERE url = :url AND id != :id", 
                ['url' => $url, 'id' => $id]);
            if ($duplicate) {
                echo json_encode(['success' => false, 'message' => '此 URL 已被其他图片使用']);
                exit;
            }
            
            // 获取新图片信息
            $imageInfo = getImageInfo($url);
            
            $db->update('images', 
                [
                    'url' => $url,
                    'width' => $imageInfo['width'],
                    'height' => $imageInfo['height'],
                    'file_size' => $imageInfo['file_size']
                ],
                "id = :id",
                ['id' => $id]
            );
            
            echo json_encode(['success' => true, 'message' => '外链图片更新成功']);
            exit;
        }
        
        // 删除外链图片
        if ($_POST['action'] === 'delete') {
            $id = intval($_POST['id'] ?? 0);
            
            if ($id <= 0) {
                echo json_encode(['success' => false, 'message' => '图片ID无效']);
                exit;
            }
            
            $image = $db->fetchOne("SELECT * FROM images WHERE id = :id AND storage_type = 'external'", ['id' => $id]);
            if (!$image) {
                echo json_encode(['success' => false, 'message' => '图片不存在']);
                exit;
            }
            
            // 删除相关的相册关联
            $db->delete('album_images', "image_id = :id", ['id' => $id]);
            
            // 删除图片记录
            $db->delete('images', "id = :id", ['id' => $id]);
            
            echo json_encode(['success' => true, 'message' => '外链图片删除成功']);
            exit;
        }
        
        // 批量删除
        if ($_POST['action'] === 'batch_delete') {
            $ids = json_decode($_POST['ids'] ?? '[]', true);
            
            if (empty($ids)) {
                echo json_encode(['success' => false, 'message' => '请选择要删除的图片']);
                exit;
            }
            
            $deleted = 0;
            foreach ($ids as $id) {
                $id = intval($id);
                if ($id > 0) {
                    $db->delete('album_images', "image_id = :id", ['id' => $id]);
                    $result = $db->delete('images', "id = :id AND storage_type = 'external'", ['id' => $id]);
                    if ($result > 0) $deleted++;
                }
            }
            
            echo json_encode([
                'success' => true,
                'message' => "成功删除 {$deleted} 张外链图片",
                'deleted' => $deleted
            ]);
            exit;
        }
        
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => '错误: ' . $e->getMessage()]);
        exit;
    }
}

// 获取外链图片列表
$page = isset($_GET['page']) ? max(1, intval($_GET['page'])) : 1;
$perPage = 10;
$offset = ($page - 1) * $perPage;

try {
    $db = Database::getInstance();
    
    // 获取总数
    $total = $db->fetchOne(
        "SELECT COUNT(*) as count FROM images WHERE storage_type = 'external'",
        []
    )['count'];
    
    // 获取当前页数据
    $externalImages = $db->fetchAll(
        "SELECT * FROM images WHERE storage_type = 'external' ORDER BY upload_time DESC LIMIT :offset, :limit",
        ['offset' => $offset, 'limit' => $perPage],
        true
    );
    
    $totalPages = ceil($total / $perPage);
} catch (Exception $e) {
    $externalImages = [];
    $total = 0;
    $totalPages = 0;
    $error = "获取外链图片失败: " . $e->getMessage();
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>后台管理 - PixPort</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Arial, sans-serif;
            background: url('/public/assets/images/home-backend.jpg') no-repeat center center fixed;
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
        .header-buttons {
            display: flex;
            gap: 10px;
        }
        .btn {
            padding: 10px 20px;
            background: rgba(102, 126, 234, 0.8);
            color: white;
            text-decoration: none;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
        }
        .btn:hover {
            background: rgba(102, 126, 234, 1);
            transform: translateY(-2px);
        }
        .btn-danger {
            background: rgba(220, 53, 69, 0.8);
        }
        .btn-danger:hover {
            background: rgba(220, 53, 69, 1);
        }
        .btn-logout {
            background: #dc3545;
        }
        .btn-logout:hover {
            background: #c82333;
        }
        .tabs {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(20px);
            -webkit-backdrop-filter: blur(20px);
            border-radius: 12px;
            padding: 15px;
            margin-bottom: 20px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.3);
            display: flex;
            gap: 10px;
            position: relative;
            z-index: 1;
        }
        .tab-btn {
            flex: 1;
            padding: 12px;
            background: rgba(255, 255, 255, 0.2);
            border: none;
            border-radius: 8px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s;
            color: white;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .tab-btn.active {
            background: rgba(255, 255, 255, 0.4);
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        .tab-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.3);
        }
        .container {
            max-width: 1200px;
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
        .section {
            margin-bottom: 30px;
        }
        .section h2 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .form-group {
            margin-bottom: 15px;
        }
        .form-group label {
            display: block;
            color: white;
            font-weight: 600;
            margin-bottom: 8px;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .form-group input,
        .form-group select {
            width: 100%;
            padding: 10px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 6px;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 14px;
            backdrop-filter: blur(5px);
            -webkit-backdrop-filter: blur(5px);
        }
        .form-group select option {
            background: #2d2d2d;
            color: white;
        }
        .form-group input::placeholder {
            color: rgba(255, 255, 255, 0.6);
        }
        .form-group input:focus,
        .form-group select:focus {
            outline: none;
            border-color: rgba(102, 126, 234, 0.8);
            box-shadow: 0 0 8px rgba(102, 126, 234, 0.3);
        }
        .form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
        }
        .table-wrapper {
            overflow-x: auto;
            margin-top: 20px;
        }
        table {
            width: 100%;
            border-collapse: collapse;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 8px;
            overflow: hidden;
        }
        thead {
            background: rgba(255, 255, 255, 0.1);
        }
        th {
            color: white;
            padding: 12px;
            text-align: left;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        td {
            color: rgba(255, 255, 255, 0.9);
            padding: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        tbody tr:hover {
            background: rgba(255, 255, 255, 0.08);
        }
        .url-cell {
            max-width: 300px;
            overflow: hidden;
            text-overflow: ellipsis;
            white-space: nowrap;
        }
        .action-buttons {
            display: flex;
            gap: 8px;
        }
        .action-buttons button {
            padding: 6px 12px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 12px;
            font-weight: 600;
            transition: all 0.3s;
        }
        .edit-btn {
            background: rgba(40, 167, 69, 0.8);
            color: white;
        }
        .edit-btn:hover {
            background: rgba(40, 167, 69, 1);
        }
        .delete-btn {
            background: rgba(220, 53, 69, 0.8);
            color: white;
        }
        .delete-btn:hover {
            background: rgba(220, 53, 69, 1);
        }
        .pagination {
            display: flex;
            justify-content: center;
            align-items: center;
            gap: 10px;
            margin-top: 20px;
        }
        .pagination a, .pagination span {
            padding: 8px 12px;
            background: rgba(255, 255, 255, 0.1);
            color: white;
            text-decoration: none;
            border-radius: 4px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .pagination a:hover {
            background: rgba(255, 255, 255, 0.2);
        }
        .pagination span.active {
            background: rgba(102, 126, 234, 0.8);
            border-color: rgba(102, 126, 234, 1);
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
        .checkbox {
            width: 20px;
            height: 20px;
            cursor: pointer;
        }
        .modal {
            display: none;
            position: fixed;
            z-index: 10000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background: rgba(0, 0, 0, 0.9);
            backdrop-filter: blur(10px);
        }
        .modal.active {
            display: flex;
            align-items: center;
            justify-content: center;
        }
        .modal-content {
            background: rgba(255, 255, 255, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 30px;
            max-width: 500px;
            width: 90%;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.3);
            position: relative;
        }
        .modal-title {
            color: white;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 20px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .close-btn {
            position: absolute;
            top: 20px;
            right: 20px;
            background: none;
            border: none;
            color: white;
            font-size: 28px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .close-btn:hover {
            transform: scale(1.2);
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
            <span>- 外链管理</span>
        </h1>
        <div class="header-buttons">
            <a href="/upload.php" class="btn">🏠 返回主页</a>
            <a href="?logout=1" class="btn btn-logout">🚪 退出登录</a>
        </div>
    </div>

    <div class="tabs">
        <a href="/panel.php?tab=system" class="tab-btn">📊 系统监控</a>
        <a href="/panel.php?tab=database" class="tab-btn">🗄️ 数据库监控</a>
        <a href="/file.php" class="tab-btn">🖼️ 图片管理</a>
        <a href="/gallery.php" class="tab-btn">🎨 图片画廊</a>
        <a href="/external-manager.php" class="tab-btn active">🔗 外链管理</a>
        <a href="/api-panel.php" class="tab-btn">🔧 API管理</a>
    </div>

    <div class="container">
        <!-- 添加外链表单 -->
        <div class="section">
            <h2>➕ 添加外链图片</h2>
            <div style="display: flex; gap: 15px; align-items: flex-end;">
                <div class="form-group" style="flex: 1; margin-bottom: 0;">
                    <label>图片 URL</label>
                    <input type="text" id="urlInput" placeholder="输入完整的图片 URL，例如: https://example.com/image.jpg">
                </div>
                <button class="btn" onclick="addExternalImage()" style="height: 42px; padding: 0 30px;">✅ 智能添加</button>
            </div>
            <p style="margin-top: 10px; color: rgba(255,255,255,0.6); font-size: 13px;">系统将自动检测图片尺寸（识别 PC/移动端）及图片格式。</p>
        </div>

        <!-- 外链图片列表 -->
        <div class="section">
            <h2>📋 外链图片列表 (共 <?php echo $total; ?> 张)</h2>
            
            <?php if (!empty($error)): ?>
                <div style="color: #ff6b6b; background: rgba(255, 107, 107, 0.1); padding: 15px; border-radius: 8px; margin-bottom: 20px;">
                    <?php echo htmlspecialchars($error); ?>
                </div>
            <?php endif; ?>
            
            <div class="table-wrapper">
                <table>
                    <thead>
                        <tr>
                            <th><input type="checkbox" id="selectAll" class="checkbox" onchange="toggleSelectAll()"></th>
                            <th>URL</th>
                            <th>设备</th>
                            <th>格式</th>
                            <th>上传时间</th>
                            <th>操作</th>
                        </tr>
                    </thead>
                    <tbody id="imageTableBody">
                        <?php if (!empty($externalImages)): ?>
                            <?php foreach ($externalImages as $img): ?>
                                <tr>
                                    <td><input type="checkbox" class="checkbox image-checkbox" value="<?php echo $img['id']; ?>"></td>
                                    <td>
                                        <div class="url-cell" title="<?php echo htmlspecialchars($img['url']); ?>">
                                            <?php echo htmlspecialchars(substr($img['url'], 0, 50)); ?>...
                                        </div>
                                    </td>
                                    <td><?php echo $img['device_type'] === 'pc' ? '🖥️ PC' : '📱 移动'; ?></td>
                                    <td><?php echo strtoupper($img['format']); ?></td>
                                    <td><?php echo $img['upload_time']; ?></td>
                                    <td>
                                        <div class="action-buttons">
                                            <button class="edit-btn" onclick="editImage(<?php echo $img['id']; ?>, '<?php echo htmlspecialchars(addslashes($img['url'])); ?>')">编辑</button>
                                            <button class="delete-btn" onclick="deleteImage(<?php echo $img['id']; ?>)">删除</button>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        <?php else: ?>
                            <tr>
                                <td colspan="6" style="text-align: center; color: rgba(255, 255, 255, 0.6);">暂无外链图片</td>
                            </tr>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>

            <?php if ($totalPages > 1): ?>
                <div class="pagination">
                    <?php if ($page > 1): ?>
                        <a href="?page=1">首页</a>
                        <a href="?page=<?php echo $page - 1; ?>">上一页</a>
                    <?php endif; ?>
                    
                    <?php for ($i = max(1, $page - 2); $i <= min($totalPages, $page + 2); $i++): ?>
                        <?php if ($i === $page): ?>
                            <span class="active"><?php echo $i; ?></span>
                        <?php else: ?>
                            <a href="?page=<?php echo $i; ?>"><?php echo $i; ?></a>
                        <?php endif; ?>
                    <?php endfor; ?>
                    
                    <?php if ($page < $totalPages): ?>
                        <a href="?page=<?php echo $page + 1; ?>">下一页</a>
                        <a href="?page=<?php echo $totalPages; ?>">末页</a>
                    <?php endif; ?>
                </div>
            <?php endif; ?>

            <?php if (!empty($externalImages)): ?>
                <button class="btn btn-danger" onclick="batchDelete()" style="width: 100%; margin-top: 20px;">🗑️ 批量删除</button>
            <?php endif; ?>
        </div>
    </div>

    <!-- 编辑模态框 -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <button class="close-btn" onclick="closeEditModal()">&times;</button>
            <div class="modal-title">编辑外链图片</div>
            <div class="form-group">
                <label>图片 URL</label>
                <input type="text" id="editUrlInput" placeholder="输入新的图片 URL">
            </div>
            <input type="hidden" id="editImageId">
            <button class="btn" onclick="saveEditImage()" style="width: 100%; margin-top: 15px;">✅ 保存修改</button>
        </div>
    </div>

    <script>
        let editingImageId = null;

        function showNotification(message, isError = false) {
            const notification = document.createElement('div');
            notification.className = 'notification' + (isError ? ' error' : '');
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }

        function addExternalImage() {
            const url = document.getElementById('urlInput').value.trim();

            if (!url) {
                showNotification('请输入 URL', true);
                return;
            }

            const btn = event.currentTarget;
            const originalText = btn.innerHTML;
            btn.innerHTML = '⏳ 正在检测...';
            btn.disabled = true;

            const formData = new FormData();
            formData.append('action', 'add');
            formData.append('url', url);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    document.getElementById('urlInput').value = '';
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, true);
                    btn.innerHTML = originalText;
                    btn.disabled = false;
                }
            })
            .catch(error => {
                showNotification('操作失败: ' + error.message, true);
                btn.innerHTML = originalText;
                btn.disabled = false;
            });
        }

        function editImage(id, url) {
            editingImageId = id;
            document.getElementById('editImageId').value = id;
            document.getElementById('editUrlInput').value = url;
            document.getElementById('editModal').classList.add('active');
        }

        function closeEditModal() {
            document.getElementById('editModal').classList.remove('active');
            editingImageId = null;
        }

        function saveEditImage() {
            const id = document.getElementById('editImageId').value;
            const url = document.getElementById('editUrlInput').value.trim();

            if (!url) {
                showNotification('请输入 URL', true);
                return;
            }

            const formData = new FormData();
            formData.append('action', 'edit');
            formData.append('id', id);
            formData.append('url', url);

            fetch('', {
                method: 'POST',
                body: formData
            })
            .then(response => response.json())
            .then(data => {
                if (data.success) {
                    showNotification(data.message);
                    setTimeout(() => location.reload(), 1000);
                } else {
                    showNotification(data.message, true);
                }
            })
            .catch(error => {
                showNotification('操作失败: ' + error.message, true);
            });
        }

        function deleteImage(id) {
            if (confirm('确定要删除这张外链图片吗？')) {
                const formData = new FormData();
                formData.append('action', 'delete');
                formData.append('id', id);

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.message, true);
                    }
                })
                .catch(error => {
                    showNotification('操作失败: ' + error.message, true);
                });
            }
        }

        function toggleSelectAll() {
            const selectAll = document.getElementById('selectAll').checked;
            const checkboxes = document.querySelectorAll('.image-checkbox');
            checkboxes.forEach(checkbox => {
                checkbox.checked = selectAll;
            });
        }

        function batchDelete() {
            const checkboxes = document.querySelectorAll('.image-checkbox:checked');
            if (checkboxes.length === 0) {
                showNotification('请先选择要删除的图片', true);
                return;
            }

            if (confirm(`确定要删除选中的 ${checkboxes.length} 张外链图片吗？`)) {
                const ids = Array.from(checkboxes).map(cb => cb.value);
                const formData = new FormData();
                formData.append('action', 'batch_delete');
                formData.append('ids', JSON.stringify(ids));

                fetch('', {
                    method: 'POST',
                    body: formData
                })
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        showNotification(data.message);
                        setTimeout(() => location.reload(), 1000);
                    } else {
                        showNotification(data.message, true);
                    }
                })
                .catch(error => {
                    showNotification('操作失败: ' + error.message, true);
                });
            }
        }
    </script>
</body>
</html>
