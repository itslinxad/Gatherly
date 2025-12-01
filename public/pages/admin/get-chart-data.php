<?php
session_start();

// Check if user is logged in and is an administrator
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'administrator') {
    http_response_code(403);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once '../../../src/services/dbconnect.php';

header('Content-Type: application/json');

$type = $_GET['type'] ?? '';
$months = $_GET['months'] ?? '6';

$response = [
    'labels' => [],
    'values' => []
];

try {
    if ($type === 'user_growth') {
        // Build query based on time range
        if ($months === 'all') {
            $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
                FROM users 
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
        } else {
            $monthsInt = intval($months);
            $query = "SELECT 
                DATE_FORMAT(created_at, '%Y-%m') as month,
                COUNT(*) as count
                FROM users 
                WHERE created_at >= DATE_SUB(CURRENT_DATE(), INTERVAL $monthsInt MONTH)
                GROUP BY DATE_FORMAT(created_at, '%Y-%m')
                ORDER BY month ASC";
        }

        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
                $response['values'][] = intval($row['count']);
            }
        }
    } elseif ($type === 'revenue_trend') {
        // Build query based on time range
        if ($months === 'all') {
            $query = "SELECT 
                DATE_FORMAT(event_date, '%Y-%m') as month,
                SUM(total_cost) as revenue,
                COUNT(*) as event_count
                FROM events 
                WHERE status IN ('confirmed', 'completed')
                GROUP BY DATE_FORMAT(event_date, '%Y-%m')
                ORDER BY month ASC";
        } else {
            $monthsInt = intval($months);
            $query = "SELECT 
                DATE_FORMAT(event_date, '%Y-%m') as month,
                SUM(total_cost) as revenue,
                COUNT(*) as event_count
                FROM events 
                WHERE status IN ('confirmed', 'completed')
                AND event_date >= DATE_SUB(CURRENT_DATE(), INTERVAL $monthsInt MONTH)
                GROUP BY DATE_FORMAT(event_date, '%Y-%m')
                ORDER BY month ASC";
        }

        $result = $conn->query($query);

        if ($result && $result->num_rows > 0) {
            while ($row = $result->fetch_assoc()) {
                $response['labels'][] = date('M Y', strtotime($row['month'] . '-01'));
                $response['values'][] = floatval($row['revenue'] ?? 0);
            }
        }
    } else {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid type parameter']);
        exit();
    }

    echo json_encode($response);
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Server error: ' . $e->getMessage()]);
}

$conn->close();
