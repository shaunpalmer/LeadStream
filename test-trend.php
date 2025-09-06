<?php
/**
 * Test script for 14-day trend functionality
 */

// WordPress bootstrap
require_once dirname(__DIR__, 3) . '/wp-config.php';

use LeadStream\Admin\Dashboard\Data;

echo "=== LeadStream Trend Test ===\n\n";

try {
    $data = new Data();
    
    // Test 14-day range
    $end = new DateTimeImmutable('today', wp_timezone());
    $start = $end->modify('-13 days');
    $range = [
        'start' => $start->format('Y-m-d'),
        'end' => $end->format('Y-m-d')
    ];
    
    echo "Date range: {$range['start']} to {$range['end']}\n\n";
    
    // Test all metrics
    $metrics = ['calls', 'forms', 'leads'];
    
    foreach ($metrics as $metric) {
        echo "=== {$metric} trend ===\n";
        $trend = $data->get_timeseries($range, $metric);
        
        echo "Labels: " . implode(', ', $trend['labels']) . "\n";
        echo "Data: " . implode(', ', $trend['data']) . "\n";
        echo "Total days: " . count($trend['labels']) . "\n";
        echo "Total events: " . array_sum($trend['data']) . "\n\n";
    }
    
    echo "Test completed successfully!\n";
    
} catch (Exception $e) {
    echo "ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
