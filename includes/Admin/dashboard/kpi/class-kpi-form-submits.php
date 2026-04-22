<?php
class KPI_Form_Submits extends KPI_Provider {
    protected string $key   = 'forms';
    protected string $label = 'Form Submits';

    public function value(array $range): int {
        return $this->data->total_for($range, 'forms');
    }
}
