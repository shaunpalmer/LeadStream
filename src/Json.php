<?php
namespace LicSrv;

final class Json {
    public static function ok(array $data = []): void {
        header('Content-Type: application/json');
        http_response_code(200);
        echo json_encode($data, JSON_UNESCAPED_SLASHES);
        exit;
    }
    public static function bad(string $msg, int $code = 400): void {
        header('Content-Type: application/json');
        http_response_code($code);
        echo json_encode(['error'=>$msg], JSON_UNESCAPED_SLASHES);
        exit;
    }
}