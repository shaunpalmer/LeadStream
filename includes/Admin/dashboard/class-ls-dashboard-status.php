<?php
namespace LeadStream\Admin\Dashboard;


class Status {
public function flags(): array {
$phone_enabled = (int) get_option('leadstream_phone_enabled', 0);
$numbers = (array) get_option('leadstream_phone_numbers', []);
$js_enabled = (bool) get_option('leadstream_js_enabled', false);
$pretty_links = (bool) get_option('leadstream_pretty_links_enabled', false);


return [
'phone' => $phone_enabled && !empty($numbers) ? 'ok' : ($phone_enabled ? 'warn' : 'off'),
'js' => $js_enabled ? 'ok' : 'off',
'pretty' => $pretty_links ? 'ok' : 'off',
];
}
}