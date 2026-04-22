<?php
namespace LeadStream\Admin;


class Admin {
public function init(): void {
add_action('admin_menu', [$this, 'menu']);
}


public function menu(): void {
add_menu_page(
'LeadStream', 'LeadStream', 'manage_options',
'leadstream-analytics-injector',
[$this, 'render_page'], 'dashicons-chart-area', 62
);
}


public function render_page(): void {
$tab = isset($_GET['tab']) ? sanitize_key($_GET['tab']) : 'dashboard'; // default first
$tabs = (new Tabs())->all();
if (!isset($tabs[$tab])) $tab = 'dashboard';


echo '<div class="wrap"><h1>LeadStream</h1>';
echo '<nav class="nav-tab-wrapper">';
foreach ($tabs as $slug => $t) {
$active = ($slug === $tab) ? ' nav-tab-active' : '';
$url = admin_url('admin.php?page=leadstream-analytics-injector&tab='.$slug);
printf('<a href="%s" class="nav-tab%s">%s %s</a>', $url, $active, $t['icon'], esc_html($t['label']));
}
echo '</nav>';


// Render selected tab
call_user_func($tabs[$tab]['cb']);


echo '</div>';
}
}