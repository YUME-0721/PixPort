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

// 引入数据库类
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

// 获取基本系统信息
function getBasicSystemInfo() {
    return [
        'php_version' => PHP_VERSION,
        'php_os' => PHP_OS,
        'server_software' => $_SERVER['SERVER_SOFTWARE'] ?? 'Unknown',
        'server_name' => $_SERVER['SERVER_NAME'] ?? 'localhost',
        'memory_limit' => ini_get('memory_limit'),
        'memory_usage' => formatBytes(memory_get_usage(true)),
        'current_time' => date('Y-m-d H:i:s'),
        'client_ip' => $_SERVER['REMOTE_ADDR'] ?? 'Unknown',
        'user_agent' => $_SERVER['HTTP_USER_AGENT'] ?? 'Unknown'
    ];
}

// 格式化字节数
function formatBytes($size) {
    $units = ['B', 'KB', 'MB', 'GB'];
    for ($i = 0; $size > 1024 && $i < 3; $i++) {
        $size /= 1024;
    }
    return round($size, 2) . ' ' . $units[$i];
}

// 获取数据库信息
function getDatabaseInfo() {
    $dbInfo = [
        'status' => '❌ 未连接',
        'type' => 'Unknown',
        'path' => 'Unknown',
        'version' => 'Unknown',
        'error' => null
    ];
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        $dbType = $db->getDatabaseType();
        
        $dbInfo['type'] = strtoupper($dbType);
        
        if ($dbType === 'sqlite') {
            // SQLite 信息
            $versionResult = $pdo->query('SELECT sqlite_version() as version')->fetch();
            $dbInfo['version'] = 'SQLite ' . ($versionResult['version'] ?? 'Unknown');
            $dbInfo['path'] = dirname(__DIR__, 2) . '/database/pixport.db';
            
            // 获取数据库大小
            $dbPath = dirname(__DIR__, 2) . '/database/pixport.db';
            if (file_exists($dbPath)) {
                $dbInfo['database_size'] = round(filesize($dbPath) / 1024 / 1024, 2) . ' MB';
            }
        } else {
            // MySQL 信息（兼容模式）
            $dbInfo['host'] = getenv('DB_HOST') ?: 'mysql';
            $dbInfo['port'] = getenv('DB_PORT') ?: '3306';
            $dbInfo['database'] = getenv('DB_NAME') ?: 'pixport';
            $dbInfo['user'] = getenv('DB_USER') ?: 'root';
            
            $versionResult = $pdo->query('SELECT VERSION() as version')->fetch();
            $dbInfo['version'] = $versionResult['version'] ?? 'Unknown';
            
            // 获取数据库大小
            $sizeResult = $db->fetchOne(
                "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
                 FROM information_schema.tables 
                 WHERE table_schema = :db_name",
                ['db_name' => getenv('DB_NAME') ?: 'pixport']
            );
            $dbInfo['database_size'] = ($sizeResult['size_mb'] ?? 0) . ' MB';
        }
        
        $dbInfo['status'] = '✅ 已连接';
    } catch (Exception $e) {
        $dbInfo['status'] = '❌ 连接失败';
        $dbInfo['error'] = $e->getMessage();
    }
    
    return $dbInfo;
}

// 格式化运行时间
function formatUptime($seconds) {
    $days = intdiv($seconds, 86400);
    $hours = intdiv($seconds % 86400, 3600);
    $minutes = intdiv($seconds % 3600, 60);
    return $days . '天 ' . $hours . '小时 ' . $minutes . '分钟';
}

