<?php
// 登录配置
$adminUser = getenv('ADMIN_USER') ?: 'admin';         // 默认用户名
$adminPassword = getenv('ADMIN_PASSWORD') ?: 'admin123'; // 默认密码
$loginBg = '/public/assets/images/backend-picture.jpg'; // 默认背景

// 加载系统配置覆盖默认值
$systemConfigFile = dirname(__DIR__, 2) . '/config/system-config.json';
if (file_exists($systemConfigFile)) {
    $config = json_decode(file_get_contents($systemConfigFile), true);
    if (is_array($config)) {
        if (!empty($config['admin_user'])) $adminUser = $config['admin_user'];
        if (!empty($config['admin_password'])) $adminPassword = $config['admin_password'];
        if (!empty($config['login_background_url'])) $loginBg = $config['login_background_url'];
    }
}

// 检查登录
session_start();

if (isset($_POST['username']) && isset($_POST['password'])) {
    if ($_POST['username'] === $adminUser && $_POST['password'] === $adminPassword) {
        $_SESSION['authenticated'] = true;
        header('Location: ' . $_SERVER['PHP_SELF']);
        exit;
    } else {
        $error = '密码错误！';
    }
}

if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: ' . $_SERVER['PHP_SELF']);
    exit;
}

if (isset($_SESSION['authenticated']) && $_SESSION['authenticated'] === true) {
    header('Location: /upload.php');
    exit;
}

