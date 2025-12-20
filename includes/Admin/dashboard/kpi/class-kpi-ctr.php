<?php
class KPI_CTR extends KPI_Provider {
    protected string $key   = 'ctr';
    protected string $label = 'CTR %';

    public function value(array $range): int {
        // Stub: if you track impressions/clicks elsewhere, wire it here.
        // For now, return 0 so the tile shows but stays neutral.
        return 0;
    }

    public function delta(array $range): array {
        return ['abs' => 0, 'pct' => 0, 'prev' => 0];
    }
}
