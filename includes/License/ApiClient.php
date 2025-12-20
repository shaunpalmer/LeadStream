<?php
namespace LS\License;

defined('ABSPATH') || exit;

/**
 * Tiny HTTP client for license server
 * Replace BASE with your endpoint host.
 */
final class ApiClient {
    private const BASE = 'https://your-license-host.tld'; // no trailing slash
    private const UA   = 'LeadStream-Lic/1.0';

    /** @return array{ok:bool,status:int,data:array,error?:string} */
    public function post(string $path, array $payload): array {
        $url  = self::BASE . $path;

        // Allow offline dev domains through without erroring
        $host = wp_parse_url(home_url(), PHP_URL_HOST) ?: '';
        if (preg_match('/(localhost|\.local|\.test|\.invalid)$/', $host)) {
            return ['ok'=>true,'status'=>200,'data'=>['status'=>'valid','expires'=>0]];
        }

        $args = [
            'timeout' => 10,
            'headers' => ['Content-Type'=>'application/json','User-Agent'=>self::UA],
            'body'    => wp_json_encode(array_merge($payload, [
                'wp'      => get_bloginfo('version'),
                'php'     => PHP_VERSION,
            ])),
        ];
        $res = wp_remote_post($url, $args);

        if (is_wp_error($res)) {
            return ['ok'=>false,'status'=>0,'data'=>[], 'error'=>$res->get_error_message()];
        }
        $code = (int) wp_remote_retrieve_response_code($res);
        $body = json_decode((string) wp_remote_retrieve_body($res), true) ?: [];
        return ['ok'=> $code >= 200 && $code < 300, 'status'=>$code, 'data'=>$body];
    }
}
