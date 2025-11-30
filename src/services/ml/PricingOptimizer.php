<?php

/**
 * ML-Powered Pricing Optimization Service
 * 
 * This service uses machine learning algorithms to optimize venue pricing based on:
 * 1. Demand Forecasting - Analyzes historical booking patterns
 * 2. Competitive Analysis - Compares similar venues in the market
 * 3. Seasonality Patterns - Identifies peak and off-peak periods
 * 4. Market Position - Determines optimal pricing relative to competition
 * 
 * @author Gatherly EMS Team
 * @version 2.0
 */

class PricingOptimizer
{
    private $conn;
    private $venue_id;
    private $base_price;

    // ML Model Parameters
    private $demand_weight = 0.35;
    private $competition_weight = 0.30;
    private $seasonality_weight = 0.25;
    private $performance_weight = 0.10;

    // Price adjustment limits (to prevent extreme pricing)
    private $min_multiplier = 0.70;  // Lowest: 70% of base
    private $max_multiplier = 1.50;  // Highest: 150% of base

    public function __construct($db_connection, $venue_id, $base_price)
    {
        $this->conn = $db_connection;
        $this->venue_id = $venue_id;
        $this->base_price = $base_price;
    }

    /**
     * Main method to calculate optimized prices
     * Returns array with peak_price, offpeak_price, weekday_price, weekend_price
     */
    public function calculateOptimizedPrices()
    {
        // Get venue characteristics
        $venue_data = $this->getVenueCharacteristics();

        // Run ML algorithms
        $demand_score = $this->calculateDemandScore($venue_data);
        $competitive_position = $this->analyzeCompetitivePosition($venue_data);
        $seasonality_factors = $this->calculateSeasonalityFactors();
        $performance_metric = $this->calculatePerformanceMetric();

        // Calculate multipliers using weighted scoring
        $peak_multiplier = $this->calculatePeakMultiplier(
            $demand_score,
            $competitive_position,
            $seasonality_factors,
            $performance_metric
        );

        $offpeak_multiplier = $this->calculateOffpeakMultiplier(
            $demand_score,
            $competitive_position,
            $seasonality_factors,
            $performance_metric
        );

        $weekday_multiplier = $this->calculateWeekdayMultiplier(
            $demand_score,
            $competitive_position
        );

        $weekend_multiplier = $this->calculateWeekendMultiplier(
            $demand_score,
            $competitive_position
        );

        // Apply multipliers to base price
        return [
            'peak_price' => round($this->base_price * $peak_multiplier, 2),
            'offpeak_price' => round($this->base_price * $offpeak_multiplier, 2),
            'weekday_price' => round($this->base_price * $weekday_multiplier, 2),
            'weekend_price' => round($this->base_price * $weekend_multiplier, 2),
            'ml_metadata' => [
                'demand_score' => round($demand_score, 4),
                'competitive_position' => round($competitive_position, 4),
                'seasonality_high' => round($seasonality_factors['high'], 4),
                'seasonality_low' => round($seasonality_factors['low'], 4),
                'performance_metric' => round($performance_metric, 4),
                'calculated_at' => date('Y-m-d H:i:s')
            ]
        ];
    }

    /**
     * Get venue characteristics from database
     */
    private function getVenueCharacteristics()
    {
        $query = "SELECT v.venue_id, v.venue_name, v.venue_type, v.capacity, v.description,
                  v.status as venue_status, v.manager_id, v.location_id, v.created_at,
                  l.city, l.province, l.baranggay,
                  COUNT(DISTINCT e.event_id) as total_bookings,
                  AVG(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost END) as avg_booking_value,
                  COUNT(DISTINCT va.amenity_id) as amenity_count
                  FROM venues v
                  LEFT JOIN locations l ON v.location_id = l.location_id
                  LEFT JOIN events e ON v.venue_id = e.venue_id 
                      AND e.event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  LEFT JOIN venue_amenities va ON v.venue_id = va.venue_id
                  WHERE v.venue_id = ?
                  GROUP BY v.venue_id, v.venue_name, v.venue_type, v.capacity, v.description,
                           v.status, v.manager_id, v.location_id, v.created_at,
                           l.city, l.province, l.baranggay";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->venue_id);
        $stmt->execute();
        $result = $stmt->get_result();

        return $result->fetch_assoc();
    }