// 获取本地图片详细统计
function getLocalImageStats() {
    $stats = [
        'pc' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0],
        'pe' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0]
    ];
    
    try {
        $db = Database::getInstance();
        $sql = "SELECT device_type, format, COUNT(*) as count 
                FROM images 
                WHERE storage_type = 'local' 
                GROUP BY device_type, format";
        $results = $db->fetchAll($sql);
        foreach ($results as $row) {
            $deviceType = $row['device_type'];
            $format = $row['format'];
            $count = (int)$row['count'];
            if (isset($stats[$deviceType][$format])) {
                $stats[$deviceType][$format] = $count;
            }
        }
    } catch (Exception $e) {
        error_log("从数据库统计本地图片失败: " . $e->getMessage());
    }
    return $stats;
}

// 获取外链图片详细统计
function getExternalImageStats() {
    $stats = [
        'pc' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0],
        'pe' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0]
    ];
    
    try {
        $db = Database::getInstance();
        $sql = "SELECT device_type, format, COUNT(*) as count 
                FROM images 
                WHERE storage_type = 'external' 
                GROUP BY device_type, format";
        $results = $db->fetchAll($sql);
        foreach ($results as $row) {
            $deviceType = $row['device_type'];
            $format = $row['format'];
            $count = (int)$row['count'];
            if (isset($stats[$deviceType][$format])) {
                $stats[$deviceType][$format] = $count;
            }
        }
    } catch (Exception $e) {
        error_log("从数据库统计外链图片失败: " . $e->getMessage());
    }
    return $stats;
}

// 获取图片统计信息
function getImageStats() {
    $db = Database::getInstance();
    $stats = [
        'total' => 0,
        'local' => 0,
        'external' => 0,
        'total_size' => '0 B',
        'albums' => 0,
        'local_storage_path' => dirname(__DIR__, 2) . '/images'
    ];
    
    try {
        // 总数
        $totalRes = $db->fetchOne("SELECT COUNT(*) as count FROM images");
        $stats['total'] = $totalRes['count'] ?? 0;
        
        // 本地 vs 外链
        $localRes = $db->fetchOne("SELECT COUNT(*) as count FROM images WHERE storage_type = 'local'");
        $stats['local'] = $localRes['count'] ?? 0;
        $stats['external'] = $stats['total'] - $stats['local'];
        
        // 总大小 (字节)
        $sizeRes = $db->fetchOne("SELECT SUM(size) as total_size FROM images WHERE storage_type = 'local'");
        $stats['total_size'] = formatBytes($sizeRes['total_size'] ?: 0);
        
        // 相册数
        $albumRes = $db->fetchOne("SELECT COUNT(*) as count FROM albums");
        $stats['albums'] = $albumRes['count'] ?? 0;
    } catch (Exception $e) {
        // 忽略统计错误
    }
    
    return $stats;
}

// 处理刷新统计请求
if (isset($_GET['refresh']) && $_GET['refresh'] === 'stats') {
    $localStats = getLocalImageStats();
    $externalStats = getExternalImageStats();
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'localStats' => $localStats,
        'externalStats' => $externalStats,
        'timestamp' => time()
    ]);
    exit;
}

