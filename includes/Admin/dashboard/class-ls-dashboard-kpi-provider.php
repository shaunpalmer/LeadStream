<?php
namespace LeadStream\Admin\Dashboard;

abstract class KPIProvider {
    /** Return array: ['label'=>string,'value'=>int,'delta_abs'=>int,'delta_pct'=>float,'state'=>string] */
    abstract public function current(): array;
    /** Optional: human key for identification */
    public function key(): string { return strtolower((new \ReflectionClass($this))->getShortName()); }

    // Legacy compatibility: expose the older KPI_Provider API expected by older code.
    public function label(): string {
        if (method_exists($this, 'current')) {
            try {
                $c = $this->current();
                if (is_array($c) && isset($c['label'])) return (string) $c['label'];
            } catch (\Throwable $e) {
                // fall through to defaults
            }
        }
        return property_exists($this, 'label') ? (string) $this->label : '';
    }

    public function value(array $range): int {
        if (method_exists($this, 'current')) {
            try {
                $c = $this->current();
                if (is_array($c) && isset($c['value'])) return (int) $c['value'];
            } catch (\Throwable $e) {
                // fall through to 0
            }
        }
        return property_exists($this, 'value') ? (int) $this->value : 0;
    }

    public function delta(array $range): array {
        // Expect ['abs'=>int,'pct'=>float,'prev'=>int]
        if (method_exists($this, 'current')) {
            try {
                $c = $this->current();
                if (is_array($c)) {
                    $abs = isset($c['delta_abs']) ? (int) $c['delta_abs'] : (isset($c['delta']) ? (int) $c['delta'] : 0);
                    $pct = isset($c['delta_pct']) ? (float) $c['delta_pct'] : 0.0;
                    $prev = isset($c['delta_prev']) ? (int) $c['delta_prev'] : 0;
                    return ['abs' => $abs, 'pct' => $pct, 'prev' => $prev];
                }
            } catch (\Throwable $e) {
                // fall through to base behaviour
            }
        }
        // Default delta via legacy Data helper if present
        if (method_exists($this, 'data') || property_exists($this, 'data')) {
            try {
                if (isset($this->data) && is_object($this->data) && method_exists($this->data, 'delta')) {
                    return (array) $this->data->delta($range, $this->key());
                }
            } catch (\Throwable $e) {
                // ignore
            }
        }
        return ['abs' => 0, 'pct' => 0.0, 'prev' => 0];
    }

    public function state(): string {
        if (method_exists($this, 'current')) {
            try {
                $c = $this->current();
                if (is_array($c) && isset($c['state'])) return (string) $c['state'];
            } catch (\Throwable $e) {
                // fall through
            }
        }
        return property_exists($this, 'state') ? (string) $this->state : 'off';
    }
}