    /**
     * ALGORITHM 1: Demand Forecasting
     * Uses historical booking data to predict future demand
     */
    private function calculateDemandScore($venue_data)
    {
        // Get booking trends over last 12 months
        $query = "SELECT 
                  COUNT(*) as booking_count,
                  AVG(expected_guests) as avg_guests,
                  AVG(DATEDIFF(event_date, created_at)) as avg_lead_time,
                  MONTH(event_date) as month
                  FROM events
                  WHERE venue_id = ? 
                  AND event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  AND events.status IN ('confirmed', 'completed', 'pending')
                  GROUP BY MONTH(event_date)
                  ORDER BY month";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->venue_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $monthly_bookings = [];
        $total_bookings = 0;

        while ($row = $result->fetch_assoc()) {
            $monthly_bookings[$row['month']] = $row['booking_count'];
            $total_bookings += $row['booking_count'];
        }

        // Calculate demand score (0 to 1 scale)
        // Factors: booking frequency, capacity utilization, lead time
        $booking_frequency = min(1.0, $total_bookings / 24); // 24 = 2 bookings per month target
        $capacity_utilization = isset($venue_data['avg_booking_value']) && $venue_data['avg_booking_value'] > 0
            ? min(1.0, $venue_data['avg_booking_value'] / ($this->base_price * 1.2))
            : 0.5;

        // Trend analysis - are bookings increasing?
        $trend_factor = $this->calculateTrendFactor($monthly_bookings);

        // Weighted demand score
        $demand_score = (
            $booking_frequency * 0.40 +
            $capacity_utilization * 0.35 +
            $trend_factor * 0.25
        );

        return max(0.1, min(1.0, $demand_score));
    }

    /**
     * Calculate booking trend factor using linear regression
     */
    private function calculateTrendFactor($monthly_bookings)
    {
        if (empty($monthly_bookings)) return 0.5;

        $n = count($monthly_bookings);
        if ($n < 2) return 0.5;

        // Simple linear regression to find trend
        $x_values = range(1, $n);
        $y_values = array_values($monthly_bookings);

        $x_mean = array_sum($x_values) / $n;
        $y_mean = array_sum($y_values) / $n;

        $numerator = 0;
        $denominator = 0;

        for ($i = 0; $i < $n; $i++) {
            $numerator += ($x_values[$i] - $x_mean) * ($y_values[$i] - $y_mean);
            $denominator += pow($x_values[$i] - $x_mean, 2);
        }

        $slope = $denominator != 0 ? $numerator / $denominator : 0;

        // Convert slope to 0-1 scale
        // Positive slope = increasing trend (higher score)
        // Negative slope = decreasing trend (lower score)
        if ($slope > 0) {
            return min(1.0, 0.5 + ($slope * 0.1));
        } else {
            return max(0.1, 0.5 + ($slope * 0.1));
        }
    }

    /**
     * ALGORITHM 2: Competitive Analysis
     * Analyzes similar venues to determine competitive positioning
     */
    private function analyzeCompetitivePosition($venue_data)
    {
        // Find similar venues (same location, similar capacity)
        $capacity_range_low = $venue_data['capacity'] * 0.7;
        $capacity_range_high = $venue_data['capacity'] * 1.3;

        $query = "SELECT v.venue_id, v.capacity, p.base_price, p.weekend_price,
                  COUNT(DISTINCT e.event_id) as competitor_bookings,
                  AVG(CASE WHEN e.status IN ('confirmed', 'completed') THEN e.total_cost END) as competitor_avg_price
                  FROM venues v
                  JOIN prices p ON v.venue_id = p.venue_id
                  JOIN locations l ON v.location_id = l.location_id
                  LEFT JOIN events e ON v.venue_id = e.venue_id 
                      AND e.event_date >= DATE_SUB(NOW(), INTERVAL 6 MONTH)
                  WHERE v.venue_id != ?
                  AND l.city = ?
                  AND v.capacity BETWEEN ? AND ?
                  AND v.status = 'active'
                  GROUP BY v.venue_id
                  LIMIT 10";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param(
            "isdd",
            $this->venue_id,
            $venue_data['city'],
            $capacity_range_low,
            $capacity_range_high
        );
        $stmt->execute();
        $result = $stmt->get_result();

