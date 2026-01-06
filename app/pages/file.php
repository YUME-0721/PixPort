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

// 获取本地图片统计
function getLocalImageStats() {
    $stats = [
        'pc' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0],
        'pe' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0]
    ];
    
    // 优先从数据库统计
    try {
        $db = Database::getInstance();
        
        // 查询数据库中的本地图片数量
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
            } else {
                // 动态添加新格式
                $stats[$deviceType][$format] = $count;
            }
        }
        
        return $stats;
    } catch (Exception $e) {
        // 数据库查询失败，回退到文件系统统计
        error_log("从数据库统计图片失败，回退到文件系统: " . $e->getMessage());
    }
    
    // 文件系统统计（回退方案）
    $imageDir = dirname(__DIR__, 1) . '/images';
    if (!is_dir($imageDir)) {
        return $stats;
    }
    
    foreach (['pc', 'pe'] as $type) {
        $typeDir = $imageDir . '/' . $type;
        if (!is_dir($typeDir)) continue;
        
        // 扫描扁平化目录
        $allFiles = glob($typeDir . '/*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE);
        foreach ($allFiles as $file) {
            $ext = strtolower(pathinfo($file, PATHINFO_EXTENSION));
            if ($ext === 'jpg') $ext = 'jpeg';
            if (!isset($stats[$type][$ext])) {
                $stats[$type][$ext] = 0;
            }
            $stats[$type][$ext]++;
        }

        // 扫描旧的格式子目录结构
        foreach (['jpeg', 'webp', 'avif', 'png', 'gif'] as $format) {
            $formatDir = $typeDir . '/' . $format;
            if (is_dir($formatDir)) {
                $files = glob($formatDir . '/*.{jpg,jpeg,png,gif,webp,avif}', GLOB_BRACE);
                if (!isset($stats[$type][$format])) {
                    $stats[$type][$format] = 0;
                }
                $stats[$type][$format] += count($files);
            }
        }
    }
    
    return $stats;
}

