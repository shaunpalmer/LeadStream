<?php
class KPI_Phone_Calls extends KPI_Provider {
    protected string $key   = 'calls';
    protected string $label = 'Phone Calls';

    public function value(array $range): int {
        return $this->data->total_for($range, 'calls');
    }

    public function state(): string {
        $enabled = (int) get_option('leadstream_phone_enabled', 0);
        $numbers = (array) get_option('leadstream_phone_numbers', []);
        if (!$enabled) return 'red';
        if (empty($numbers)) return 'orange';
        return 'green';
    }
}
