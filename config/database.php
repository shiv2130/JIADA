<?php
/**
 * JIADA Portal - Database Configuration
 * MySQL Database Connection Settings
 */

// Prevent direct access
if (!defined('JIADA_APP')) {
    die('Direct access not permitted');
}

/**
 * Database Configuration Class
 */
class Database {
    // Database credentials
    private $host = 'localhost';
    private $username = 'root';
    private $password = '';
    private $database = 'jiada';
    private $charset = 'utf8mb4';
    
    // Connection instance
    private static $instance = null;
    private $connection;
    
    // Private constructor for singleton
    private function __construct() {
        try {
            $dsn = "mysql:host={$this->host};dbname={$this->database};charset={$this->charset}";
            $options = [
                PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
                PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
                PDO::ATTR_EMULATE_PREPARES => false,
                PDO::MYSQL_ATTR_INIT_COMMAND => "SET NAMES {$this->charset}"
            ];
            
            $this->connection = new PDO($dsn, $this->username, $this->password, $options);
        } catch (PDOException $e) {
            error_log("Database connection failed: " . $e->getMessage());
            die("Database connection failed. Please try again later.");
        }
    }
    
    // Get singleton instance
    public static function getInstance() {
        if (self::$instance === null) {
            self::$instance = new self();
        }
        return self::$instance;
    }
    
    // Get PDO connection
    public function getConnection() {
        return $this->connection;
    }
    
    // Execute query
    public function query($sql, $params = []) {
        try {
            $stmt = $this->connection->prepare($sql);
            $stmt->execute($params);
            return $stmt;
        } catch (PDOException $e) {
            error_log("Query execution failed: " . $e->getMessage());
            throw $e;
        }
    }
    
    // Fetch single row
    public function fetchOne($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetch();
    }
    
    // Fetch all rows
    public function fetchAll($sql, $params = []) {
        $stmt = $this->query($sql, $params);
        return $stmt->fetchAll();
    }
    
    // Insert and return last insert ID
    public function insert($table, $data) {
        $columns = implode(', ', array_keys($data));
        $placeholders = ':' . implode(', :', array_keys($data));
        
        $sql = "INSERT INTO {$table} ({$columns}) VALUES ({$placeholders})";
        $this->query($sql, $data);
        
        return $this->connection->lastInsertId();
    }
    
    // Update records
    public function update($table, $data, $where, $whereParams = []) {
        $set = [];
        $params = [];
        $i = 0;
        foreach ($data as $key => $value) {
            $paramName = "set_{$key}";
            $set[] = "{$key} = :{$paramName}";
            $params[$paramName] = $value;
            $i++;
        }
        $setString = implode(', ', $set);
        
        // Handle positional where parameters
        if (strpos($where, '?') !== false) {
            $whereParts = explode('?', $where);
            $whereClause = '';
            $j = 0;
            foreach ($whereParts as $idx => $part) {
                $whereClause .= $part;
                if ($idx < count($whereParts) - 1 && isset($whereParams[$j])) {
                    $paramName = "where_{$j}";
                    $whereClause .= ":{$paramName}";
                    $params[$paramName] = $whereParams[$j];
                    $j++;
                }
            }
        } else {
            $whereClause = $where;
            foreach ($whereParams as $key => $value) {
                $paramName = "where_param_{$key}";
                $params[$paramName] = $value;
                $whereClause = str_replace(':' . $key, ":{$paramName}", $whereClause);
            }
        }
        
        $sql = "UPDATE {$table} SET {$setString} WHERE {$whereClause}";
        
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Delete records
    public function delete($table, $where, $params = []) {
        $sql = "DELETE FROM {$table} WHERE {$where}";
        $stmt = $this->query($sql, $params);
        return $stmt->rowCount();
    }
    
    // Begin transaction
    public function beginTransaction() {
        return $this->connection->beginTransaction();
    }
    
    // Commit transaction
    public function commit() {
        return $this->connection->commit();
    }
    
    // Rollback transaction
    public function rollback() {
        return $this->connection->rollBack();
    }
    
    // Prevent cloning
    private function __clone() {}
    
    // Prevent unserialization
    public function __wakeup() {
        throw new Exception("Cannot unserialize singleton");
    }
}

// Database configuration constants
define('DB_HOST', 'localhost');
define('DB_USER', 'root');
define('DB_PASS', '');
define('DB_NAME', 'jiada');
define('DB_CHARSET', 'utf8mb4');

// Get database connection
function getDB() {
    return Database::getInstance();
}