// 获取外链图片统计
function getExternalImageStats() {
    $stats = [
        'pc' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0],
        'pe' => ['jpeg' => 0, 'webp' => 0, 'avif' => 0, 'png' => 0, 'gif' => 0]
    ];
    
    // 优先从数据库统计
    try {
        $db = Database::getInstance();
        
        // 查询数据库中的外链图片数量
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
        
        return $stats;
    } catch (Exception $e) {
        // 数据库查询失败，回退到文件系统统计
        error_log("从数据库统计外链图片失败，回退到文件系统: " . $e->getMessage());
    }
    
    // 文件系统统计（回退方案）
    $externalDir = __DIR__ . '/external-images';
    if (!is_dir($externalDir)) {
        return $stats;
    }
    
    foreach (['pc', 'pe'] as $type) {
        $typeDir = $externalDir . '/' . $type;
        if (!is_dir($typeDir)) continue;
        
        $formats = ['jpeg', 'webp', 'avif', 'png', 'gif'];
        foreach ($formats as $format) {
            $formatDir = $typeDir . '/' . $format;
            if (is_dir($formatDir)) {
                $txtFiles = glob($formatDir . '/*.txt');
                foreach ($txtFiles as $txtFile) {
                    if (!is_file($txtFile)) continue;
                    $urls = file($txtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
                    foreach ($urls as $url) {
                        $url = trim($url);
                        if (empty($url) || strpos($url, '#') === 0) continue;
                        $stats[$type][$format]++;
                    }
                }
            }
        }
        
        $txtFiles = glob($typeDir . '/*.txt');
        foreach ($txtFiles as $txtFile) {
            if (!is_file($txtFile)) continue;
            $urls = file($txtFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
            foreach ($urls as $url) {
                $url = trim($url);
                if (empty($url) || strpos($url, '#') === 0) continue;
                
                $ext = strtolower(pathinfo(parse_url($url, PHP_URL_PATH), PATHINFO_EXTENSION));
                if ($ext === 'jpg') $ext = 'jpeg';
                if (isset($stats[$type][$ext])) {
                    $stats[$type][$ext]++;
                }
            }
        }
    }
    
    return $stats;
}

// 只在刷新请求或首次启动时统计图片
if (isset($_GET['refresh']) && $_GET['refresh'] === 'stats') {
    $localStats = getLocalImageStats();
    $externalStats = getExternalImageStats();
    
    $_SESSION['localStats'] = $localStats;
    $_SESSION['externalStats'] = $externalStats;
    $_SESSION['stats_updated_at'] = time();
    
    // 返回 JSON 响应，用于前端获取最新数据
    header('Content-Type: application/json');
    echo json_encode([
        'success' => true,
        'localStats' => $localStats,
        'externalStats' => $externalStats,
        'timestamp' => $_SESSION['stats_updated_at']
    ]);
    exit;
} else {
    // 强制刷新：如果距离上次更新超过5秒，或者没有缓存数据
    $needRefresh = !isset($_SESSION['stats_updated_at']) || 
                   (time() - $_SESSION['stats_updated_at']) > 5 ||
                   !isset($_SESSION['localStats']) || 
                   !isset($_SESSION['externalStats']);
    
    if ($needRefresh) {
        $localStats = getLocalImageStats();
        $externalStats = getExternalImageStats();
        $_SESSION['localStats'] = $localStats;
        $_SESSION['externalStats'] = $externalStats;
        $_SESSION['stats_updated_at'] = time();
    } else {
        $localStats = $_SESSION['localStats'];
        $externalStats = $_SESSION['externalStats'];
    }
}
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>图片统计 - PixPort</title>
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
            from {
                transform: translateX(400px);
                opacity: 0;
            }
            to {
                transform: translateX(0);
                opacity: 1;
            }
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
            gap: 20px;
            margin: 20px 0;
        }
        .stat-card {
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            padding: 20px;
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            text-align: center;
        }
        .stat-card h3 {
            color: white;
            font-size: 16px;
            margin-bottom: 10px;
            text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);
        }
        .stat-card .number {
            font-size: 36px;
            font-weight: 700;
            color: white;
            margin: 10px 0;
            text-shadow: 2px 2px 4px rgba(0, 0, 0, 0.3);
        }
        .stat-card .desc {
            color: rgba(255, 255, 255, 0.85);
            font-size: 14px;
        }
        .chart-container {
            max-width: 600px;
            margin: 30px auto;
            padding: 20px;
            background: rgba(255, 255, 255, 0.1);
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border-radius: 10px;
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        canvas {
            max-width: 100%;
            height: auto !important;
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
        <a href="/panel.php?tab=system" class="tab-btn">📊 系统监控</a>
        <a href="/panel.php?tab=database" class="tab-btn">🗄️ 数据库监控</a>
        <a href="/file.php" class="tab-btn active">🖼️ 图片管理</a>
        <a href="/gallery.php" class="tab-btn">🎨 图片画廊</a>
        <a href="/external-manager.php" class="tab-btn">🔗 外链管理</a>
        <a href="/api-panel.php" class="tab-btn">🔧 API管理</a>
    </div>

    <div class="container">
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
        </div>

        <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 30px; margin-top: 30px;">
            <div class="chart-container">
                <h3 style="text-align: center; color: white; margin-bottom: 20px; text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);">图片来源分布</h3>
                <canvas id="sourceChart"></canvas>
            </div>
            <div class="chart-container">
                <h3 style="text-align: center; color: white; margin-bottom: 20px; text-shadow: 1px 1px 3px rgba(0, 0, 0, 0.3);">图片格式分布</h3>
                <canvas id="formatChart"></canvas>
            </div>
        </div>
    </div>

    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js"></script>
    <script>
        // 显示通知
        function showNotification(message) {
            const notification = document.createElement('div');
            notification.className = 'notification';
            notification.textContent = message;
            document.body.appendChild(notification);
            
            setTimeout(() => {
                notification.remove();
            }, 3000);
        }
        
        // 刷新统计数据
        function refreshStats() {
            fetch('?refresh=stats')
                .then(response => response.json())
                .then(data => {
                    if (data.success) {
                        // 更新存储較旧的统计数据
                        const localStats = data.localStats;
                        const externalStats = data.externalStats;
                                
                        // 重新计算总数
                        const totalLocal = Object.values(localStats.pc).reduce((a, b) => a + b, 0) + 
                                          Object.values(localStats.pe).reduce((a, b) => a + b, 0);
                        const totalExternal = Object.values(externalStats.pc).reduce((a, b) => a + b, 0) + 
                                             Object.values(externalStats.pe).reduce((a, b) => a + b, 0);
                        const totalPC = Object.values(localStats.pc).reduce((a, b) => a + b, 0) + 
                                       Object.values(externalStats.pc).reduce((a, b) => a + b, 0);
                        const totalPE = Object.values(localStats.pe).reduce((a, b) => a + b, 0) + 
                                       Object.values(externalStats.pe).reduce((a, b) => a + b, 0);
                                
                        // 更新页面显示数字
                        document.getElementById('total-local').textContent = totalLocal;
                        document.getElementById('total-external').textContent = totalExternal;
                        document.getElementById('total-pc').textContent = totalPC;
                        document.getElementById('total-pe').textContent = totalPE;
                                
                        // 重新渲染图表
                        const stats = {
                            local: localStats,
                            external: externalStats
                        };
                        createSourceChart(stats);
                        createFormatChart(stats);
                                
                        showNotification('✅ 统计数据已更新');
                    }
                })
                .catch(error => {
                    console.error('更新失败:', error);
                    showNotification('❌ 更新失败，请重新加载');
                });
        }
        
        // 加载图片统计数据
        function loadImageStats() {
            const localStats = <?php echo json_encode($localStats); ?>;
            const externalStats = <?php echo json_encode($externalStats); ?>;
            
            const stats = {
                local: localStats,
                external: externalStats
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
        
        // 创建来源分布图
        function createSourceChart(stats) {
            const ctx = document.getElementById('sourceChart');
            
            if (window.sourceChartInstance) {
                window.sourceChartInstance.destroy();
            }
            
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
                        backgroundColor: [
                            'rgba(102, 126, 234, 0.8)',
                            'rgba(118, 75, 162, 0.8)',
                            'rgba(255, 159, 64, 0.8)',
                            'rgba(255, 205, 86, 0.8)'
                        ],
                        borderColor: [
                            'rgba(102, 126, 234, 1)',
                            'rgba(118, 75, 162, 1)',
                            'rgba(255, 159, 64, 1)',
                            'rgba(255, 205, 86, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 14 },
                                color: 'white'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = total > 0 ? ((value / total) * 100).toFixed(1) : '0.0';
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 创建格式分布图
        function createFormatChart(stats) {
            const ctx = document.getElementById('formatChart');
            
            if (window.formatChartInstance) {
                window.formatChartInstance.destroy();
            }
            
            const totalJPEG = stats.local.pc.jpeg + stats.local.pe.jpeg;
            const totalWebP = stats.local.pc.webp + stats.local.pe.webp;
            const totalAVIF = stats.local.pc.avif + stats.local.pe.avif;
            const totalPNG = (stats.local.pc.png || 0) + (stats.local.pe.png || 0);
            const totalGIF = (stats.local.pc.gif || 0) + (stats.local.pe.gif || 0);
            
            const externalJPEG = stats.external.pc.jpeg + stats.external.pe.jpeg;
            const externalWebP = stats.external.pc.webp + stats.external.pe.webp;
            const externalAVIF = stats.external.pc.avif + stats.external.pe.avif;
            const externalPNG = (stats.external.pc.png || 0) + (stats.external.pe.png || 0);
            const externalGIF = (stats.external.pc.gif || 0) + (stats.external.pe.gif || 0);
            
            window.formatChartInstance = new Chart(ctx, {
                type: 'doughnut',
                data: {
                    labels: ['JPEG', 'WebP', 'AVIF', 'PNG', 'GIF'],
                    datasets: [{
                        data: [
                            totalJPEG + externalJPEG, 
                            totalWebP + externalWebP, 
                            totalAVIF + externalAVIF,
                            totalPNG + externalPNG,
                            totalGIF + externalGIF
                        ],
                        backgroundColor: [
                            'rgba(255, 99, 132, 0.8)',
                            'rgba(54, 162, 235, 0.8)',
                            'rgba(75, 192, 192, 0.8)',
                            'rgba(153, 102, 255, 0.8)',
                            'rgba(255, 205, 86, 0.8)'
                        ],
                        borderColor: [
                            'rgba(255, 99, 132, 1)',
                            'rgba(54, 162, 235, 1)',
                            'rgba(75, 192, 192, 1)',
                            'rgba(153, 102, 255, 1)',
                            'rgba(255, 205, 86, 1)'
                        ],
                        borderWidth: 2
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: true,
                    plugins: {
                        legend: {
                            position: 'bottom',
                            labels: {
                                padding: 20,
                                font: { size: 14 },
                                color: 'white'
                            }
                        },
                        tooltip: {
                            callbacks: {
                                label: function(context) {
                                    const label = context.label || '';
                                    const value = context.parsed || 0;
                                    const total = context.dataset.data.reduce((a, b) => a + b, 0);
                                    const percentage = ((value / total) * 100).toFixed(1);
                                    return `${label}: ${value} (${percentage}%)`;
                                }
                            }
                        }
                    }
                }
            });
        }
        
        // 页面加载时初始化
        loadImageStats();
    </script>
</body>
</html>
