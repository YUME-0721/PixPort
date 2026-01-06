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
        'host' => getenv('DB_HOST') ?: 'mysql',
        'port' => getenv('DB_PORT') ?: '3306',
        'database' => getenv('DB_NAME') ?: 'picflow',
        'user' => getenv('DB_USER') ?: 'root',
        'version' => 'Unknown',
        'charset' => 'Unknown',
        'datadir' => 'Unknown',
        'max_connections' => 'Unknown',
        'threads_connected' => 'Unknown',
        'questions' => 'Unknown',
        'uptime' => 'Unknown',
        'error' => null
    ];
    
    try {
        $db = Database::getInstance();
        $pdo = $db->getConnection();
        
        // 获取MySQL版本
        $versionResult = $pdo->query('SELECT VERSION() as version')->fetch();
        $dbInfo['version'] = $versionResult['version'] ?? 'Unknown';
        
        // 获取系统变量
        $varsResult = $pdo->query('SHOW VARIABLES LIKE "character_set_database"')->fetch();
        $dbInfo['charset'] = $varsResult['Value'] ?? 'Unknown';
        
        // 获取数据目录
        $datadirResult = $pdo->query('SHOW VARIABLES LIKE "datadir"')->fetch();
        $dbInfo['datadir'] = $datadirResult['Value'] ?? 'Unknown';
        
        // 获取最大连接数
        $maxConnResult = $pdo->query('SHOW VARIABLES LIKE "max_connections"')->fetch();
        $dbInfo['max_connections'] = $maxConnResult['Value'] ?? 'Unknown';
        
        // 获取运行状态信息
        $statusResult = $pdo->query('SHOW STATUS WHERE Variable_name IN ("Threads_connected", "Questions", "Uptime")')->fetchAll();
        foreach ($statusResult as $row) {
            if ($row['Variable_name'] === 'Threads_connected') {
                $dbInfo['threads_connected'] = $row['Value'];
            } elseif ($row['Variable_name'] === 'Questions') {
                $dbInfo['questions'] = $row['Value'];
            } elseif ($row['Variable_name'] === 'Uptime') {
                $dbInfo['uptime'] = formatUptime($row['Value']);
            }
        }
        
        // 获取数据库大小
        $sizeResult = $db->fetchOne(
            "SELECT ROUND(SUM(data_length + index_length) / 1024 / 1024, 2) as size_mb 
             FROM information_schema.tables 
             WHERE table_schema = :db_name",
            ['db_name' => getenv('DB_NAME') ?: 'picflow']
        );
        $dbInfo['database_size'] = ($sizeResult['size_mb'] ?? 0) . ' MB';
        
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

$info = getBasicSystemInfo();
$dbInfo = getDatabaseInfo();
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
            padding: 30px;
            box-shadow: 0 8px 32px rgba(0, 0, 0, 0.15);
            border: 1px solid rgba(255, 255, 255, 0.3);
            position: relative;
            z-index: 1;
        }
        h2 {
            color: white;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid rgba(255, 255, 255, 0.5);
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .info-item {
            display: flex;
            justify-content: space-between;
            padding: 12px 0;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2);
        }
        .info-item:last-child {
            border-bottom: none;
        }
        .label {
            font-weight: bold;
            color: rgba(255, 255, 255, 0.9);
        }
        .value {
            color: rgba(255, 255, 255, 0.85);
        }
        .status {
            text-align: center;
            color: white;
            font-size: 18px;
            margin-bottom: 20px;
            padding: 15px;
            background: rgba(40, 167, 69, 0.3);
            border-radius: 8px;
            border: 1px solid rgba(255, 255, 255, 0.3);
            text-shadow: 1px 1px 2px rgba(0, 0, 0, 0.3);
        }
        #current-time {
            font-family: Arial, sans-serif;
        }
    </style>