if (!isset($_SESSION['authenticated']) || $_SESSION['authenticated'] !== true) {
    // 显示登录表单
    ?>
    <!DOCTYPE html>
    <html lang="zh-CN">
    <head>
        <meta charset="UTF-8">
        <meta name="viewport" content="width=device-width, initial-scale=1.0">
        <link rel="icon" type="image/svg+xml" href="/public/assets/svg/favicon.svg">
        <title>PixPort图床</title>
        <style>
            * {
                margin: 0;
                padding: 0;
                box-sizing: border-box;
            }
            body {
                font-family: Arial, sans-serif;
                background: url('<?php echo $loginBg; ?>') no-repeat center center fixed;
                background-size: cover;
                min-height: 100vh;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0;
                position: relative;
            }
            .site-header {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                padding: 0 30px;
                z-index: 100;
                backdrop-filter: blur(10px);
                background: rgba(255, 255, 255, 0.05);
                border-bottom: 1px solid rgba(255, 255, 255, 0.2);
                display: flex;
                align-items: center;
                justify-content: space-between;
                padding-right: 45px;
            }
            .site-title {
                display: flex;
                align-items: center;
            }
            .site-logo {
                height: 64px;
                width: auto;
                filter: drop-shadow(0 2px 10px rgba(0, 0, 0, 0.3));
            }
            .doc-link {
                display: flex;
                align-items: center;
                gap: 8px;
                color: white;
                text-decoration: none;
                font-size: 20px;
                font-weight: 500;
                transition: opacity 0.3s;
            }
            .doc-link:hover {
                opacity: 0.8;
            }
            .doc-link svg {
                width: 28px;
                height: 28px;
                filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.2));
            }
            body::before {
                content: '';
                position: absolute;
                top: 0;
                left: 0;
                right: 0;
                bottom: 0;
                background: rgba(0, 0, 0, 0.25);
                backdrop-filter: blur(1px);
            }
            .login-container {
                background: rgba(255, 255, 255, 0.95);
                padding: 35px 50px 50px 50px;
                border-radius: 16px;
                box-shadow: 0 20px 60px rgba(0,0,0,0.4);
                width: 100%;
                max-width: 420px;
                position: relative;
                z-index: 1;
                backdrop-filter: blur(10px);
                margin-top: 80px;
            }
            h1 {
                text-align: center;
                color: #333;
                margin-top: 0;
                margin-bottom: 15px;
                font-size: 42px;
                font-weight: 700;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                -webkit-background-clip: text;
                -webkit-text-fill-color: transparent;
                background-clip: text;
            }
            .subtitle {
                text-align: center;
                color: #555;
                font-size: 20px;
                margin-bottom: 12px;
                font-weight: 600;
                line-height: 1.5;
            }
            .slogan {
                text-align: center;
                color: #888;
                font-size: 15px;
                margin-bottom: 10px;
                letter-spacing: 3px;
                text-transform: uppercase;
            }
            .form-group {
                margin-bottom: 20px;
                position: relative;
            }
            .input-wrapper {
                position: relative;
                width: 100%;
            }
            .input-icon {
                position: absolute;
                right: 12px;
                top: 50%;
                transform: translateY(-50%);
                width: 22px;
                height: 22px;
                color: #999;
                pointer-events: none;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .input-icon svg {
                width: 100%;
                height: 100%;
            }
            input[type="text"],
            input[type="password"] {
                width: 100%;
                padding: 12px;
                padding-right: 42px;
                border: 2px solid #e0e0e0;
                border-radius: 8px;
                font-size: 16px;
                box-sizing: border-box;
                transition: all 0.3s ease;
            }
            input[type="text"]:focus,
            input[type="password"]:focus {
                outline: none;
                border-color: #667eea;
                box-shadow: 0 0 0 3px rgba(102, 126, 234, 0.1);
            }
            button {
                width: 100%;
                padding: 14px;
                background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
                color: white;
                border: none;
                border-radius: 8px;
                font-size: 16px;
                cursor: pointer;
                font-weight: 600;
                transition: all 0.3s ease;
                box-shadow: 0 4px 15px rgba(102, 126, 234, 0.4);
            }
            button:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(102, 126, 234, 0.6);
            }
            .error {
                background: rgba(220, 53, 69, 0.1);
                color: #dc3545;
                text-align: center;
                padding: 12px;
                border-radius: 8px;
                margin-bottom: 20px;
                font-size: 14px;
                font-weight: 600;
                border: 1px solid rgba(220, 53, 69, 0.2);
                display: flex;
                align-items: center;
                justify-content: center;
                gap: 8px;
                animation: shake 0.4s ease-in-out;
            }
            @keyframes shake {
                0%, 100% { transform: translateX(0); }
                25% { transform: translateX(-5px); }
                75% { transform: translateX(5px); }
            }
        </style>
    </head>
    <body>
        <div class="site-header">
            <div class="site-title">
                <img src="/public/assets/images/logo-white.png" alt="PixPort" class="site-logo">
            </div>
            <a href="https://github.com/YUME-0721" class="doc-link" target="_blank">
                <svg xmlns="http://www.w3.org/2000/svg" width="24" height="24" viewBox="0 0 24 24"><path fill="white" d="M12 2A10 10 0 0 0 2 12c0 4.42 2.87 8.17 6.84 9.5c.5.08.66-.23.66-.5v-1.69c-2.77.6-3.36-1.34-3.36-1.34c-.46-1.16-1.11-1.47-1.11-1.47c-.91-.62.07-.6.07-.6c1 .07 1.53 1.03 1.53 1.03c.87 1.52 2.34 1.07 2.91.83c.09-.65.35-1.09.63-1.34c-2.22-.25-4.55-1.11-4.55-4.92c0-1.11.38-2 1.03-2.71c-.1-.25-.45-1.29.1-2.64c0 0 .84-.27 2.75 1.02c.79-.22 1.65-.33 2.5-.33s1.71.11 2.5.33c1.91-1.29 2.75-1.02 2.75-1.02c.55 1.35.2 2.39.1 2.64c.65.71 1.03 1.6 1.03 2.71c0 3.82-2.34 4.66-4.57 4.91c.36.31.69.92.69 1.85V21c0 .27.16.59.67.5C19.14 20.16 22 16.42 22 12A10 10 0 0 0 12 2"/></svg>
                GitHub
            </a>
        </div>
        <div class="login-container">
            <h1>欢迎使用</h1>
            <div class="slogan">Intelligent · Lightweight · Efficient</div>
            <div class="subtitle">极简管理，即刻分享</div>
            <?php if (isset($error)): ?>
                <div class="error">
                    <svg xmlns="http://www.w3.org/2000/svg" width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5" stroke-linecap="round" stroke-linejoin="round"><circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/></svg>
                    <?php echo $error; ?>
                </div>
            <?php endif; ?>
            <form method="POST">
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="text" id="username" name="username" placeholder="请输入用户名" required autofocus>
                        <div class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 48 48"><g fill="none" stroke="currentColor" stroke-linecap="round" stroke-linejoin="round" stroke-width="4"><circle cx="24" cy="11" r="7"/><path d="M4 41c0-8.837 8.059-16 18-16m9 17l10-10l-4-4l-10 10v4z"/></g></svg>
                        </div>
                    </div>
                </div>
                <div class="form-group">
                    <div class="input-wrapper">
                        <input type="password" id="password" name="password" placeholder="请输入密码" required>
                        <div class="input-icon">
                            <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24"><path fill="currentColor" d="M12 17a2 2 0 0 1-2-2c0-1.11.89-2 2-2a2 2 0 0 1 2 2a2 2 0 0 1-2 2m6 3V10H6v10zm0-12a2 2 0 0 1 2 2v10a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V10c0-1.11.89-2 2-2h1V6a5 5 0 0 1 5-5a5 5 0 0 1 5 5v2zm-6-5a3 3 0 0 0-3 3v2h6V6a3 3 0 0 0-3-3"/></svg>
                        </div>
                    </div>
                </div>
                <button type="submit">登录</button>
            </form>
        </div>
    </body>
    </html>
    <?php
    exit;
}
?>