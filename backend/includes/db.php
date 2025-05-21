<?php
// backend/includes/db.php
require_once __DIR__ . '/../config.php';

$conn = null;

function getDBConnection() {
    global $conn;
    if ($conn === null) {
        // 创建连接
        $conn = new mysqli(DB_HOST, DB_USER, DB_PASS, DB_NAME);

        // 检测连接
        if ($conn->connect_error) {
            // 对于API，最好是返回JSON错误，而不是直接 die()
            // 但在连接数据库失败这种严重情况下，die() 可能也行
            error_log("数据库连接失败: " . $conn->connect_error); // 写入服务器错误日志
            // 为避免暴露详细错误给客户端，可以返回通用错误信息
            // header('Content-Type: application/json');
            // echo json_encode(['success' => false, 'message' => '数据库连接错误，请稍后重试。']);
            // exit;
            die("数据库连接失败: " . $conn->connect_error); // 简单处理
        }

        // 设置字符集
        if (!$conn->set_charset("utf8mb4")) {
            error_log("设置字符集 utf8mb4 失败: " . $conn->error);
            // die("Error loading character set utf8mb4: %s\n". $conn->error);
        }
    }
    return $conn;
}

// 你可以在这里添加更多数据库相关的辅助函数，例如安全执行查询等

/**
 * 准备并执行一个 SQL 语句 (防止 SQL 注入)
 * @param mysqli $db 数据库连接对象
 * @param string $sql SQL 语句模板，用 ? 作为占位符
 * @param string $types 参数类型字符串 (例如 "iss" 表示 integer, string, string)
 * @param array $params 参数数组
 * @return mysqli_stmt|false 预处理语句对象或 false
 */
function preparedQuery(mysqli $db, string $sql, string $types = "", array $params = []) {
    $stmt = $db->prepare($sql);
    if ($stmt === false) {
        error_log("SQL prepare error: " . $db->error . " SQL: " . $sql);
        return false;
    }
    if ($types != "" && count($params) > 0) {
        if (!$stmt->bind_param($types, ...$params)) {
            error_log("SQL bind_param error: " . $stmt->error);
            $stmt->close();
            return false;
        }
    }
    if (!$stmt->execute()) {
        error_log("SQL execute error: " . $stmt->error);
        $stmt->close();
        return false;
    }
    return $stmt;
}

?>
