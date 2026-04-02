<?php

function r2_set_last_error($message) {
    $GLOBALS['R2_LAST_ERROR'] = (string)$message;
}

function r2_get_last_error() {
    return isset($GLOBALS['R2_LAST_ERROR']) ? (string)$GLOBALS['R2_LAST_ERROR'] : '';
}

function r2_load_config() {
    static $cfg = null;
    if ($cfg !== null) {
        return $cfg;
    }

    $cfg = [
        'enabled' => false,
        'account_id' => '',
        'bucket' => '',
        'region' => 'auto',
        'endpoint' => '',
        'public_base_url' => '',
        'access_key_id' => '',
        'secret_access_key' => ''
    ];

    $path = __DIR__ . '/../config/r2_config.php';
    if (is_file($path)) {
        $loaded = require $path;
        if (is_array($loaded)) {
            $cfg = array_merge($cfg, $loaded);
        }
    }

    return $cfg;
}

function r2_is_enabled() {
    $cfg = r2_load_config();
    return !empty($cfg['enabled']);
}

function r2_is_config_complete() {
    $cfg = r2_load_config();
    if (empty($cfg['enabled'])) return false;
    return !empty($cfg['account_id'])
        && !empty($cfg['bucket'])
        && !empty($cfg['endpoint'])
        && !empty($cfg['access_key_id'])
        && !empty($cfg['secret_access_key']);
}

function r2_get_sdk_client() {
    static $client = false;
    if ($client !== false) {
        return $client;
    }

    if (!r2_is_config_complete()) {
        r2_set_last_error('R2 config is incomplete.');
        $client = null;
        return $client;
    }

    $autoload = __DIR__ . '/../vendor/autoload.php';
    if (!is_file($autoload)) {
        $msg = 'R2: vendor/autoload.php not found. Run composer install.';
        error_log($msg);
        r2_set_last_error($msg);
        $client = null;
        return $client;
    }

    require_once $autoload;
    if (!class_exists('Aws\\S3\\S3Client')) {
        $msg = 'R2: Aws\\S3\\S3Client not found. Install aws/aws-sdk-php.';
        error_log($msg);
        r2_set_last_error($msg);
        $client = null;
        return $client;
    }

    $cfg = r2_load_config();
    try {
        $client = new Aws\S3\S3Client([
            'version' => 'latest',
            'region' => $cfg['region'] ?: 'auto',
            'endpoint' => $cfg['endpoint'],
            'use_path_style_endpoint' => true,
            'suppress_php_deprecation_warning' => true,
            'credentials' => [
                'key' => $cfg['access_key_id'],
                'secret' => $cfg['secret_access_key']
            ]
        ]);
        r2_set_last_error('');
    } catch (Throwable $e) {
        $msg = 'R2: failed to initialize S3 client: ' . $e->getMessage();
        error_log($msg);
        r2_set_last_error($msg);
        $client = null;
    }

    return $client;
}

function r2_build_object_key($studentId, $filename) {
    $safeName = preg_replace('/[^A-Za-z0-9_\-.]/', '_', (string)$filename);
    $year = date('Y');
    return 'journals/' . (int)$studentId . '/' . $year . '/' . time() . '_' . bin2hex(random_bytes(6)) . '_' . $safeName;
}

function r2_upload_file($localPath, $objectKey, $contentType = 'application/octet-stream') {
    if (!r2_is_enabled()) {
        return ['ok' => false, 'error' => 'R2 is disabled.'];
    }
    if (!r2_is_config_complete()) {
        return ['ok' => false, 'error' => 'R2 config is incomplete. Please set access key and secret.'];
    }

    $client = r2_get_sdk_client();
    if (!$client) {
        $detail = r2_get_last_error();
        if ($detail !== '') {
            return ['ok' => false, 'error' => $detail];
        }
        return ['ok' => false, 'error' => 'R2 client is not available.'];
    }

    $cfg = r2_load_config();
    try {
        $client->putObject([
            'Bucket' => $cfg['bucket'],
            'Key' => $objectKey,
            'SourceFile' => $localPath,
            'ContentType' => $contentType
        ]);
        return ['ok' => true, 'attachment' => 'r2://' . $objectKey];
    } catch (Exception $e) {
        error_log('R2 upload failed: ' . $e->getMessage());
        return ['ok' => false, 'error' => 'R2 upload failed.'];
    }
}

function r2_attachment_to_url($attachment, $ttlSeconds = 900) {
    $attachment = trim((string)$attachment);
    if ($attachment === '') return '';

    if (preg_match('/^https?:\/\//i', $attachment)) {
        return $attachment;
    }

    if (strpos($attachment, 'r2://') !== 0) {
        return '../' . ltrim($attachment, '/\\');
    }

    $objectKey = substr($attachment, 5);
    if ($objectKey === '') return '';

    $cfg = r2_load_config();

    // If a public base URL is configured, prefer direct public link.
    if (!empty($cfg['public_base_url'])) {
        $base = rtrim((string)$cfg['public_base_url'], '/');
        $parts = array_map('rawurlencode', explode('/', $objectKey));
        return $base . '/' . implode('/', $parts);
    }

    if (!r2_is_config_complete()) {
        return '';
    }

    $client = r2_get_sdk_client();
    if (!$client) {
        return '';
    }

    try {
        $cmd = $client->getCommand('GetObject', [
            'Bucket' => $cfg['bucket'],
            'Key' => $objectKey
        ]);
        $request = $client->createPresignedRequest($cmd, '+' . (int)$ttlSeconds . ' seconds');
        return (string)$request->getUri();
    } catch (Exception $e) {
        error_log('R2 sign failed: ' . $e->getMessage());
        return '';
    }
}
