<?php
require __DIR__ . '/../includes/autoload.php';
$classes = [
    'LeadStream\\Admin\\Dashboard\\Dashboard',
    'LeadStream\\Admin\\Dashboard\\Factory',
    'LeadStream\\Admin\\Dashboard\\Data',
    'LeadStream\\Admin\\Dashboard\\Status',
    'LeadStream\\Admin\\Dashboard\\Render',
];
foreach ($classes as $c) {
    printf("%s => %s\n", $c, class_exists($c) ? 'yes' : 'no');
}
