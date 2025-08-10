<?php
namespace LS\Repository;

class LinksRepository {
    public static function build_filters(array $args): array {
        global $wpdb;
        $where = ['1=1']; $params = [];
        $q = isset($args['q']) ? sanitize_text_field($args['q']) : '';
        $rt = isset($args['rt']) ? sanitize_text_field($args['rt']) : '';
        $from = isset($args['from']) ? sanitize_text_field($args['from']) : '';
        $to   = isset($args['to'])   ? sanitize_text_field($args['to'])   : '';
        if ($q) { $like = '%' . $wpdb->esc_like($q) . '%'; $where[] = '(l.slug LIKE %s OR l.target_url LIKE %s)'; $params[] = $like; $params[] = $like; }
        if (in_array($rt, ['301','302','307','308'], true)) { $where[] = 'l.redirect_type = %s'; $params[] = $rt; }
        if ($from) { $where[] = 'l.created_at >= %s'; $params[] = $from . ' 00:00:00'; }
        if ($to)   { $where[] = 'l.created_at <= %s'; $params[] = $to   . ' 23:59:59'; }
        return [implode(' AND ', $where), $params];
    }

    public static function fetch_with_counts(string $where_sql, array $params, int $limit, int $offset) {
        global $wpdb;
        $sql = "
            SELECT l.*, COUNT(c.id) as clicks, MAX(c.clicked_at) as last_click
            FROM {$wpdb->prefix}ls_links l
            LEFT JOIN {$wpdb->prefix}ls_clicks c ON l.id = c.link_id AND c.link_type = 'link'
            WHERE {$where_sql}
            GROUP BY l.id
            ORDER BY l.created_at DESC
            LIMIT %d OFFSET %d
        ";
        $query_params = array_merge($params, [$limit, $offset]);
        return !empty($query_params) ? $wpdb->get_results($wpdb->prepare($sql, $query_params)) : $wpdb->get_results(str_replace(['%d','%d'], [$limit,$offset], $sql));
    }

    public static function count(string $where_sql, array $params): int {
        global $wpdb;
        $count_sql = "SELECT COUNT(*) FROM {$wpdb->prefix}ls_links l WHERE {$where_sql}";
        return !empty($params) ? (int)$wpdb->get_var($wpdb->prepare($count_sql, $params)) : (int)$wpdb->get_var($count_sql);
    }
}
