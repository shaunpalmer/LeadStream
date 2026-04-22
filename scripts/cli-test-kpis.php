<?php
require __DIR__ . '/../includes/autoload.php';
$r = new \LeadStream\Admin\Dashboard\Data();
echo json_encode($r->kpis(), JSON_PRETTY_PRINT) . PHP_EOL;