$info = getBasicSystemInfo();
$dbInfo = getDatabaseInfo();
$imageStats = getImageStats();
$localStats = getLocalImageStats();
$externalStats = getExternalImageStats();
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>监控面板 - PixPort</title>
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
            max-width: 1000px;
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
        h2 {
            color: white;
            margin-bottom: 25px;
            font-size: 22px;
            border-bottom: 2px solid rgba(102, 126, 234, 0.3);
            padding-bottom: 10px;
            display: flex;
            align-items: center;
            gap: 10px;
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 15px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: 600;
            color: rgba(255, 255, 255, 0.7);
            font-size: 14px;
        }
        .value {
            color: white;
            font-weight: 600;
            font-size: 14px;
        }
        .status {
            text-align: center;
            color: white;
            font-size: 16px;
            margin-bottom: 30px;
            padding: 12px;
            background: rgba(40, 167, 69, 0.2);
            border-radius: 10px;
            border: 1px solid rgba(40, 167, 69, 0.3);
            font-weight: 600;
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
        .sub-nav {
            display: flex;
            flex-direction: column;
            gap: 5px;
            margin-left: 10px;
            padding-left: 10px;
            border-left: 1px solid rgba(255, 255, 255, 0.1);
            margin-top: -5px;
            margin-bottom: 5px;
        }
        .sidebar.collapsed .sub-nav {
            display: none;
        }
        .sub-nav-item {
            font-size: 13px;
            padding: 8px 12px;
            color: rgba(255, 255, 255, 0.6);
            text-decoration: none;
            border-radius: 8px;
            transition: all 0.2s;
            cursor: pointer;
            display: block;
        }
        .sub-nav-item:hover {
            color: white;
            background: rgba(255, 255, 255, 0.1);
        }
        .sub-nav-item.active {
            color: white;
            background: rgba(255, 255, 255, 0.15);
            font-weight: bold;
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
        .sub-tab-content {
            display: none;
            animation: fadeIn 0.4s ease-out;
        }
        .sub-tab-content.active {
            display: block;
        }
        @keyframes fadeIn {
            from { opacity: 0; transform: translateY(10px); }
            to { opacity: 1; transform: translateY(0); }
        }
        #current-time {
            font-family: Arial, sans-serif;
        }

        /* 统计卡片与图表样式 */
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
        @keyframes slideIn {
            from { transform: translateX(400px); opacity: 0; }
            to { transform: translateX(0); opacity: 1; }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
            transition: transform 0.3s;
        }
        .stat-card:hover {
            transform: translateY(-5px);
            background: rgba(255, 255, 255, 0.15);
        }
        .stat-card h3 {
            color: rgba(255, 255, 255, 0.8);
            font-size: 14px;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }
        .stat-card .number {
            font-size: 32px;
            font-weight: 700;
            color: white;
            margin: 10px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .stat-card .desc {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
        }
        .chart-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin-top: 25px;
        }
        .chart-container {
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        @media (max-width: 768px) {
            .chart-row {
                grid-template-columns: 1fr;
            }
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/upload.php" style="text-decoration: none;">
            <h1 style="cursor: pointer;">
                <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
                <span>- 监控面板</span>
            </h1>
        </a>
    </div>

    <div class="sidebar" id="sidebar">
        <a href="/upload.php" class="nav-item">
            <span class="btn-icon">📤</span>
            <span class="btn-text">上传图片</span>
        </a>
        <a href="/gallery.php" class="nav-item">
            <span class="btn-icon">🎨</span>
            <span class="btn-text">图片画廊</span>
        </a>
        <div class="nav-item active">
            <span class="btn-icon">📊</span>
            <span class="btn-text">监控面板</span>
        </div>
        <div class="sub-nav">
            <div class="sub-nav-item active" id="sub-nav-system" onclick="switchSubTab('system')">系统监控</div>
            <div class="sub-nav-item" id="sub-nav-database" onclick="switchSubTab('database')">数据库监控</div>
            <div class="sub-nav-item" id="sub-nav-images" onclick="switchSubTab('images')">图片统计</div>
        </div>
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

    <!-- 系统监控 Tab -->
    <div class="container sub-tab-content active" id="system-tab">
        <h2>📊 系统监控</h2>
        <div class="status">✅ 服务运行中</div>
    
        <div class="info-item">
            <span class="label">PHP 版本</span>
            <span class="value"><?php echo $info['php_version']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">操作系统</span>
            <span class="value"><?php echo $info['php_os']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">服务器软件</span>
            <span class="value"><?php echo $info['server_software']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">服务器名称</span>
            <span class="value">Prisma</span>
        </div>
        
        <div class="info-item">
            <span class="label">内存限制</span>
            <span class="value"><?php echo $info['memory_limit']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">内存使用</span>
            <span class="value"><?php echo $info['memory_usage']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">当前时间</span>
            <span class="value" id="current-time"><?php echo $info['current_time']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">客户端IP</span>
            <span class="value"><?php echo $info['client_ip']; ?></span>
        </div>
    </div>

    <!-- 数据库监控 Tab -->
    <div class="container sub-tab-content" id="database-tab">
        <h2>🗄️ 数据库监控</h2>
        <div class="status"><?php echo $dbInfo['status']; ?></div>
        
        <?php if ($dbInfo['error']): ?>
        <div class="error-message" style="background: rgba(220, 53, 69, 0.3); border: 1px solid rgba(220, 53, 69, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #ffb3b3;">
            <strong>⚠️ 连接错误:</strong> <?php echo htmlspecialchars($dbInfo['error']); ?>
        </div>
        <?php endif; ?>
        
        <div class="info-item">
            <span class="label">数据库类型</span>
            <span class="value"><?php echo $dbInfo['type']; ?></span>
        </div>
        
        <?php if ($dbInfo['type'] === 'SQLITE'): ?>
        <div class="info-item">
            <span class="label">数据库路径</span>
            <span class="value" style="font-size: 12px; word-break: break-all;"><?php echo $dbInfo['path']; ?></span>
        </div>
        <?php else: ?>
        <div class="info-item">
            <span class="label">主机地址</span>
            <span class="value"><?php echo $dbInfo['host'] ?? 'N/A'; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">端口号</span>
            <span class="value"><?php echo $dbInfo['port'] ?? 'N/A'; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">数据库名</span>
            <span class="value"><?php echo $dbInfo['database'] ?? 'N/A'; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">用户名</span>
            <span class="value"><?php echo $dbInfo['user'] ?? 'N/A'; ?></span>
        </div>
        <?php endif; ?>
        
        <div class="info-item">
            <span class="label">数据库版本</span>
            <span class="value"><?php echo $dbInfo['version']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">数据库大小</span>
            <span class="value"><?php echo $dbInfo['database_size'] ?? '0 MB'; ?></span>
        </div>
    </div>

    <!-- 图片统计 Tab -->
    <div class="container sub-tab-content" id="images-tab">
        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
            <h2 style="margin-bottom: 0;">🖼️ 图片统计</h2>
            <button onclick="refreshStats()" style="
                padding: 10px 20px;
                background: rgba(40, 167, 69, 0.8);
                color: white;
                border: none;
                border-radius: 8px;
                font-weight: 600;
                cursor: pointer;
                transition: all 0.3s;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.2);
            " onmouseover="this.style.background='rgba(40, 167, 69, 1)'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='rgba(40, 167, 69, 0.8)'; this.style.transform='translateY(0)'">
                🔄 刷新数据
            </button>
        </div>
        
        <div class="stats-grid">
            <div class="stat-card">
                <h3>📸 本地总图片</h3>
                <div class="number" id="total-local">0</div>
                <div class="desc">本地存储的图片数量</div>
            </div>
            <div class="stat-card">
                <h3>🌐 外链总数</h3>
                <div class="number" id="total-external">0</div>
                <div class="desc">外链配置的图片数量</div>
            </div>
            <div class="stat-card">
                <h3>📁 PC 端图片</h3>
                <div class="number" id="total-pc">0</div>
                <div class="desc">桌面端图片数量</div>
            </div>
            <div class="stat-card">
                <h3>📱 PE 端图片</h3>
                <div class="number" id="total-pe">0</div>
                <div class="desc">移动端图片数量</div>
            </div>
            <div class="stat-card">
                <h3>💾 存储占用</h3>
                <div class="number" style="font-size: 24px; padding: 4px 0;"><?php echo $imageStats['total_size']; ?></div>
                <div class="desc">本地文件总大小</div>
            </div>
            <div class="stat-card">
                <h3>📚 相册总数</h3>
                <div class="number"><?php echo $imageStats['albums']; ?></div>
                <div class="desc">分类相册总数</div>
            </div>
        </div>

        <div class="chart-row">
            <div class="chart-container">
                <h3 style="text-align: center; color: white; margin-bottom: 15px; font-size: 16px; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">图片来源分布</h3>
                <canvas id="sourceChart"></canvas>
            </div>
            <div class="chart-container">
                <h3 style="text-align: center; color: white; margin-bottom: 15px; font-size: 16px; text-shadow: 1px 1px 3px rgba(0,0,0,0.3);">图片格式分布</h3>
                <canvas id="formatChart"></canvas>
            </div>
        </div>
    </div>

    <a href="?logout=1" class="floating-logout" title="退出登录">
        <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M15 4.001H5v14a2 2 0 0 0 2 2h8m1-5l3-3m0 0l-3-3m3 3H9"/></svg>
    </a>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // 显示通知
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            document.body.appendChild(notification);
            setTimeout(() => notification.remove(), 3000);
        }

        // 刷新统计数据
        function refreshStats() {
            fetch('?refresh=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        const localStats = data.localStats;
                        const externalStats = data.externalStats;
                                
                        const totalLocal = Object.values(localStats.pc).reduce((a, b) => a + b, 0) + 
                                          Object.values(localStats.pe).reduce((a, b) => a + b, 0);
                        const totalExternal = Object.values(externalStats.pc).reduce((a, b) => a + b, 0) + 
                                             Object.values(externalStats.pe).reduce((a, b) => a + b, 0);
                        const totalPC = Object.values(localStats.pc).reduce((a, b) => a + b, 0) + 
                                       Object.values(externalStats.pc).reduce((a, b) => a + b, 0);
                        const totalPE = Object.values(localStats.pe).reduce((a, b) => a + b, 0) + 
                                       Object.values(externalStats.pe).reduce((a, b) => a + b, 0);
                                
                        document.getElementById('total-local').textContent = totalLocal;
                        document.getElementById('total-external').textContent = totalExternal;
                        document.getElementById('total-pc').textContent = totalPC;
                        document.getElementById('total-pe').textContent = totalPE;
                                
                        const stats = { local: localStats, external: externalStats };
                        createSourceChart(stats);
                        createFormatChart(stats);
                        showNotification('✅ 统计数据已更新');
                    }
                })
                .catch(err => showNotification('❌ 更新失败'));
        }

        function createSourceChart(stats) {
            const ctx = document.getElementById('sourceChart');
            if (window.sourceChartInstance) window.sourceChartInstance.destroy();
            
            const localPC = Object.values(stats.local.pc).reduce((a, b) => a + b, 0);
            const localPE = Object.values(stats.local.pe).reduce((a, b) => a + b, 0);
            const externalPC = Object.values(stats.external.pc).reduce((a, b) => a + b, 0);
            const externalPE = Object.values(stats.external.pe).reduce((a, b) => a + b, 0);
            
            window.sourceChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['本地 PC', '本地 PE', '外链 PC', '外链 PE'],
                    datasets: [{
                        data: [localPC, localPE, externalPC, externalPE],
                        backgroundColor: ['rgba(102, 126, 234, 0.8)', 'rgba(118, 75, 162, 0.8)', 'rgba(255, 159, 64, 0.8)', 'rgba(255, 205, 86, 0.8)'],
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: 'white', font: { size: 12 } } }
                    }
                }
            });
        }

        function createFormatChart(stats) {
            const ctx = document.getElementById('formatChart');
            if (window.formatChartInstance) window.formatChartInstance.destroy();
            
            const labels = ['JPEG', 'WebP', 'AVIF', 'PNG', 'GIF'];
            const data = labels.map(label => {
                const key = label.toLowerCase();
                return (stats.local.pc[key] || 0) + (stats.local.pe[key] || 0) + 
                       (stats.external.pc[key] || 0) + (stats.external.pe[key] || 0);
            });
            
            window.formatChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: labels,
                    datasets: [{
                        data: data,
                        backgroundColor: ['rgba(255, 99, 132, 0.8)', 'rgba(54, 162, 235, 0.8)', 'rgba(75, 192, 192, 0.8)', 'rgba(153, 102, 255, 0.8)', 'rgba(255, 205, 86, 0.8)'],
                        borderColor: 'rgba(255, 255, 255, 0.2)',
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    plugins: {
                        legend: { position: 'bottom', labels: { color: 'white', font: { size: 12 } } }
                    }
                }
            });
        }

        function initImageStats() {
            const stats = {
                local: <?php echo json_encode($localStats); ?>,
                external: <?php echo json_encode($externalStats); ?>
            };
            
            const totalLocal = Object.values(stats.local.pc).reduce((a, b) => a + b, 0) + 
                              Object.values(stats.local.pe).reduce((a, b) => a + b, 0);
            const totalExternal = Object.values(stats.external.pc).reduce((a, b) => a + b, 0) + 
                                 Object.values(stats.external.pe).reduce((a, b) => a + b, 0);
            const totalPC = Object.values(stats.local.pc).reduce((a, b) => a + b, 0) + 
                           Object.values(stats.external.pc).reduce((a, b) => a + b, 0);
            const totalPE = Object.values(stats.local.pe).reduce((a, b) => a + b, 0) + 
                           Object.values(stats.external.pe).reduce((a, b) => a + b, 0);
            
            document.getElementById('total-local').textContent = totalLocal;
            document.getElementById('total-external').textContent = totalExternal;
            document.getElementById('total-pc').textContent = totalPC;
            document.getElementById('total-pe').textContent = totalPE;
            
            createSourceChart(stats);
            createFormatChart(stats);
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

        function switchSubTab(tabId) {
            // 切换按钮状态
            document.querySelectorAll('.sub-nav-item').forEach(btn => {
                btn.classList.remove('active');
            });
            const targetBtn = document.getElementById('sub-nav-' + tabId);
            if (targetBtn) targetBtn.classList.add('active');

            // 切换内容显示
            document.querySelectorAll('.sub-tab-content').forEach(content => {
                content.classList.remove('active');
            });
            const targetContent = document.getElementById(tabId + '-tab');
            if (targetContent) targetContent.classList.add('active');
            
            localStorage.setItem('activeMonitorTab', tabId);
            // 更新URL但不刷新
            window.history.replaceState({}, '', '?tab=' + tabId);
        }

        // 页面加载时恢复状态
        window.onload = function() {
            // 恢复侧边栏状态
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            const sidebar = document.getElementById('sidebar');
            const icon = document.getElementById('toggleIcon');
            if (isCollapsed) {
                sidebar.classList.add('collapsed');
                icon.innerText = '➡️';
            } else {
                sidebar.classList.remove('collapsed');
                icon.innerText = '⬅️';
            }

            // 恢复标签页
            const urlParams = new URLSearchParams(window.location.search);
            const urlTab = urlParams.get('tab');
            const lastTab = urlTab || localStorage.getItem('activeMonitorTab') || 'system';
            switchSubTab(lastTab);

            // 初始化图片统计图表
            initImageStats();
        }

        // 动态更新时间
        function updateTime() {
            const now = new Date();
            const year = now.getFullYear();
            const month = String(now.getMonth() + 1).padStart(2, '0');
            const day = String(now.getDate()).padStart(2, '0');
            const hours = String(now.getHours()).padStart(2, '0');
            const minutes = String(now.getMinutes()).padStart(2, '0');
            const seconds = String(now.getSeconds()).padStart(2, '0');
            
            const timeString = `${year}-${month}-${day} ${hours}:${minutes}:${seconds}`;
            const timeElement = document.getElementById('current-time');
            if (timeElement) {
                timeElement.textContent = timeString;
            }
        }
        
        setInterval(updateTime, 1000);
        updateTime();
    </script>
</body>
</html>
