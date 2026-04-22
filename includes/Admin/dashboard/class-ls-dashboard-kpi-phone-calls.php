<?php
namespace LeadStream\Admin\Dashboard;

class KPI_PhoneCalls extends KPIProvider {
    public function current(): array {
        $value = (int) get_option('leadstream_calls_today', 0);
        $prev = (int) get_option('leadstream_calls_yesterday', 0);
        $delta = $value - $prev;
        $pct = $prev ? round(($delta / max(1, $prev)) * 100, 1) : 0.0;
        $state = $value > 0 ? 'green' : 'off';
        return [
            'label' => 'Calls Today',
            'value' => $value,
            'delta_abs' => $delta,
            'delta_pct' => $pct,
            'state' => $state
        ];
    }
}