</head>
<body>
    <div class="header">
        <a href="/upload.php" style="text-decoration: none;">
            <h1 style="cursor: pointer;">
                <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
                <span>- 后台管理</span>
            </h1>
        </a>
        <div style="display: flex; gap: 10px;">
            <a href="/upload.php" style="padding: 10px 20px; background: #667eea; color: white; text-decoration: none; border-radius: 8px; font-weight: 600; transition: all 0.3s;" onmouseover="this.style.background='#5568d3'; this.style.transform='translateY(-2px)'" onmouseout="this.style.background='#667eea'; this.style.transform='translateY(0)'">🏠 返回主页</a>
            <a href="?logout=1" class="logout-btn">🚪 退出登录</a>
        </div>
    </div>

    <div class="tabs">
        <a href="/panel.php?tab=system" class="tab-btn active" onclick="switchTab('system', event)">📊 系统监控</a>
        <a href="/panel.php?tab=database" class="tab-btn" onclick="switchTab('database', event)">🗄️ 数据库监控</a>
        <a href="/file.php" class="tab-btn">🖼️ 图片管理</a>
        <a href="/gallery.php" class="tab-btn">🎨 图片画廊</a>
        <a href="/external-manager.php" class="tab-btn">🔗 外链管理</a>
        <a href="/api-panel.php" class="tab-btn">🔧 API管理</a>
    </div>

    <!-- 系统监控 Tab -->
    <div class="container" id="system-tab">
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
    <div class="container" id="database-tab" style="display: none;">
        <h2>🗄️ 数据库监控</h2>
        <div class="status"><?php echo $dbInfo['status']; ?></div>
        
        <?php if ($dbInfo['error']): ?>
        <div class="error-message" style="background: rgba(220, 53, 69, 0.3); border: 1px solid rgba(220, 53, 69, 0.5); padding: 15px; border-radius: 8px; margin-bottom: 20px; color: #ffb3b3;">
            <strong>⚠️ 连接错误:</strong> <?php echo htmlspecialchars($dbInfo['error']); ?>
        </div>
        <?php endif; ?>
        
        <div class="info-item">
            <span class="label">主机地址</span>
            <span class="value"><?php echo $dbInfo['host']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">端口号</span>
            <span class="value"><?php echo $dbInfo['port']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">数据库名</span>
            <span class="value"><?php echo $dbInfo['database']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">用户名</span>
            <span class="value"><?php echo $dbInfo['user']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">MySQL 版本</span>
            <span class="value"><?php echo $dbInfo['version']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">字符集</span>
            <span class="value"><?php echo $dbInfo['charset']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">数据目录</span>
            <span class="value" style="font-size: 12px; word-break: break-all;"><?php echo $dbInfo['datadir']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">数据库大小</span>
            <span class="value"><?php echo $dbInfo['database_size']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">最大连接数</span>
            <span class="value"><?php echo $dbInfo['max_connections']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">当前连接</span>
            <span class="value"><?php echo $dbInfo['threads_connected']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">查询总数</span>
            <span class="value"><?php echo $dbInfo['questions']; ?></span>
        </div>
        
        <div class="info-item">
            <span class="label">运行时长</span>
            <span class="value"><?php echo $dbInfo['uptime']; ?></span>
        </div>
    </div>

    <script>
        // 初始化活跃Tab
        const urlParams = new URLSearchParams(window.location.search);
        const currentTab = urlParams.get('tab') || 'system';
        
        // 页面加载时设置正确的Tab
        window.addEventListener('load', function() {
            switchTab(currentTab, null);
        });
        
        // Tab切换函数
        function switchTab(tabName, event) {
            if (event) {
                event.preventDefault();
            }
            
            // 隐藏所有Tab内容
            document.getElementById('system-tab').style.display = 'none';
            document.getElementById('database-tab').style.display = 'none';
            
            // 移除所有Tab的active类
            const tabBtns = document.querySelectorAll('.tab-btn');
            tabBtns.forEach(btn => btn.classList.remove('active'));
            
            // 显示选中的Tab内容
            if (tabName === 'system') {
                document.getElementById('system-tab').style.display = 'block';
            } else if (tabName === 'database') {
                document.getElementById('database-tab').style.display = 'block';
            }
            
            // 添加active类到对应的Tab按钮
            const activeBtn = document.querySelector(`a[href*="tab=${tabName}"]`);
            if (activeBtn) {
                activeBtn.classList.add('active');
            }
            
            // 更新URL
            window.history.replaceState({}, '', '?tab=' + tabName);
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
