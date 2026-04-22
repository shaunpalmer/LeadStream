<?php
namespace LeadStream\Admin\Dashboard;

class KPI_CTR extends KPIProvider {
    public function current(): array {
        $clicks = (int) get_option('leadstream_clicks_today', 0);
        $imprs = (int) get_option('leadstream_impressions_today', 0);
        $value = $imprs ? round(($clicks / max(1, $imprs)) * 100, 1) : 0.0;
        $prev_clicks = (int) get_option('leadstream_clicks_yesterday', 0);
        $prev_imprs = (int) get_option('leadstream_impressions_yesterday', 0);
        $prev = $prev_imprs ? round(($prev_clicks / max(1, $prev_imprs)) * 100, 1) : 0.0;
        $delta = $value - $prev;
        $pct = $prev ? round(($delta / max(1, $prev)) * 100, 1) : 0.0;
        $state = $value > 0 ? 'green' : 'off';
        return [
            'label' => 'CTR %',
            'value' => $value,
            'delta_abs' => $delta,
            'delta_pct' => $pct,
            'state' => $state
        ];
    }
}
