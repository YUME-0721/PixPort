<?php
// 系统设置和账号管理
session_start();
require_once dirname(__DIR__, 2) . '/includes/Database.php';

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    header('Location: /index.php');
    exit;
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: /index.php');
    exit;
}

// 获取系统配置文件路径
$systemConfigFile = dirname(__DIR__, 2) . '/config/system-config.json';
if (!is_dir(dirname($systemConfigFile))) {
    mkdir(dirname($systemConfigFile), 0755, true);
}

// 默认系统配置
$defaultConfig = [
    'admin_user' => getenv('ADMIN_USER') ?: 'admin',
    'admin_password' => getenv('ADMIN_PASSWORD') ?: 'admin123',
    'background_url' => '/public/assets/images/home-backend.jpg',
    'login_background_url' => '/public/assets/images/backend-picture.jpg'
];

// 读取配置
$config = $defaultConfig;
if (file_exists($systemConfigFile)) {
    $loadedConfig = json_decode(file_get_contents($systemConfigFile), true);
    if (is_array($loadedConfig)) {
        $config = array_merge($config, $loadedConfig);
    }
}

// 处理配置更新
$message = '';
$error = '';
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    $action = $_POST['action'];
    
    if ($action === 'update_account') {
        $newUser = trim($_POST['admin_user'] ?? '');
        $newPass = $_POST['admin_password'] ?? '';
        $confirmPass = $_POST['confirm_password'] ?? '';
        
        if (empty($newUser)) {
            $error = '❌ 用户名不能为空';
        } elseif (!empty($newPass) && $newPass !== $confirmPass) {
            $error = '❌ 两次输入的密码不一致';
        } else {
            $config['admin_user'] = $newUser;
            if (!empty($newPass)) {
                $config['admin_password'] = $newPass;
            }
            
            if (file_put_contents($systemConfigFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
                $message = '✅ 账号信息已更新';
            } else {
                $error = '❌ 配置文件写入失败，请检查权限';
            }
        }
    } elseif ($action === 'update_ui') {
        $config['background_url'] = trim($_POST['background_url'] ?? $defaultConfig['background_url']);
        $config['login_background_url'] = trim($_POST['login_background_url'] ?? $defaultConfig['login_background_url']);
        
        if (file_put_contents($systemConfigFile, json_encode($config, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE))) {
            $message = '✅ 界面设置已保存';
        } else {
            $error = '❌ 配置文件写入失败，请检查权限';
        }
    } elseif ($action === 'export_system') {
        try {
            $backupDir = dirname(__DIR__, 2) . '/backups';
            if (!is_dir($backupDir)) mkdir($backupDir, 0755, true);
            
            $zipFile = $backupDir . '/pixport_backup_' . date('YmdHis') . '.zip';
            $zip = new ZipArchive();
            
            if ($zip->open($zipFile, ZipArchive::CREATE | ZipArchive::OVERWRITE) !== TRUE) {
                throw new Exception("无法创建压缩文件");
            }
            
            // 1. 备份数据库
            $db = Database::getInstance();
            $tables = ['album_images', 'albums', 'images']; // 按照外键依赖的反序导出，或者导出后在还原时禁用检查
            $sqlDump = "-- PixPort Backup\n-- Generated: " . date('Y-m-d H:i:s') . "\n";
            $sqlDump .= "SET FOREIGN_KEY_CHECKS = 0;\n\n";
            
            foreach ($tables as $table) {
                $rows = $db->fetchAll("SELECT * FROM $table");
                if (empty($rows)) {
                    $sqlDump .= "TRUNCATE TABLE `$table`;\n";
                    continue;
                }
                
                $sqlDump .= "TRUNCATE TABLE `$table`;\n";
                foreach ($rows as $row) {
                    $keys = array_keys($row);
                    $values = array_values($row);
                    $valStr = implode(", ", array_map(function($v) use ($db) {
                        return $v === null ? "NULL" : "'" . addslashes($v) . "'";
                    }, $values));
                    $sqlDump .= "INSERT INTO `$table` (`" . implode("`, `", $keys) . "`) VALUES ($valStr);\n";
                }
                $sqlDump .= "\n";
            }
            $sqlDump .= "SET FOREIGN_KEY_CHECKS = 1;\n";
            $zip->addFromString('database_backup.sql', $sqlDump);
            
            // 2. 备份资源文件 (images 目录)
            $imageDir = dirname(__DIR__, 2) . '/images';
            if (is_dir($imageDir)) {
                $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($imageDir), RecursiveIteratorIterator::LEAVES_ONLY);
                foreach ($files as $file) {
                    if (!$file->isDir()) {
                        $filePath = $file->getRealPath();
                        $relativePath = 'images/' . substr($filePath, strlen($imageDir) + 1);
                        $zip->addFile($filePath, $relativePath);
                    }
                }
            }
            
            // 3. 备份配置文件 (config 目录)
            $configDir = dirname(__DIR__, 2) . '/config';
            if (is_dir($configDir)) {
                $files = scandir($configDir);
                foreach ($files as $file) {
                    if ($file !== '.' && $file !== '..' && is_file($configDir . '/' . $file)) {
                        $zip->addFile($configDir . '/' . $file, 'config/' . $file);
                    }
                }
            }
            
            $zip->close();
            
            // 下载文件
            header('Content-Type: application/zip');
            header('Content-Disposition: attachment; filename="' . basename($zipFile) . '"');
            header('Content-Length: ' . filesize($zipFile));
            readfile($zipFile);
            unlink($zipFile); // 下载后删除临时文件
            exit;
            
        } catch (Exception $e) {
            $error = '❌ 导出失败: ' . $e->getMessage();
        }
    } elseif ($action === 'restore_system') {
        if (!isset($_FILES['backup_file']) || $_FILES['backup_file']['error'] !== UPLOAD_ERR_OK) {
            $error = '❌ 请选择有效的备份文件';
        } else {
            try {
                $zipFile = $_FILES['backup_file']['tmp_name'];
                $zip = new ZipArchive();
                
                if ($zip->open($zipFile) !== TRUE) {
                    throw new Exception("无法打开压缩文件");
                }
                
                $tempRestoreDir = dirname(__DIR__, 2) . '/backups/temp_restore_' . time();
                mkdir($tempRestoreDir, 0755, true);
                $zip->extractTo($tempRestoreDir);
                $zip->close();
                
                $db = Database::getInstance();
                
                // 1. 恢复数据库
                $sqlFile = $tempRestoreDir . '/database_backup.sql';
                if (file_exists($sqlFile)) {
                    $sql = file_get_contents($sqlFile);
                    // 统一换行符并分割
                    $sql = str_replace("\r\n", "\n", $sql);
                    $queries = array_filter(array_map('trim', explode(";\n", $sql)));
                    
                    $db->query("SET FOREIGN_KEY_CHECKS = 0");
                    foreach ($queries as $query) {
                        if (!empty($query)) {
                            $db->query($query);
                        }
                    }
                    $db->query("SET FOREIGN_KEY_CHECKS = 1");
                }
                
                // 2. 恢复资源文件
                $restoredImagesDir = $tempRestoreDir . '/images';
                if (is_dir($restoredImagesDir)) {
                    $targetImagesDir = dirname(__DIR__, 2) . '/images';
                    // 递归复制
                    $files = new RecursiveIteratorIterator(new RecursiveDirectoryIterator($restoredImagesDir), RecursiveIteratorIterator::LEAVES_ONLY);
                    foreach ($files as $file) {
                        if (!$file->isDir()) {
                            $filePath = $file->getRealPath();
                            $targetPath = $targetImagesDir . '/' . substr($filePath, strlen($restoredImagesDir) + 1);
                            if (!is_dir(dirname($targetPath))) mkdir(dirname($targetPath), 0755, true);
                            copy($filePath, $targetPath);
                        }
                    }
                }
                
                // 3. 恢复配置文件
                $restoredConfigDir = $tempRestoreDir . '/config';
                if (is_dir($restoredConfigDir)) {
                    $targetConfigDir = dirname(__DIR__, 2) . '/config';
                    $files = scandir($restoredConfigDir);
                    foreach ($files as $file) {
                        if ($file !== '.' && $file !== '..' && is_file($restoredConfigDir . '/' . $file)) {
                            copy($restoredConfigDir . '/' . $file, $targetConfigDir . '/' . $file);
                        }
                    }
                }
                
                // 清理临时目录
                $it = new RecursiveIteratorIterator(
                    new RecursiveDirectoryIterator($tempRestoreDir, RecursiveDirectoryIterator::SKIP_DOTS),
                    RecursiveIteratorIterator::CHILD_FIRST
                );
                foreach($it as $file) {
                    if ($file->isDir()) {
                        rmdir($file->getRealPath());
                    } else {
                        unlink($file->getRealPath());
                    }
                }
                rmdir($tempRestoreDir);
                
                $message = '✅ 系统已成功恢复，部分配置可能需要刷新页面生效';
                
            } catch (Exception $e) {
                $error = '❌ 还原失败: ' . $e->getMessage();
            }
        }
    }
}