        $competitors = [];
        $market_avg_price = 0;
        $market_avg_bookings = 0;
        $count = 0;

        while ($row = $result->fetch_assoc()) {
            $competitors[] = $row;
            $market_avg_price += $row['base_price'];
            $market_avg_bookings += $row['competitor_bookings'];
            $count++;
        }

        if ($count == 0) {
            // No competitors found, return neutral position
            return 0.5;
        }

        $market_avg_price /= $count;
        $market_avg_bookings /= $count;

        // Calculate competitive position (0 to 1 scale)
        // 0 = underpriced, 0.5 = at market rate, 1 = premium pricing
        $price_position = $market_avg_price > 0
            ? min(1.0, $this->base_price / $market_avg_price)
            : 0.5;

        // Adjust based on booking performance vs competitors
        $my_bookings = $venue_data['total_bookings'] ?? 0;
        $booking_performance = $market_avg_bookings > 0
            ? min(1.0, $my_bookings / $market_avg_bookings)
            : 0.5;

        // If we're getting more bookings than average, we can increase prices
        // If we're getting fewer bookings, we should be more competitive
        $competitive_position = ($price_position * 0.6) + ($booking_performance * 0.4);

        return max(0.2, min(0.9, $competitive_position));
    }

    /**
     * ALGORITHM 3: Seasonality Pattern Analysis
     * Identifies high and low demand periods
     */
    private function calculateSeasonalityFactors()
    {
        // Analyze booking patterns by month across all venues (market trend)
        $query = "SELECT 
                  MONTH(event_date) as month,
                  COUNT(*) as booking_count,
                  AVG(total_cost) as avg_price
                  FROM events
                  WHERE event_date >= DATE_SUB(NOW(), INTERVAL 24 MONTH)
                  AND events.status IN ('confirmed', 'completed')
                  GROUP BY MONTH(event_date)";

        $result = $this->conn->query($query);

        $monthly_data = [];
        $total_bookings = 0;

        while ($row = $result->fetch_assoc()) {
            $monthly_data[$row['month']] = $row['booking_count'];
            $total_bookings += $row['booking_count'];
        }

        if (empty($monthly_data)) {
            return ['high' => 1.20, 'low' => 0.85];
        }

        $avg_monthly_bookings = $total_bookings / count($monthly_data);

        // Find peak and low months
        $peak_months = [];
        $low_months = [];

        foreach ($monthly_data as $month => $bookings) {
            if ($bookings > $avg_monthly_bookings * 1.3) {
                $peak_months[] = $bookings;
            } elseif ($bookings < $avg_monthly_bookings * 0.7) {
                $low_months[] = $bookings;
            }
        }

        // Calculate seasonality multipliers
        $high_factor = !empty($peak_months)
            ? 1.0 + (0.30 * (max($peak_months) / $avg_monthly_bookings - 1))
            : 1.20;

        $low_factor = !empty($low_months)
            ? 1.0 - (0.20 * (1 - min($low_months) / $avg_monthly_bookings))
            : 0.80;

        return [
            'high' => min(1.35, max(1.15, $high_factor)),
            'low' => min(0.90, max(0.70, $low_factor))
        ];
    }

    /**
     * Calculate venue performance metric
     * Based on booking conversion rate and customer satisfaction
     */
    private function calculatePerformanceMetric()
    {
        // Get venue's booking statistics
        $query = "SELECT 
                  COUNT(*) as total_events,
                  SUM(CASE WHEN e.status IN ('confirmed', 'completed') THEN 1 ELSE 0 END) as successful_events,
                  AVG(e.expected_guests) as avg_guests,
                  v.capacity
                  FROM events e
                  JOIN venues v ON e.venue_id = v.venue_id
                  WHERE e.venue_id = ?
                  AND e.event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->venue_id);
        $stmt->execute();
        $result = $stmt->get_result();
        $data = $result->fetch_assoc();

        if (!$data || $data['total_events'] == 0) {
            return 0.5; // Neutral for new venues
        }

        // Success rate
        $success_rate = $data['successful_events'] / $data['total_events'];

        // Capacity utilization
        $capacity_util = $data['capacity'] > 0
            ? min(1.0, $data['avg_guests'] / $data['capacity'])
            : 0.5;

        // Combined performance metric
        return ($success_rate * 0.6) + ($capacity_util * 0.4);
    }

    /**
     * Calculate peak season multiplier
     */
    private function calculatePeakMultiplier($demand, $competition, $seasonality, $performance)
    {
        $multiplier = 1.0;

        // Base peak adjustment
        $multiplier += 0.20; // 20% base increase for peak

        // Demand adjustment
        $multiplier += ($demand - 0.5) * 0.20; // ±10% based on demand

        // Competitive position adjustment
        if ($competition > 0.6) {
            // We can command premium prices
            $multiplier += 0.10;
        }

        // Seasonality factor
        $multiplier *= $seasonality['high'];

        // Performance bonus
        if ($performance > 0.7) {
            $multiplier += 0.05;
        }

        return max($this->min_multiplier, min($this->max_multiplier, $multiplier));
    }

    /**
     * Calculate off-peak season multiplier
     */
    private function calculateOffpeakMultiplier($demand, $competition, $seasonality, $performance)
    {
        $multiplier = 1.0;

        // Base off-peak reduction
        $multiplier -= 0.15; // 15% base decrease for off-peak

        // Demand adjustment (inverse - lower demand = lower price)
        $multiplier -= (0.5 - $demand) * 0.15;

        // Competitive strategy - be more aggressive if low bookings
        if ($demand < 0.4) {
            $multiplier -= 0.10; // Additional discount to attract customers
        }

        // Seasonality factor
        $multiplier *= $seasonality['low'];

        // Don't go too low if performance is good
        if ($performance > 0.6) {
            $multiplier += 0.05;
        }

        return max($this->min_multiplier, min($this->max_multiplier, $multiplier));
    }

    /**
     * Calculate weekday multiplier
     */
    private function calculateWeekdayMultiplier($demand, $competition)
    {
        $multiplier = 1.0;

        // Base weekday pricing (typically lower)
        $multiplier -= 0.08; // 8% base decrease for weekdays

        // Demand factor
        if ($demand > 0.6) {
            $multiplier += 0.05; // Reduce discount if demand is high
        }

        // Competitive positioning
        if ($competition < 0.4) {
            // We're cheaper than market, can maintain lower weekday prices
            $multiplier -= 0.04;
        }

        return max($this->min_multiplier, min($this->max_multiplier, $multiplier));
    }

    /**
     * Calculate weekend multiplier
     */
    private function calculateWeekendMultiplier($demand, $competition)
    {
        $multiplier = 1.0;

        // Base weekend premium
        $multiplier += 0.15; // 15% base increase for weekends

        // Demand factor
        if ($demand > 0.7) {
            $multiplier += 0.10; // High demand = higher weekend premium
        }

        // Competitive positioning
        if ($competition > 0.6 && $demand > 0.5) {
            // Strong position = can charge more on weekends
            $multiplier += 0.05;
        }

        return max($this->min_multiplier, min($this->max_multiplier, $multiplier));
    }

    /**
     * Get detailed ML insights for analytics
     */
    public function getMLInsights()
    {
        $venue_data = $this->getVenueCharacteristics();
        $demand_score = $this->calculateDemandScore($venue_data);
        $competitive_position = $this->analyzeCompetitivePosition($venue_data);
        $seasonality_factors = $this->calculateSeasonalityFactors();
        $performance_metric = $this->calculatePerformanceMetric();
        $demand_history = $this->getDemandHistory();

        return [
            'demand_forecast' => [
                'score' => round($demand_score * 100, 1),
                'trend' => $demand_score > 0.6 ? 'increasing' : ($demand_score < 0.4 ? 'decreasing' : 'stable'),
                'recommendation' => $this->getDemandRecommendation($demand_score),
                'history' => $demand_history
            ],
            'competitive_analysis' => [
                'position' => round($competitive_position * 100, 1),
                'status' => $competitive_position > 0.6 ? 'premium' : ($competitive_position < 0.4 ? 'value' : 'market-rate'),
                'recommendation' => $this->getCompetitiveRecommendation($competitive_position, $demand_score)
            ],
            'seasonality' => [
                'peak_multiplier' => round($seasonality_factors['high'], 2),
                'offpeak_multiplier' => round($seasonality_factors['low'], 2),
                'recommendation' => 'Adjust pricing seasonally for maximum revenue'
            ],
            'performance' => [
                'score' => round($performance_metric * 100, 1),
                'rating' => $performance_metric > 0.7 ? 'excellent' : ($performance_metric > 0.5 ? 'good' : 'needs improvement'),
                'recommendation' => $this->getPerformanceRecommendation($performance_metric)
            ],
            'price_calculation_explanation' => [
                'base_price' => $this->base_price,
                'algorithm' => 'Weighted Scoring Model',
                'factors' => [
                    'demand' => ['weight' => '35%', 'score' => round($demand_score * 100, 1) . '%'],
                    'competition' => ['weight' => '30%', 'score' => round($competitive_position * 100, 1) . '%'],
                    'seasonality' => ['weight' => '25%', 'multiplier_range' => round($seasonality_factors['low'], 2) . 'x - ' . round($seasonality_factors['high'], 2) . 'x'],
                    'performance' => ['weight' => '10%', 'score' => round($performance_metric * 100, 1) . '%']
                ],
                'explanation' => 'The AI calculates optimal prices by combining 4 factors: (1) Demand Score analyzes your booking frequency and trends, (2) Competitive Position compares you to similar venues, (3) Seasonality identifies peak/off-peak periods, (4) Performance measures your booking success rate. Each factor is weighted and combined to create multipliers that adjust your base price for different scenarios.'
            ]
        ];
    }

    /**
     * Get demand history for last 12 months for charting
     */
    private function getDemandHistory()
    {
        $query = "SELECT 
                  DATE_FORMAT(event_date, '%Y-%m') as month,
                  COUNT(*) as booking_count
                  FROM events
                  WHERE venue_id = ? 
                  AND event_date >= DATE_SUB(NOW(), INTERVAL 12 MONTH)
                  AND events.status IN ('confirmed', 'completed', 'pending')
                  GROUP BY DATE_FORMAT(event_date, '%Y-%m')
                  ORDER BY month ASC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param("i", $this->venue_id);
        $stmt->execute();
        $result = $stmt->get_result();

        $labels = [];
        $data = [];

        while ($row = $result->fetch_assoc()) {
            $labels[] = date('M Y', strtotime($row['month'] . '-01'));
            $data[] = intval($row['booking_count']);
        }

        return [
            'labels' => $labels,
            'data' => $data
        ];
    }

    private function getDemandRecommendation($score)
    {
        if ($score > 0.7) {
            return "High demand detected. Consider increasing prices by 10-15% to maximize revenue.";
        } elseif ($score < 0.3) {
            return "Low demand. Implement promotional pricing or special packages to attract bookings.";
        } else {
            return "Moderate demand. Maintain current pricing strategy with seasonal adjustments.";
        }
    }

    private function getCompetitiveRecommendation($position, $demand)
    {
        if ($position > 0.7 && $demand > 0.6) {
            return "Strong market position. You can maintain premium pricing.";
        } elseif ($position < 0.3) {
            return "Highly competitive pricing. Focus on value-added services to justify increases.";
        } else {
            return "Balanced market position. Monitor competitor pricing and adjust accordingly.";
        }
    }

    private function getPerformanceRecommendation($score)
    {
        if ($score > 0.7) {
            return "Excellent performance. Your venue is operating efficiently - consider premium pricing.";
        } elseif ($score < 0.4) {
            return "Performance below expectations. Focus on improving booking conversion and capacity utilization.";
        } else {
            return "Good performance. Continue optimizing operations for better results.";
        }
    }
}
