<?php
namespace LeadStream\Admin;


class Tabs {
public function all(): array {
return [
'dashboard' => [ 'label' => 'Dashboard', 'icon' => '📊', 'cb' => [new Dashboard\Dashboard(), 'render'] ],
'javascript' => [ 'label' => 'JavaScript Injection', 'icon' => '📝', 'cb' => [\LeadStream\Settings\Settings::class, 'render_js'] ],
'utm' => [ 'label' => 'UTM Builder', 'icon' => '🔗', 'cb' => [\LeadStream\Settings\Settings::class, 'render_utm'] ],
'links' => [ 'label' => 'Pretty Links', 'icon' => '🎯', 'cb' => [\LeadStream\Settings\Settings::class, 'render_links'] ],
'phone' => [ 'label' => 'Phone Tracking', 'icon' => '📞', 'cb' => [\LeadStream\Phone\Phone_Service::class, 'render_admin'] ],
'license' => [ 'label' => 'License', 'icon' => '🔑', 'cb' => [\LeadStream\Settings\Settings::class, 'render_license'] ],
];
}
}