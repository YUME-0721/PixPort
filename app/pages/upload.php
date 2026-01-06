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

// 处理上传
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_FILES['image'])) {
    $response = ['success' => false, 'message' => ''];
    
    try {
        $file = $_FILES['image'];
        
        // 验证上传错误
        if ($file['error'] !== UPLOAD_ERR_OK) {
            throw new Exception('上传失败: ' . $file['error']);
        }
        
        // 验证文件类型
        $allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];
        $finfo = finfo_open(FILEINFO_MIME_TYPE);
        $mimeType = finfo_file($finfo, $file['tmp_name']);
        finfo_close($finfo);
        
        if (!in_array($mimeType, $allowedTypes)) {
            throw new Exception('不支持的图片格式');
        }
        
        // 获取图片尺寸
        $imageInfo = getimagesize($file['tmp_name']);
        if (!$imageInfo) {
            throw new Exception('无法读取图片信息');
        }
        
        $width = $imageInfo[0];
        $height = $imageInfo[1];
        
        // 判断横屏/竖屏
        $type = ($width >= $height) ? 'pc' : 'pe';
        
        // 判断格式
        $ext = strtolower(pathinfo($file['name'], PATHINFO_EXTENSION));
        if ($ext === 'jpg') $ext = 'jpeg';
        
        $format = $ext;
        
        // 生成新文件名（时间戳 + 随机数）
        $newFilename = date('YmdHis') . '_' . substr(md5(uniqid()), 0, 8) . '.' . $format;
        
        // 目标目录
        $targetDir = __DIR__ . '/../../images/' . $type;
        if (!is_dir($targetDir)) {
            mkdir($targetDir, 0777, true);
        }
        
        $targetPath = $targetDir . '/' . $newFilename;
        
        // 直接移动文件
        if (!move_uploaded_file($file['tmp_name'], $targetPath)) {
            throw new Exception('文件保存失败');
        }
        
        // 记录上传信息到JSON文件（兼容旧版本）
        $uploaderIP = $_SERVER['REMOTE_ADDR'] ?? 'Unknown';
        $uploadTime = date('Y-m-d H:i:s');
        
        $metaFile = $targetDir . '/.metadata.json';
        $metadata = [];
        if (file_exists($metaFile)) {
            $metadata = json_decode(file_get_contents($metaFile), true) ?? [];
        }
        
        $metadata[$newFilename] = [
            'uploader_ip' => $uploaderIP,
            'upload_time' => $uploadTime,
            'original_name' => $file['name'],
            'width' => $width,
            'height' => $height,
            'size' => $file['size']
        ];
        
        file_put_contents($metaFile, json_encode($metadata, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // 生成访问URL
        $relativePath = '/images/' . $type . '/' . $newFilename;
        $fullUrl = 'http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . $relativePath;
        
        // 保存到数据库
        try {
            $db = Database::getInstance();
            $imageId = $db->insert('images', [
                'filename' => $newFilename,
                'url' => $fullUrl,
                'storage_type' => 'local',
                'device_type' => $type,
                'format' => $format,
                'local_path' => $targetPath,
                'width' => $width,
                'height' => $height,
                'file_size' => $file['size'],
                'uploader_ip' => $uploaderIP,
                'upload_time' => $uploadTime
            ]);
        } catch (Exception $dbError) {
            // 数据库写入失败不影响上传，记录日志
            error_log("数据库写入失败: " . $dbError->getMessage());
        }
        
        // 异步刷新图片统计（不阻塞响应）
        session_write_close(); // 释放 Session 锁
        @file_get_contents('http' . (isset($_SERVER['HTTPS']) ? 's' : '') . '://' . $_SERVER['HTTP_HOST'] . '/app/pages/file.php?refresh=stats', false, stream_context_create([
            'http' => [
                'timeout' => 1,
                'ignore_errors' => true
            ]
        ]));
        
        $response = [
            'success' => true,
            'message' => '上传成功',
            'data' => [
                'filename' => $newFilename,
                'url' => $fullUrl,
                'type' => $type,
                'format' => $format,
                'width' => $width,
                'height' => $height,
                'size' => $file['size'],
                'uploader_ip' => $uploaderIP,
                'upload_time' => $uploadTime
            ]
        ];
        
    } catch (Exception $e) {
        $response = [
            'success' => false,
            'message' => $e->getMessage()
        ];
    }
    
    header('Content-Type: application/json');
    echo json_encode($response, JSON_UNESCAPED_UNICODE);
    exit;
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>图片上传 - PixPort</title>
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
            padding: 40px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }
        .section {
            margin-bottom: 40px;
        }
        .section h2 {
            color: white;
            font-size: 24px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .section h3 {
            color: rgba(255, 255, 255, 0.95);
            font-size: 18px;
            margin: 20px 0 10px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }
        .feature-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .feature-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .feature-card h4 {
            color: white;
            margin-bottom: 10px;
            font-size: 16px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }
        .feature-card p {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
            line-height: 1.6;
        }
        .code-block {
            background: #2d2d2d;
            color: #f8f8f2;
            padding: 20px;
            border-radius: 8px;
            overflow-x: auto;
            margin: 15px 0;
            font-family: 'Consolas', 'Monaco', monospace;
            font-size: 14px;
            line-height: 1.6;
        }
        .code-block .comment {
            color: #75715e;
        }
        .code-block .string {
            color: #e6db74;
        }
        .code-block .keyword {
            color: #66d9ef;
        }
        .param-table {
            width: 100%;
            border-collapse: collapse;
            margin: 15px 0;
        }
        .param-table th,
        .param-table td {
            padding: 12px;
            text-align: left;
            border: 1px solid rgba(255, 255, 255, 0.2);
            color: rgba(255, 255, 255, 0.9);
        }
        .param-table th {
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-weight: 600;
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        .param-table tr:nth-child(even) {
            background: rgba(255, 255, 255, 0.05);
        }
        .upload-area {
            border: 3px dashed rgba(255, 255, 255, 0.5);
            border-radius: 12px;
            padding: 80px 40px;
            text-align: center;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.05);
            margin-bottom: 30px;
        }
        .upload-area:hover, .upload-area.drag-over {
            border-color: rgba(102, 126, 234, 0.8);
            background: rgba(102, 126, 234, 0.1);
            transform: scale(1.02);
        }
        .file-list {
            background: rgba(255, 255, 255, 0.1);
            border-radius: 12px;
            padding: 15px;
            margin-top: 20px;
            display: none;
        }
        .file-list.show {
            display: block;
        }
        .file-item {
            display: flex;
            flex-direction: column;
            gap: 10px;
            padding: 15px;
            border-radius: 12px;
            background: rgba(255, 255, 255, 0.15);
            margin-bottom: 15px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        .file-item:last-child {
            margin-bottom: 0;
        }
        .file-item-header {
            display: flex;
            align-items: center;
            gap: 15px;
        }
        .file-preview {
            width: 50px;
            height: 50px;
            border-radius: 8px;
            object-fit: cover;
        }
        .file-info {
            flex: 1;
        }
        .file-name {
            font-weight: 600;
            color: white;
            margin-bottom: 5px;
        }
        .file-size {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.8);
        }
        .file-status {
            font-size: 12px;
            color: rgba(255, 255, 255, 0.6);
        }
        .file-status.success {
            color: #28a745;
            font-weight: 600;
        }
        .file-status.error {
            color: #dc3545;
            font-weight: 600;
        }
        .file-actions {
            display: flex;
            gap: 10px;
        }
        .action-btn {
            width: 35px;
            height: 35px;
            border: none;
            border-radius: 6px;
            cursor: pointer;
            transition: all 0.3s;
            background: rgba(255, 255, 255, 0.2);
            color: white;
            font-size: 16px;
        }
        .action-btn:hover {
            background: rgba(255, 255, 255, 0.3);
        }
        .upload-icon {
            font-size: 64px;
            margin-bottom: 20px;
        }
        .upload-text {
            color: white;
            font-size: 20px;
            font-weight: 600;
            margin-bottom: 10px;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .upload-hint {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
        }
        .result-area {
            display: none;
            margin-top: 20px;
        }
        .result-area.show {
            display: block;
        }
        .tab-container {
            background: rgba(255, 255, 255, 0.95);
            border-radius: 8px;
            padding: 20px;
        }
        .file-list .tab-container {
            background: rgba(255, 255, 255, 0.12);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.25);
            border-radius: 10px;
            padding: 15px;
        }
        .file-list .tab-buttons {
            display: flex;
            gap: 5px;
            margin-bottom: 12px;
            border-bottom: 1px solid rgba(255, 255, 255, 0.15);
            padding-bottom: 8px;
            flex-wrap: wrap;
        }
        .file-list .tab-buttons .tab-btn {
            padding: 6px 12px;
            border: none;
            background: rgba(255, 255, 255, 0.15);
            color: rgba(255, 255, 255, 0.9);
            font-size: 13px;
            border-radius: 6px;
            transition: all 0.3s;
            cursor: pointer;
            white-space: nowrap;
        }
        .file-list .tab-buttons .tab-btn.active {
            background: rgba(102, 126, 234, 0.6);
            color: white;
            font-weight: 600;
            box-shadow: 0 2px 8px rgba(102, 126, 234, 0.3);
        }
        .file-list .tab-buttons .tab-btn:hover:not(.active) {
            background: rgba(255, 255, 255, 0.25);
        }
        .file-list .link-input {
            background: rgba(0, 0, 0, 0.25);
            color: white;
            border: 1px solid rgba(255, 255, 255, 0.3);
            font-size: 13px;
        }
        .file-list .link-input::selection {
            background: rgba(102, 126, 234, 0.5);
        }
        .file-list .copy-inline-btn {
            background: rgba(40, 167, 69, 0.7);
        }
        .file-list .copy-inline-btn:hover {
            background: rgba(40, 167, 69, 0.9);
        }
        .file-list .copy-inline-btn.copied {
            background: rgba(108, 117, 125, 0.7);
        }
        .tab-buttons {
            display: flex;
            gap: 10px;
            margin-bottom: 15px;
            border-bottom: 1px solid rgba(0, 0, 0, 0.1);
        }
        .tab-buttons .tab-btn {
            padding: 10px 20px;
            border: none;
            background: transparent;
            color: #333;
            font-weight: 600;
            cursor: pointer;
            border-bottom: 2px solid transparent;
            transition: all 0.3s;
        }
        .tab-buttons .tab-btn.active {
            color: #667eea;
            border-bottom-color: #667eea;
        }
        .tab-content {
            margin-bottom: 15px;
        }
        .tab-pane {
            display: none;
        }
        .tab-pane.active {
            display: block;
        }
        .url-input-wrapper {
            position: relative;
            margin-bottom: 15px;
        }
        .link-input {
            width: 100%;
            padding: 12px 50px 12px 12px;
            border-radius: 8px;
            border: 1px solid rgba(0, 0, 0, 0.1);
            background: rgba(0, 0, 0, 0.02);
            color: #333;
            font-size: 14px;
            font-family: 'Consolas', monospace;
        }
        .copy-inline-btn {
            position: absolute;
            right: 8px;
            top: 50%;
            transform: translateY(-50%);
            padding: 8px;
            background: rgba(40, 167, 69, 0.8);
            color: white;
            border: none;
            border-radius: 6px;
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
            background: rgba(40, 167, 69, 1);
            transform: translateY(-50%) scale(1.05);
        }
        .copy-inline-btn.copied {
            background: rgba(108, 117, 125, 0.8);
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
            z-index: 9999;
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
        .loading {
            display: none;
            text-align: center;
            padding: 40px;
            color: white;
            font-size: 18px;
        }
        .loading.show {
            display: block;
        }
        .spinner {
            border: 4px solid rgba(255, 255, 255, 0.3);
            border-top: 4px solid white;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>
            <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
            <span>- 图片上传</span>
        </h1>
        <div style="display: flex; gap: 10px;">
            <a href="/app/pages/panel.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='#5568d3'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#667eea'; this.style.transform='translateY(0)'">🔧 系统管理</a>
            <a href="?logout=1" class="logout-btn">🚪 退出登录</a>
        </div>
    </div>

    <div class="container">
        <div class="upload-area" id="uploadArea">
            <div class="upload-icon">📁</div>
            <div class="upload-text">拖拽文件到这里，支持多文件同时上传</div>
            <div class="upload-hint">点击选择文件或拖拽到此处上传</div>
            <input type="file" id="fileInput" accept="image/jpeg,image/jpg,image/png,image/webp,image/avif,image/gif" multiple style="display: none;">
        </div>
        
        <div class="file-list" id="fileList"></div>
        
        <div class="result-area" id="resultArea">
            <div class="tab-container">
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="url">URL</button>
                    <button class="tab-btn" data-tab="html">HTML</button>
                    <button class="tab-btn" data-tab="bbcode">BBCode</button>
                    <button class="tab-btn" data-tab="markdown">Markdown</button>
                    <button class="tab-btn" data-tab="markdown-link">Markdown with link</button>
                    <button class="tab-btn" data-tab="thumbnail">Thumbnail url</button>
                </div>
                
                <div class="tab-content">
                    <div class="tab-pane active" id="url-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="urlLink" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('urlLink', this)">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                           </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="html-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="htmlLink" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('htmlLink', this)">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                           </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="bbcode-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="bbcodeLink" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('bbcodeLink', this)">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                           </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="markdown-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="markdownLink" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('markdownLink', this)">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                           </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="markdown-link-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="markdownLinkWithLink" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('markdownLinkWithLink', this)">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                           </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="thumbnail-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="thumbnailLink" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('thumbnailLink', this)">
                               <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                           </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div class="loading" id="loading">
            <div class="spinner"></div>
            <div>上传中，请稍候...</div>
        </div>
    </div>

    <script>
        const uploadArea = document.getElementById('uploadArea');
        const fileInput = document.getElementById('fileInput');
        const loading = document.getElementById('loading');
        const resultArea = document.getElementById('resultArea');

        // 点击上传
        uploadArea.addEventListener('click', () => {
            fileInput.click();
        });

        // 文件选择
        fileInput.addEventListener('change', (e) => {
            if (e.target.files.length > 0) {
                displayFileList(e.target.files);
            }
        });

        // 拖拽上传
        uploadArea.addEventListener('dragover', (e) => {
            e.preventDefault();
            uploadArea.classList.add('drag-over');
        });

        uploadArea.addEventListener('dragleave', () => {
            uploadArea.classList.remove('drag-over');
        });

        uploadArea.addEventListener('drop', (e) => {
            e.preventDefault();
            uploadArea.classList.remove('drag-over');
            
            if (e.dataTransfer.files.length > 0) {
                displayFileList(e.dataTransfer.files);
            }
        });

        // 显示文件列表
        function displayFileList(files) {
            const fileList = document.getElementById('fileList');
            fileList.innerHTML = '';
            fileList.classList.add('show');

            Array.from(files).forEach((file, index) => {
                const fileItem = document.createElement('div');
                fileItem.className = 'file-item';
                const fileId = 'file-' + Date.now() + '-' + index;
                fileItem.setAttribute('data-file-id', fileId);

                // 文件头部（预览图、信息、操作按钮）
                const fileHeader = document.createElement('div');
                fileHeader.className = 'file-item-header';

                const filePreview = document.createElement('img');
                filePreview.className = 'file-preview';
                filePreview.src = URL.createObjectURL(file);

                const fileInfo = document.createElement('div');
                fileInfo.className = 'file-info';

                const fileName = document.createElement('div');
                fileName.className = 'file-name';
                fileName.textContent = file.name;

                const fileSize = document.createElement('div');
                fileSize.className = 'file-size';
                fileSize.textContent = (file.size / (1024 * 1024)).toFixed(2) + ' MB';

                const fileStatus = document.createElement('div');
                fileStatus.className = 'file-status';
                fileStatus.textContent = '等待上传';

                fileInfo.appendChild(fileName);
                fileInfo.appendChild(fileSize);
                fileInfo.appendChild(fileStatus);

                const fileActions = document.createElement('div');
                fileActions.className = 'file-actions';

                const deleteBtn = document.createElement('button');
                deleteBtn.className = 'action-btn';
                deleteBtn.innerHTML = '✖';
                deleteBtn.onclick = () => fileItem.remove();

                const uploadBtn = document.createElement('button');
                uploadBtn.className = 'action-btn';
                uploadBtn.innerHTML = '⬆';
                uploadBtn.onclick = () => uploadFile(file, fileStatus, uploadBtn, fileItem, fileId);

                fileActions.appendChild(deleteBtn);
                fileActions.appendChild(uploadBtn);

                fileHeader.appendChild(filePreview);
                fileHeader.appendChild(fileInfo);
                fileHeader.appendChild(fileActions);

                fileItem.appendChild(fileHeader);
                fileList.appendChild(fileItem);
            });
        }

        // 上传文件
        async function uploadFile(file, statusElement, uploadBtn, fileItem, fileId) {
            // 验证文件类型
            const allowedTypes = ['image/jpeg', 'image/jpg', 'image/png', 'image/webp', 'image/avif', 'image/gif'];
            if (!allowedTypes.includes(file.type)) {
                statusElement.textContent = '不支持的格式';
                statusElement.classList.add('error');
                return;
            }

            statusElement.textContent = '上传中...';

            const formData = new FormData();
            formData.append('image', file);

            try {
                const response = await fetch(window.location.href, {
                    method: 'POST',
                    body: formData
                });

                const result = await response.json();

                if (result.success) {
                    statusElement.textContent = '✅ 上传成功';
                    statusElement.classList.add('success');
                    // 隐藏上传按钮
                    if (uploadBtn && uploadBtn.parentNode) {
                        uploadBtn.style.display = 'none';
                    }
                    showResultInFileItem(result.data, fileItem, fileId);
                    showNotification('上传成功！');
                } else {
                    throw new Error(result.message);
                }
            } catch (error) {
                statusElement.textContent = '❌ ' + (error.message || '上传失败');
                statusElement.classList.add('error');
                showNotification(error.message || '上传失败', 'error');
            }
        }

        // 在文件项中显示结果
        function showResultInFileItem(data, fileItem, fileId) {
            // 检查是否已经有 tab-container
            let tabContainer = fileItem.querySelector('.tab-container');
            if (tabContainer) {
                tabContainer.remove();
            }

            // 创建新的 tab-container
            tabContainer = document.createElement('div');
            tabContainer.className = 'tab-container';
            tabContainer.innerHTML = `
                <div class="tab-buttons">
                    <button class="tab-btn active" data-tab="url" data-file-id="${fileId}">URL</button>
                    <button class="tab-btn" data-tab="html" data-file-id="${fileId}">HTML</button>
                    <button class="tab-btn" data-tab="bbcode" data-file-id="${fileId}">BBCode</button>
                    <button class="tab-btn" data-tab="markdown" data-file-id="${fileId}">Markdown</button>
                    <button class="tab-btn" data-tab="markdown-link" data-file-id="${fileId}">Markdown Link</button>
                    <button class="tab-btn" data-tab="thumbnail" data-file-id="${fileId}">Thumbnail</button>
                </div>
                <div class="tab-content">
                    <div class="tab-pane active" id="${fileId}-url-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="${fileId}-urlLink" value="${data.url}" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('${fileId}-urlLink', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="${fileId}-html-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="${fileId}-htmlLink" value='<img src="${data.url}" alt="${data.filename}">' readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('${fileId}-htmlLink', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="${fileId}-bbcode-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="${fileId}-bbcodeLink" value="[img]${data.url}[/img]" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('${fileId}-bbcodeLink', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="${fileId}-markdown-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="${fileId}-markdownLink" value="![${data.filename}](${data.url})" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('${fileId}-markdownLink', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="${fileId}-markdown-link-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="${fileId}-markdownLinkWithLink" value="[${data.filename}](${data.url})" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('${fileId}-markdownLinkWithLink', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                            </button>
                        </div>
                    </div>
                    <div class="tab-pane" id="${fileId}-thumbnail-tab">
                        <div class="url-input-wrapper">
                            <input type="text" class="link-input" id="${fileId}-thumbnailLink" value="${data.url}" readonly>
                            <button class="copy-inline-btn" onclick="copyFromInput('${fileId}-thumbnailLink', this)">
                                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2"><path d="M16 3H4v13"/><path d="M8 7h12v12a2 2 0 0 1-2 2h-8a2 2 0 0 1-2-2z"/></g></svg>
                            </button>
                        </div>
                    </div>
                </div>
            `;

            fileItem.appendChild(tabContainer);

            // 为这个文件项的标签页按钮绑定事件
            tabContainer.querySelectorAll('.tab-btn').forEach(btn => {
                btn.addEventListener('click', () => {
                    const tabName = btn.dataset.tab;
                    const currentFileId = btn.dataset.fileId;
                    
                    // 只在当前文件项内切换
                    tabContainer.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
                    tabContainer.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                    
                    btn.classList.add('active');
                    document.getElementById(`${currentFileId}-${tabName}-tab`).classList.add('active');
                });
            });
        }

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
        
        // 标签页切换
        document.querySelectorAll('.tab-buttons .tab-btn').forEach(btn => {
            btn.addEventListener('click', () => {
                const tabName = btn.dataset.tab;
                
                // 移除所有激活状态
                document.querySelectorAll('.tab-buttons .tab-btn').forEach(b => b.classList.remove('active'));
                document.querySelectorAll('.tab-pane').forEach(p => p.classList.remove('active'));
                
                // 添加当前激活状态
                btn.classList.add('active');
                document.getElementById(`${tabName}-tab`).classList.add('active');
            });
        });

        // 显示通知
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
