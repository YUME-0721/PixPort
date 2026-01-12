<?php
/**
 * 数据库连接类
 * 自动检测并连接 SQLite（轻量级）或 MySQL（传统方案）
 */
class Database {
    private static $instance = null;
    private $pdo;
    private $dbType = 'sqlite'; // 默认使用 SQLite
    
    private function __construct() {
        // 优先使用 SQLite（轻量化方案）
        $sqliteDb = dirname(__DIR__) . '/database/pixport.db';
        $sqliteDir = dirname($sqliteDb);
        
        // 如果显式配置了 MySQL 且可连接，则使用 MySQL
        $useMysql = getenv('USE_MYSQL') === 'true';
        
        if ($useMysql && $this->testMysqlConnection()) {
            $this->initMysql();
        } else {
            $this->initSqlite($sqliteDb, $sqliteDir);
        }
    }
    
    /**
     * 初始化 SQLite 数据库
     */
    private function initSqlite($dbPath, $dbDir) {
        try {
            // 确保数据库目录存在
            if (!is_dir($dbDir)) {
                mkdir($dbDir, 0755, true);
            }
            
            $isNewDb = !file_exists($dbPath);
            
            $this->pdo = new PDO("sqlite:{$dbPath}", null, null, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false
            ]);
            
            // 启用外键约束
            $this->pdo->exec('PRAGMA foreign_keys = ON');
            
            // 如果是新数据库，执行初始化脚本
            if ($isNewDb) {
                $this->initializeSqliteSchema();
            }
            
            $this->dbType = 'sqlite';
        } catch (PDOException $e) {
            error_log("SQLite 连接失败: " . $e->getMessage());
            throw new Exception("数据库连接失败");
        }
    }
    
    /**
     * 初始化 MySQL 数据库（传统方案）
     */
    private function initMysql() {
        $host = getenv('DB_HOST') ?: 'mysql';
        $port = getenv('DB_PORT') ?: '3306';
        $dbname = getenv('DB_NAME') ?: 'pixport';
        $username = getenv('DB_USER') ?: 'root';
        $password = getenv('DB_PASSWORD') ?: 'root';
        
        $dsn = "mysql:host={$host};port={$port};dbname={$dbname};charset=utf8mb4";
        
        try {
            $this->pdo = new PDO($dsn, $username, $password, [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES utf8mb4"
            ]);
            $this->dbType = 'mysql';
        } catch (PDOException $e) {
            error_log("MySQL 连接失败: " . $e->getMessage());
            throw new Exception("数据库连接失败");
        }
    }
    
    /**
     * 测试 MySQL 连接是否可用
     */
    private function testMysqlConnection() {
        $host = getenv('DB_HOST');
        if (!$host || $host === 'mysql') {
            return false;
        }
        return true;
    }
    
    /**
     * 执行 SQLite 初始化脚本
     */
    private function initializeSqliteSchema() {
        $sqlFile = dirname(__DIR__) . '/database/init_sqlite.sql';
        if (!file_exists($sqlFile)) {
            error_log("SQLite 初始化脚本不存在: {$sqlFile}");
            return;
        }
        
        $sql = file_get_contents($sqlFile);
        $this->pdo->exec($sql);
    }
    
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    public function getConnection() {
        return $this->pdo;
    }
    
    /**
     * 获取数据库类型
     */
    public function getDatabaseType() {
        return $this->dbType;
    }
    
    /**
     * 执行查询
     */
    public function query($sql, $params = []) {
        try {
            $stmt = $this->pdo->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("SQL执行失败: " . $e->getMessage());
            throw new Exception("数据库查询失败");
        }
    }
    
    /**
     * 查询单条记录
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * 查询所有记录
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * 插入数据并返回ID
     */
    public function insert($table, $data) {
        $columns = array_keys($data);
        $placeholders = array_map(function($col) { return ":$col"; }, $columns);
        
        $sql = "INSERT INTO {$table} (" . implode(', ', $columns) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $this->query($sql, $data);
        return $this->pdo->lastInsertId();
    }
    
    /**
     * 更新数据
     */
    public function update($table, $data, $where, $whereParams = []) {
        $setParts = [];
        foreach (array_keys($data) as $col) {
            $setParts[] = "{$col} = :{$col}";
        }
        
        $sql = "UPDATE {$table} SET " . implode(', ', $setParts) . " WHERE {$where}";
        
        $params = array_merge($data, $whereParams);
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 删除数据
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    /**
     * 开始事务
     */
    public function beginTransaction() {
        return $this->pdo->beginTransaction();
    }
    
    /**
     * 提交事务
     */
    public function commit() {
        return $this->pdo->commit();
    }
    
    /**
     * 回滚事务
     */
    public function rollback() {
        return $this->pdo->rollBack();
    }
}
