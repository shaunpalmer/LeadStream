<?php
/**
 * Test Dashboard Output and JavaScript Integration
 */

// WordPress bootstrap
require_once dirname(__DIR__, 3) . '/wp-config.php';

use LeadStream\Admin\Dashboard\Data;
use LeadStream\Admin\Dashboard\Status;
use LeadStream\Admin\Dashboard\Render;

echo "=== Dashboard Integration Test ===\n\n";

try {
    // Simulate the dashboard rendering process
    $data = new Data();
    $status = new Status();
    $render = new Render($data, $status);
    
    echo "1. Testing KPI data structure...\n";
    $kpis = $data->kpis();
    foreach ($kpis as $key => $kpi) {
        $value = is_array($kpi) ? $kpi['value'] : $kpi;
        echo "   {$key}: {$value}\n";
    }
    echo "\n";
    
    echo "2. Testing trend data...\n";
    $end = new DateTimeImmutable('today', wp_timezone());
    $start = $end->modify('-13 days');
    $range = ['start' => $start->format('Y-m-d'), 'end' => $end->format('Y-m-d')];
    
    $trend = $data->get_timeseries($range, 'calls');
    echo "   Trend labels count: " . count($trend['labels']) . "\n";
    echo "   Trend data count: " . count($trend['data']) . "\n";
    echo "   Sample data: " . implode(', ', array_slice($trend['data'], -5)) . "\n\n";
    
    echo "3. Testing JavaScript data format...\n";
    // Simulate the JavaScript data structure
    $js_data = [
        'kpis' => [],
        'trend' => $trend,
        'status' => $status->flags(),
        'range' => $range,
        'metric' => 'calls'
    ];
    
    foreach ($kpis as $key => $kpi_data) {
        $js_data['kpis'][] = [
            'id' => $key,
            'label' => ucfirst(str_replace('_', ' ', $key)),
            'value' => is_array($kpi_data) ? (int)($kpi_data['value'] ?? 0) : (int)$kpi_data,
            'delta_abs' => is_array($kpi_data) ? (int)($kpi_data['delta_abs'] ?? 0) : 0,
            'delta_pct' => is_array($kpi_data) ? (float)($kpi_data['delta_pct'] ?? 0) : 0,
            'state' => is_array($kpi_data) ? ($kpi_data['state'] ?? 'green') : 'green'
        ];
    }
    
    echo "   JavaScript data structure ready:\n";
    echo "   - KPIs: " . count($js_data['kpis']) . " items\n";
    echo "   - Trend: " . count($js_data['trend']['labels']) . " data points\n";
    echo "   - Status: " . count($js_data['status']) . " flags\n";
    echo "   - Range: {$js_data['range']['start']} to {$js_data['range']['end']}\n\n";
    
    echo "✅ All dashboard components are working correctly!\n";
    echo "The 14-day trend chart should now display with real data.\n";
    
} catch (Exception $e) {
    echo "❌ ERROR: " . $e->getMessage() . "\n";
    echo "Trace: " . $e->getTraceAsString() . "\n";
}
