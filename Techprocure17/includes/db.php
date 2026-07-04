<?php
/**
 * TechProcure Tanzania - Database Connection
 * File: includes/db.php
 * Description: Database connection and query execution
 */

// Prevent direct access
if (!defined('BASE_PATH')) {
    require_once __DIR__ . '/config.php';
}

/**
 * Database Class - Singleton Pattern
 */
class Database {
    private static $instance = null;
    private $connection;
    private $query_count = 0;
    private $last_query = '';
    
    /**
     * Private constructor to prevent direct instantiation
     */
    private function __construct() {
        $this->connect();
    }
    
    /**
     * Get database instance (Singleton)
     */
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    /**
     * Connect to database
     */
    private function connect() {
        try {
            $dsn = "mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=" . DB_CHARSET . ";port=" . DB_PORT;
            
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::ATTR_PERSISTENT => DB_PERSISTENT,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES " . DB_CHARSET,
                PDO::MYSQL_ATTR_LOCAL_INFILE => true
            ];
            
            // If in development, enable query logging
            if (isDevelopment()) {
                $options[PDO::ATTR_ERRMODE] = PDO::ERRMODE_EXCEPTION;
            }
            
            $this->connection = new PDO($dsn, DB_USER, DB_PASS, $options);
            
        } catch (PDOException $e) {
            error_log("Database Connection Error: " . $e->getMessage());
            if (isDevelopment()) {
                die("Database connection failed: " . $e->getMessage());
            } else {
                die("Database connection failed. Please try again later.");
            }
        }
    }
    
    /**
     * Get PDO connection
     */
    public function getConnection() {
        return $this->connection;
    }
    
    /**
     * Prepare a SQL statement
     */
    public function prepare($sql) {
        $this->last_query = $sql;
        return $this->connection->prepare($sql);
    }
    
    /**
     * Execute a query and return result
     */
    public function query($sql) {
        $this->last_query = $sql;
        $this->query_count++;
        
        if (isDevelopment() && strpos($sql, 'SELECT') !== false) {
            $start_time = microtime(true);
            $result = $this->connection->query($sql);
            $end_time = microtime(true);
            
            // Log slow queries (over 1 second)
            if (($end_time - $start_time) > 1) {
                error_log("Slow Query: " . $sql . " - Time: " . ($end_time - $start_time) . "s");
            }
            
            return $result;
        }
        
        return $this->connection->query($sql);
    }
    
    /**
     * Execute a prepared statement with parameters
     */
    public function execute($sql, $params = []) {
        $this->last_query = $sql;
        $this->query_count++;
        
        $stmt = $this->prepare($sql);
        $stmt->execute($params);
        return $stmt;
    }
    
    /**
     * Get a single row
     */
    public function fetchOne($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetch();
    }
    
    /**
     * Get all rows
     */
    public function fetchAll($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        return $stmt->fetchAll();
    }
    
    /**
     * Get a single value
     */
    public function fetchValue($sql, $params = []) {
        $stmt = $this->execute($sql, $params);
        $result = $stmt->fetch(PDO::FETCH_NUM);
        return $result ? $result[0] : null;
    }
    
    /**
     * Insert a record
     */
    public function insert($table, $data) {
        $fields = array_keys($data);
        $placeholders = array_fill(0, count($fields), '?');
        
        $sql = "INSERT INTO $table (" . implode(', ', $fields) . ") 
                VALUES (" . implode(', ', $placeholders) . ")";
        
        $values = array_values($data);
        $this->execute($sql, $values);
        
        return $this->connection->lastInsertId();
    }
    
    /**
     * Update a record
     */
    public function update($table, $data, $where, $where_params = []) {
        $set = [];
        $values = [];
        
        foreach ($data as $field => $value) {
            $set[] = "$field = ?";
            $values[] = $value;
        }
        
        $values = array_merge($values, $where_params);
        
        $sql = "UPDATE $table SET " . implode(', ', $set) . " WHERE $where";
        return $this->execute($sql, $values)->rowCount();
    }
    
    /**
     * Delete a record
     */
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM $table WHERE $where";
        return $this->execute($sql, $params)->rowCount();
    }
    
    /**
     * Get last insert ID
     */
    public function lastInsertId() {
        return $this->connection->lastInsertId();
    }
    
    /**
     * Get row count from last statement
     */
    public function rowCount() {
        return $this->connection ? $this->connection->query("SELECT FOUND_ROWS()")->fetchColumn() : 0;
    }
    
    /**
     * Begin transaction
     */
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    /**
     * Commit transaction
     */
    public function commit() {
        return $this->connection->commit();
    }
    
    /**
     * Rollback transaction
     */
    public function rollback() {
        return $this->connection->rollback();
    }
    
    /**
     * Escape a string
     */
    public function escape($string) {
        return $this->connection->quote($string);
    }
    
    /**
     * Get query count
     */
    public function getQueryCount() {
        return $this->query_count;
    }
    
    /**
     * Get last query
     */
    public function getLastQuery() {
        return $this->last_query;
    }
    
    /**
     * Check if table exists
     */
    public function tableExists($table) {
        $sql = "SHOW TABLES LIKE ?";
        $stmt = $this->execute($sql, [$table]);
        return $stmt->rowCount() > 0;
    }
    
    /**
     * Get table status
     */
    public function getTableStatus($table) {
        $sql = "SHOW TABLE STATUS LIKE ?";
        return $this->fetchOne($sql, [$table]);
    }
    
    /**
     * Get all tables
     */
    public function getTables() {
        $sql = "SHOW TABLES";
        $result = $this->query($sql);
        $tables = [];
        while ($row = $result->fetch(PDO::FETCH_NUM)) {
            $tables[] = $row[0];
        }
        return $tables;
    }
}

// =============================================
// GLOBAL FUNCTIONS FOR BACKWARD COMPATIBILITY
// =============================================

/**
 * Get database connection (legacy support)
 */
function getDB() {
    return Database::getInstance()->getConnection();
}

/**
 * Get database instance
 */
function getDatabase() {
    return Database::getInstance();
}

/**
 * Execute a query (legacy support)
 */
function dbQuery($sql) {
    return Database::getInstance()->query($sql);
}

/**
 * Execute a prepared statement (legacy support)
 */
function dbPrepare($sql, $params = []) {
    return Database::getInstance()->execute($sql, $params);
}

/**
 * Fetch one row (legacy support)
 */
function dbFetchOne($sql, $params = []) {
    return Database::getInstance()->fetchOne($sql, $params);
}

/**
 * Fetch all rows (legacy support)
 */
function dbFetchAll($sql, $params = []) {
    return Database::getInstance()->fetchAll($sql, $params);
}

/**
 * Insert record (legacy support)
 */
function dbInsert($table, $data) {
    return Database::getInstance()->insert($table, $data);
}

/**
 * Update record (legacy support)
 */
function dbUpdate($table, $data, $where, $where_params = []) {
    return Database::getInstance()->update($table, $data, $where, $where_params);
}

/**
 * Delete record (legacy support)
 */
function dbDelete($table, $where, $params = []) {
    return Database::getInstance()->delete($table, $where, $params);
}
?>