$currentBg = $config['background_url'];
?>
<!DOCTYPE html>
<html lang="zh-CN">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
    <title>系统设置 - PixPort</title>
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
        section {
            margin-bottom: 40px;
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
        .form-group {
            margin-bottom: 20px;
        }
        label {
            color: white;
            display: block;
            margin-bottom: 8px;
            font-weight: 600;
            font-size: 14px;
        }
        input[type="text"],
        input[type="password"] {
            width: 100%;
            padding: 12px;
            border: 1px solid rgba(255, 255, 255, 0.2);
            border-radius: 8px;
            background: rgba(0, 0, 0, 0.3);
            color: white !important;
            font-size: 14px;
            transition: all 0.3s;
        }
        input::placeholder {
            color: rgba(255, 255, 255, 0.5);
        }
        /* 玻璃按钮样式 */
        .glass-btn {
            background: rgba(255, 255, 255, 0.1) !important;
            backdrop-filter: blur(10px);
            -webkit-backdrop-filter: blur(10px);
            border: 1px solid rgba(255, 255, 255, 0.2) !important;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2) !important;
            background-image: none !important;
        }
        .glass-btn:hover {
            background: rgba(255, 255, 255, 0.2) !important;
            border-color: rgba(255, 255, 255, 0.4) !important;
            transform: translateY(-2px);
        }
        input:focus {
            outline: none;
            border-color: #667eea;
            background: rgba(0, 0, 0, 0.4);
        }
        button {
            padding: 12px 30px;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            border-radius: 8px;
            font-weight: 600;
            cursor: pointer;
            font-size: 15px;
            transition: all 0.3s;
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.5);
        }
        .alert {
            padding: 15px;
            border-radius: 8px;
            margin-bottom: 25px;
            font-weight: 600;
        }
        .alert-success {
            background: rgba(40, 167, 69, 0.2);
            border: 1px solid rgba(40, 167, 69, 0.5);
            color: #28a745;
        }
        .alert-danger {
            background: rgba(220, 53, 69, 0.2);
            border: 1px solid rgba(220, 53, 69, 0.5);
            color: #dc3545;
        }
        .hint {
            color: rgba(255, 255, 255, 0.6);
            font-size: 12px;
            margin-top: 5px;
        }
        .grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
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
    </style>
