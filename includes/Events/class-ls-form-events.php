<?php
namespace LS\Events;

defined('ABSPATH') || exit;

/**
 * LS_Form_Events
 * Capture successful form submissions from common plugins and
 * record a single row in wp_ls_events (event_type=form_submit).
 *
 * No PII is stored: we only keep form id/name, page url, referrer, UA.
 */
class LS_Form_Events {
    private string $tbl;

    public static function init(): void {
        $o = new self();
        $o->hook();
    }

    public function __construct() {
        global $wpdb;
        $this->tbl = $wpdb->prefix . 'ls_events';
    }

    public function hook(): void {
        // Ensure table exists (safe no-op if it does)
        add_action('admin_init', [$this, 'maybe_create_table']);

        // Contact Form 7
        add_action('wpcf7_mail_sent', function ($contact_form) {
            $id   = method_exists($contact_form, 'id')    ? (string) $contact_form->id()   : '';
            $name = method_exists($contact_form, 'title') ? (string) $contact_form->title(): '';
            $this->record('CF7', $id, $name);
        }, 10, 1);

        // WPForms
        add_action('wpforms_process_complete', function ($fields, $entry, $form_data) {
            $id   = isset($form_data['id']) ? (string) $form_data['id'] : '';
            $name = isset($form_data['settings']['form_title']) ? (string) $form_data['settings']['form_title'] : '';
            $this->record('WPForms', $id, $name);
        }, 10, 3);

        // Gravity Forms
        add_action('gform_after_submission', function ($entry, $form) {
            $id   = isset($form['id']) ? (string) $form['id'] : '';
            $name = isset($form['title']) ? (string) $form['title'] : '';
            $this->record('Gravity', $id, $name);
        }, 10, 2);

        // Ninja Forms
        add_action('ninja_forms_after_submission', function ($form_data) {
            $id   = isset($form_data['id']) ? (string) $form_data['id'] : '';
            $name = isset($form_data['settings']['title']) ? (string) $form_data['settings']['title'] : '';
            $this->record('Ninja', $id, $name);
        }, 10, 1);
    }

    /** Create ls_events if missing */
    public function maybe_create_table(): void {
        global $wpdb;
        $charset = $wpdb->get_charset_collate();
        $sql = "CREATE TABLE IF NOT EXISTS {$this->tbl} (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT,
            event_type VARCHAR(64) NOT NULL,
            action     VARCHAR(128) DEFAULT '' NOT NULL,
            label      VARCHAR(191) DEFAULT '' NOT NULL,
            value_int  BIGINT DEFAULT 0 NOT NULL,
            page_url   TEXT NULL,
            referrer   TEXT NULL,
            ua         TEXT NULL,
            meta       LONGTEXT NULL,
            created_at DATETIME NOT NULL,
            PRIMARY KEY (id),
            KEY event_type (event_type),
            KEY created_at (created_at)
        ) {$charset};";
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta($sql);
    }

    /** Write one sanitized row */
    private function record(string $source, string $form_id, string $form_name): void {
        global $wpdb;
        // De-dupe: if identical form on same page within 2 seconds, skip
        $key = md5($source.'|'.$form_id.'|'.(wp_parse_url($_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH) ?: '').'|'.(int)(time()/2));
        if (isset($GLOBALS['__LS_FORM_DUPE__'][$key])) return;
        $GLOBALS['__LS_FORM_DUPE__'][$key] = true;

        $wpdb->insert(
            $this->tbl,
            [
                'event_type' => 'form_submit',
                'action'     => sanitize_text_field($source),
                'label'      => sanitize_text_field(trim($form_id.' '.$form_name)),
                'value_int'  => 1,
                'page_url'   => esc_url_raw(home_url(add_query_arg([]))),
                'referrer'   => esc_url_raw(wp_get_referer() ?: ''),
                'ua'         => substr((string)($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 512),
                'meta'       => null,
                'created_at' => current_time('mysql'),
            ],
            ['%s','%s','%s','%d','%s','%s','%s','%s','%s']
        );
    }
}
