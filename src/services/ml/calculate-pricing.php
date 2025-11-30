<?php

/**
 * ML Pricing Calculation API
 * 
 * This endpoint calculates optimized prices using ML algorithms
 * Called by manager's pricing interface
 */

session_start();

// Enable error handling for debugging
error_reporting(E_ALL);
ini_set('display_errors', 0); // Don't display errors, log them instead

// Start output buffering to catch any unwanted output
ob_start();

header('Content-Type: application/json');

// Check authentication
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'manager') {
    ob_clean();
    http_response_code(401);
    echo json_encode(['error' => 'Unauthorized']);
    exit();
}

require_once __DIR__ . '/../dbconnect.php';
require_once __DIR__ . '/PricingOptimizer.php';

$user_id = $_SESSION['user_id'];

// Handle different request types
$request_method = $_SERVER['REQUEST_METHOD'];

if ($request_method === 'POST') {
    // Calculate prices for a venue
    $input = json_decode(file_get_contents('php://input'), true);

    $venue_id = isset($input['venue_id']) ? intval($input['venue_id']) : 0;
    $base_price = isset($input['base_price']) ? floatval($input['base_price']) : 0;

    if ($venue_id <= 0 || $base_price <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid venue_id or base_price']);
        exit();
    }

    // Verify venue belongs to this manager
    $stmt = $conn->prepare("SELECT venue_id FROM venues WHERE venue_id = ? AND manager_id = ?");
    $stmt->bind_param("ii", $venue_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Venue not found or access denied']);
        exit();
    }

    try {
        // Initialize ML pricing optimizer
        $optimizer = new PricingOptimizer($conn, $venue_id, $base_price);

        // Calculate optimized prices
        $calculated_prices = $optimizer->calculateOptimizedPrices();

        // Update prices table
        $update_query = "UPDATE prices SET 
                        peak_price = ?,
                        offpeak_price = ?,
                        weekday_price = ?,
                        weekend_price = ?,
                        ml_demand_score = ?,
                        ml_competitive_position = ?,
                        ml_seasonality_high = ?,
                        ml_seasonality_low = ?,
                        ml_performance_score = ?,
                        ml_last_calculated = NOW()
                        WHERE venue_id = ?";

        $stmt = $conn->prepare($update_query);
        $stmt->bind_param(
            "dddddddddi",
            $calculated_prices['peak_price'],
            $calculated_prices['offpeak_price'],
            $calculated_prices['weekday_price'],
            $calculated_prices['weekend_price'],
            $calculated_prices['ml_metadata']['demand_score'],
            $calculated_prices['ml_metadata']['competitive_position'],
            $calculated_prices['ml_metadata']['seasonality_high'],
            $calculated_prices['ml_metadata']['seasonality_low'],
            $calculated_prices['ml_metadata']['performance_metric'],
            $venue_id
        );

        if (!$stmt->execute()) {
            // If update failed, try insert
            $insert_query = "INSERT INTO prices 
                            (venue_id, base_price, peak_price, offpeak_price, weekday_price, weekend_price,
                             ml_demand_score, ml_competitive_position, ml_seasonality_high, ml_seasonality_low,
                             ml_performance_score, ml_last_calculated)
                            VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, NOW())";

            $stmt = $conn->prepare($insert_query);
            $stmt->bind_param(
                "idddddddddd",
                $venue_id,
                $base_price,
                $calculated_prices['peak_price'],
                $calculated_prices['offpeak_price'],
                $calculated_prices['weekday_price'],
                $calculated_prices['weekend_price'],
                $calculated_prices['ml_metadata']['demand_score'],
                $calculated_prices['ml_metadata']['competitive_position'],
                $calculated_prices['ml_metadata']['seasonality_high'],
                $calculated_prices['ml_metadata']['seasonality_low'],
                $calculated_prices['ml_metadata']['performance_metric']
            );
            $stmt->execute();
        }

        // Log calculation history
        $history_query = "INSERT INTO pricing_ml_history 
                         (venue_id, base_price, calculated_peak_price, calculated_offpeak_price,
                          calculated_weekday_price, calculated_weekend_price, ml_demand_score,
                          ml_competitive_position, ml_seasonality_high, ml_seasonality_low,
                          ml_performance_score, calculation_type)
                         VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, ?, ?, 'manual_trigger')";

        $stmt = $conn->prepare($history_query);
        $stmt->bind_param(
            "idddddddddd",
            $venue_id,
            $base_price,
            $calculated_prices['peak_price'],
            $calculated_prices['offpeak_price'],
            $calculated_prices['weekday_price'],
            $calculated_prices['weekend_price'],
            $calculated_prices['ml_metadata']['demand_score'],
            $calculated_prices['ml_metadata']['competitive_position'],
            $calculated_prices['ml_metadata']['seasonality_high'],
            $calculated_prices['ml_metadata']['seasonality_low'],
            $calculated_prices['ml_metadata']['performance_metric']
        );
        $stmt->execute();

        echo json_encode([
            'success' => true,
            'prices' => $calculated_prices,
            'message' => 'Prices calculated successfully using ML algorithms'
        ]);
    } catch (Exception $e) {
        ob_clean();
        error_log("ML Calculation Error: " . $e->getMessage() . " - " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Calculation failed',
            'message' => $e->getMessage()
        ]);
    }
} elseif ($request_method === 'GET') {
    // Get ML insights for a venue
    $venue_id = isset($_GET['venue_id']) ? intval($_GET['venue_id']) : 0;

    if ($venue_id <= 0) {
        http_response_code(400);
        echo json_encode(['error' => 'Invalid venue_id']);
        exit();
    }

    // Verify venue belongs to this manager
    $stmt = $conn->prepare("SELECT v.*, p.base_price FROM venues v 
                           LEFT JOIN prices p ON v.venue_id = p.venue_id
                           WHERE v.venue_id = ? AND v.manager_id = ?");
    $stmt->bind_param("ii", $venue_id, $user_id);
    $stmt->execute();
    $result = $stmt->get_result();

    if ($result->num_rows === 0) {
        http_response_code(403);
        echo json_encode(['error' => 'Venue not found or access denied']);
        exit();
    }

    $venue = $result->fetch_assoc();
    $base_price = $venue['base_price'] ?? 50000;

    try {
        $optimizer = new PricingOptimizer($conn, $venue_id, $base_price);
        $insights = $optimizer->getMLInsights();

        // Get calculation history
        $history_query = "SELECT * FROM pricing_ml_history 
                         WHERE venue_id = ? 
                         ORDER BY created_at DESC 
                         LIMIT 10";
        $stmt = $conn->prepare($history_query);
        $stmt->bind_param("i", $venue_id);
        $stmt->execute();
        $history_result = $stmt->get_result();

        $history = [];
        while ($row = $history_result->fetch_assoc()) {
            $history[] = $row;
        }

        echo json_encode([
            'success' => true,
            'venue_id' => $venue_id,
            'venue_name' => $venue['venue_name'],
            'current_base_price' => $base_price,
            'insights' => $insights,
            'calculation_history' => $history
        ]);
    } catch (Exception $e) {
        ob_clean();
        error_log("ML Insights Error: " . $e->getMessage() . " - " . $e->getTraceAsString());
        http_response_code(500);
        echo json_encode([
            'success' => false,
            'error' => 'Failed to get insights',
            'message' => $e->getMessage(),
            'details' => $e->getTraceAsString()
        ]);
    }
} else {
    ob_clean();
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
}

// Clean output buffer and send
ob_end_flush();

$conn->close();