</head>
<body>
    <div class="header">
        <a href="/upload.php" style="text-decoration: none;">
            <h1 style="cursor: pointer;">
                <img src="/public/assets/images/logo-white.png" alt="PixPort" class="logo-img">
                <span>- 系统设置</span>
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
        <a href="/panel.php" class="nav-item">
            <span class="btn-icon">📊</span>
            <span class="btn-text">监控面板</span>
        </a>
        <a href="/api-panel.php" class="nav-item">
            <span class="btn-icon">🔧</span>
            <span class="btn-text">API管理</span>
        </a>
        <div class="nav-item active">
            <span class="btn-icon">⚙️</span>
            <span class="btn-text">系统设置</span>
        </div>
        <div class="sub-nav">
            <div class="sub-nav-item active" id="sub-nav-account" onclick="switchSubTab('account')">账号管理</div>
            <div class="sub-nav-item" id="sub-nav-ui" onclick="switchSubTab('ui')">界面配置</div>
            <div class="sub-nav-item" id="sub-nav-backup" onclick="switchSubTab('backup')">备份还原</div>
        </div>
        <div class="toggle-btn" onclick="toggleSidebar()">
            <span id="toggleIcon">⬅️</span>
        </div>
    </div>

    <div class="container">
        <?php if ($message): ?>
            <div class="alert alert-success"><?php echo $message; ?></div>
        <?php endif; ?>
        <?php if ($error): ?>
            <div class="alert alert-danger"><?php echo $error; ?></div>
        <?php endif; ?>

        <!-- 账号设置 -->
        <div id="account-tab" class="sub-tab-content active">
            <section>
                <h2>👤 账号安全设置</h2>
                <form method="POST" style="background: rgba(255, 255, 255, 0.05); padding: 30px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 600px; margin: 0 auto;">
                    <input type="hidden" name="action" value="update_account">
                    
                    <div class="form-group">
                        <label>管理员用户名</label>
                        <input type="text" name="admin_user" value="<?php echo htmlspecialchars($config['admin_user']); ?>" required>
                        <p class="hint">用于登录后台管理系统的用户名</p>
                    </div>

                    <div class="form-group">
                        <label>新密码 (留空则不修改)</label>
                        <input type="password" name="admin_password" placeholder="请输入新密码">
                    </div>

                    <div class="form-group">
                        <label>确认新密码</label>
                        <input type="password" name="confirm_password" placeholder="请再次输入新密码">
                    </div>

                    <div style="text-align: right; margin-top: 10px;">
                        <button type="submit" class="glass-btn">💾 保存账号信息</button>
                    </div>
                </form>
            </section>
        </div>

        <!-- 界面设置 -->
        <div id="ui-tab" class="sub-tab-content">
            <section>
                <h2>🎨 个性化界面设置</h2>
                <form method="POST" style="background: rgba(255, 255, 255, 0.05); padding: 30px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); max-width: 600px; margin: 0 auto;">
                    <input type="hidden" name="action" value="update_ui">
                    
                    <div class="form-group">
                        <label>后台管理页背景图 URL</label>
                        <input type="text" name="background_url" value="<?php echo htmlspecialchars($config['background_url']); ?>" placeholder="输入背景图片链接">
                        <p class="hint">应用于所有后台管理页面的背景（包括本页面）</p>
                    </div>

                    <div class="form-group">
                        <label>登录页背景图 URL</label>
                        <input type="text" name="login_background_url" value="<?php echo htmlspecialchars($config['login_background_url']); ?>" placeholder="输入登录页背景链接">
                        <p class="hint">应用于登录页 (index.php) 的背景图片</p>
                    </div>

                    <div style="text-align: right; margin-top: 10px;">
                        <button type="submit" class="glass-btn">✨ 应用界面设置</button>
                    </div>
                </form>
            </section>
        </div>

        <!-- 备份与恢复 -->
        <div id="backup-tab" class="sub-tab-content">
            <section>
                <h2>💾 系统备份与恢复</h2>
                <div class="grid">
                    <!-- 导出 -->
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 30px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1); text-align: center;">
                        <div style="font-size: 40px; margin-bottom: 15px;">📤</div>
                        <h3 style="color: white; margin-bottom: 10px;">一键导出备份</h3>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 14px; margin-bottom: 20px;">
                            将打包数据库记录、图片资源及系统配置文件，生成一个 ZIP 压缩包供您下载保存。
                        </p>
                        <form method="POST">
                            <input type="hidden" name="action" value="export_system">
                            <button type="submit" class="glass-btn" style="width: 100%;">📦 立即生成备份</button>
                        </form>
                    </div>

                    <!-- 还原 -->
                    <div style="background: rgba(255, 255, 255, 0.05); padding: 30px; border-radius: 12px; border: 1px solid rgba(255, 255, 255, 0.1);">
                        <div style="font-size: 40px; margin-bottom: 15px; text-align: center;">📥</div>
                        <h3 style="color: white; margin-bottom: 10px; text-align: center;">一键还原系统</h3>
                        <p style="color: rgba(255, 255, 255, 0.7); font-size: 14px; margin-bottom: 20px; text-align: center;">
                            上传之前导出的备份压缩包（ZIP格式），系统将自动恢复所有数据和资源。
                        </p>
                        <form method="POST" enctype="multipart/form-data">
                            <input type="hidden" name="action" value="restore_system">
                            <div class="form-group" style="display: flex; flex-direction: column; align-items: center; text-align: center;">
                                <label>选择备份文件</label>
                                <input type="file" name="backup_file" accept=".zip" required style="display: block; margin: 0 auto; width: fit-content; max-width: 100%; color: white; background: transparent; border: none; padding: 10px 0; text-align: center;">
                            </div>
                            <button type="submit" class="glass-btn" style="width: 100%;">🔄 开始还原数据</button>
                        </form>
                        <p class="hint" style="text-align: center; margin-top: 10px; color: #ffc107;">⚠️ 还原操作会覆盖当前所有数据，请谨慎操作！</p>
                    </div>
                </div>
            </section>
        </div>
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
            document.getElementById(tabId + '-tab').classList.add('active');
            
            localStorage.setItem('activeSystemTab', tabId);
        }

        // 页面加载时恢复状态
        window.onload = function() {
            // 恢复侧边栏状态
            const isCollapsed = localStorage.getItem('sidebarCollapsed') === 'true';
            if (isCollapsed) {
                document.getElementById('sidebar').classList.add('collapsed');
                document.getElementById('toggleIcon').innerText = '➡️';
            } else {
                document.getElementById('sidebar').classList.remove('collapsed');
                document.getElementById('toggleIcon').innerText = '⬅️';
            }

            // 恢复标签页
            const lastTab = localStorage.getItem('activeSystemTab');
            if (lastTab) {
                switchSubTab(lastTab);
            }
        }
    </script>
</body>
</html>
