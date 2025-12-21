<?php

declare(strict_types=1);

// LeadStream Pro smoke test: boots WordPress and runs the tracking pipeline.
// Usage (from plugin dir):
//   php tools/smoke-pipeline.php
// Optional:
//   WP_LOAD_PATH=C:\path\to\wp-load.php php tools/smoke-pipeline.php

$maybeWpLoad = getenv('WP_LOAD_PATH');
if (is_string($maybeWpLoad) && $maybeWpLoad !== '' && file_exists($maybeWpLoad)) {
	require_once $maybeWpLoad;
} else {
	// tools/ -> leadstream-pro/ -> plugins/ -> wp-content/ -> WP root
	$wpLoad = dirname(__DIR__, 4) . DIRECTORY_SEPARATOR . 'wp-load.php';
	if (!file_exists($wpLoad)) {
		fwrite(STDERR, "Could not find wp-load.php. Set WP_LOAD_PATH env var.\n");
		exit(1);
	}
	require_once $wpLoad;
}

// Ensure plugin code is loaded (in case plugin isn't active in this WP install).
$pluginMain = __DIR__ . DIRECTORY_SEPARATOR . '..' . DIRECTORY_SEPARATOR . 'leadstream-analytics-injector.php';
$pluginMain = realpath($pluginMain);
if (is_string($pluginMain) && file_exists($pluginMain)) {
	require_once $pluginMain;
}

if (!class_exists('LeadStream\\Pipeline\\TrackingPipelineRunner')) {
	fwrite(STDERR, "TrackingPipelineRunner not available. Is the plugin autoloader active?\n");
	exit(2);
}

$utm = array(
	'utm_source' => '',
	'utm_medium' => '',
	'utm_campaign' => '',
	'utm_term' => '',
	'utm_content' => '',
);
$clickIds = array('gclid' => '', 'fbclid' => '', 'msclkid' => '', 'ttclid' => '');
$device = array(
	'client_language' => '',
	'device_type' => '',
	'viewport_w' => 0,
	'viewport_h' => 0,
	'time_to_click_ms' => 0,
	'landing_page' => '',
);
$element = array(
	'element_type' => 'smoke',
	'element_class' => '',
	'element_id' => 'smoke',
	'original' => 'smoke',
);

$ctx = new LeadStream\DTO\ClickContext(
	new DateTimeImmutable('now'),
	'phone',
	'6400000000',
	'tel:6400000000',
	home_url('/'),
	'LeadStream Smoke',
	'',
	'smoke',
	'smoke',
	substr((string)($_SERVER['HTTP_USER_AGENT'] ?? 'cli'), 0, 512),
	'127.0.0.1',
	0,
	false,
	$utm,
	$clickIds,
	$device,
	$element,
	'ls_smoke_' . md5((string)microtime(true)),
	'',
	'',
	0,
	0
);

$envelope = new LeadStream\DTO\TrackingEnvelope(
	$ctx,
	'phone_click',
	$ctx->event_id(),
	0,
	array('smoke' => true)
);

$sent = LeadStream\Pipeline\TrackingPipelineRunner::run($envelope, 2);

echo "LeadStream pipeline smoke: allow_send=" . ($sent ? 'true' : 'false') . "\n";

echo "Done.\n";
