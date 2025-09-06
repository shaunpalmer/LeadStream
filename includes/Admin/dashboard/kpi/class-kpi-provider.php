<?php
abstract class KPI_Provider {
    protected LS_Dashboard_Data $data;
    protected string $key;
    protected string $label;

    public function __construct(LS_Dashboard_Data $data) {
        $this->data = $data;
    }
    public function key(): string   { return $this->key; }
    public function label(): string { return $this->label; }

    /** Return integer value for current period */
    abstract public function value(array $range): int;

    /** Return ['abs'=>int,'pct'=>float,'prev'=>int] */
    public function delta(array $range): array {
        return $this->data->delta($range, $this->key());
    }

    /** Optional state color for tile (green/orange/red) */
    public function state(): string { return 'green'; }
}
