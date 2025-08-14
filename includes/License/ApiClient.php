<?php
namespace LS\License;

defined('ABSPATH') || exit;

/**
 * Tiny HTTP client for the licensing API.
 */
final class ApiClient {
    /** Replace with your licensing server URL (no trailing slash). */
    private const BASE = 'https://license.yourdomain.tld';
    private const UA   = 'LeadStream-Lic/1.0';

    /**
     * POST JSON to the API.
     * @return array{ok:bool,status:int,data:array,error?:string}
     */
    public function post(string $path, array $payload): array {
        $url  = self::BASE . $path;

        // Dev/staging domains auto-pass without consuming a seat
        $host = strtolower((string) wp_parse_url(home_url(), PHP_URL_HOST));
        if ($host === 'localhost' || str_ends_with($host, '.local') || str_ends_with($host, '.test')) {
            return ['ok'=>true,'status'=>200,'data'=>['status'=>'valid','expires'=>0]];
        }

        $args = [
            'timeout' => 10,
            'headers' => [
                'Content-Type' => 'application/json',
                'User-Agent'   => self::UA,
            ],
            'body'    => wp_json_encode(array_merge($payload, [
                'php'      => PHP_VERSION,
                'wp'       => get_bloginfo('version'),
                'domain'   => $host,
                'site'     => get_bloginfo('name'),
                'url'      => home_url('/'),
            ])),
        ];

        $res = wp_remote_post($url, $args);
        if (is_wp_error($res)) {
            return ['ok'=>false,'status'=>0,'data'=>[], 'error'=>$res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = json_decode((string) wp_remote_retrieve_body($res), true) ?: [];
        return ['ok' => $code >= 200 && $code < 300, 'status' => $code, 'data' => $body];
    }
}