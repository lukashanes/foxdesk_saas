<?php
/**
 * Storage abstraction for local disk and Cloudflare R2.
 */

function storage_env_or_constant(string $name, $default = '')
{
    if (defined($name)) {
        return constant($name);
    }

    $value = getenv($name);
    return $value !== false ? $value : $default;
}

function storage_driver(): string
{
    return strtolower(trim((string) storage_env_or_constant('STORAGE_DRIVER', 'local')));
}

function storage_is_r2_enabled(): bool
{
    return storage_driver() === 'r2';
}

function storage_r2_endpoint(): string
{
    $endpoint = trim((string) storage_env_or_constant('R2_ENDPOINT', ''));
    if ($endpoint === '') {
        $account_id = trim((string) storage_env_or_constant('CLOUDFLARE_ACCOUNT_ID', ''));
        if ($account_id !== '') {
            $endpoint = 'https://' . $account_id . '.r2.cloudflarestorage.com';
        }
    }
    return rtrim($endpoint, '/');
}

function storage_r2_bucket(): string
{
    return trim((string) storage_env_or_constant('R2_BUCKET', ''));
}

function storage_r2_access_key(): string
{
    return trim((string) storage_env_or_constant('R2_ACCESS_KEY_ID', ''));
}

function storage_r2_secret_key(): string
{
    return trim((string) storage_env_or_constant('R2_SECRET_ACCESS_KEY', ''));
}

function storage_r2_assert_configured(): void
{
    if (storage_r2_endpoint() === '' || storage_r2_bucket() === '' || storage_r2_access_key() === '' || storage_r2_secret_key() === '') {
        throw new RuntimeException('Cloudflare R2 storage is not fully configured.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('PHP cURL extension is required for Cloudflare R2 storage.');
    }
}

function storage_object_key(int $tenant_id, string $relative_path): string
{
    $relative_path = ltrim(str_replace('\\', '/', $relative_path), '/');
    return 'tenants/' . max(1, $tenant_id) . '/' . $relative_path;
}

function storage_r2_uri_encode(string $path): string
{
    return implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
}

function storage_r2_signed_headers(string $method, string $object_key, string $payload_hash, array $extra_headers = []): array
{
    storage_r2_assert_configured();

    $endpoint = storage_r2_endpoint();
    $host = (string) parse_url($endpoint, PHP_URL_HOST);
    $bucket = storage_r2_bucket();
    $access_key = storage_r2_access_key();
    $secret_key = storage_r2_secret_key();
    $region = 'auto';
    $service = 's3';
    $amz_date = gmdate('Ymd\THis\Z');
    $date_stamp = gmdate('Ymd');
    $uri = '/' . storage_r2_uri_encode($bucket) . '/' . storage_r2_uri_encode($object_key);

    $headers = array_merge([
        'host' => $host,
        'x-amz-content-sha256' => $payload_hash,
        'x-amz-date' => $amz_date,
    ], $extra_headers);

    ksort($headers);
    $canonical_headers = '';
    foreach ($headers as $name => $value) {
        $canonical_headers .= strtolower($name) . ':' . trim((string) $value) . "\n";
    }
    $signed_headers = implode(';', array_map('strtolower', array_keys($headers)));
    $canonical_request = strtoupper($method) . "\n"
        . $uri . "\n"
        . "\n"
        . $canonical_headers . "\n"
        . $signed_headers . "\n"
        . $payload_hash;

    $credential_scope = $date_stamp . '/' . $region . '/' . $service . '/aws4_request';
    $string_to_sign = 'AWS4-HMAC-SHA256' . "\n"
        . $amz_date . "\n"
        . $credential_scope . "\n"
        . hash('sha256', $canonical_request);

    $k_date = hash_hmac('sha256', $date_stamp, 'AWS4' . $secret_key, true);
    $k_region = hash_hmac('sha256', $region, $k_date, true);
    $k_service = hash_hmac('sha256', $service, $k_region, true);
    $k_signing = hash_hmac('sha256', 'aws4_request', $k_service, true);
    $signature = hash_hmac('sha256', $string_to_sign, $k_signing);

    $headers['authorization'] = 'AWS4-HMAC-SHA256 Credential=' . $access_key . '/' . $credential_scope
        . ', SignedHeaders=' . $signed_headers
        . ', Signature=' . $signature;

    $http_headers = [];
    foreach ($headers as $name => $value) {
        $http_headers[] = $name . ': ' . $value;
    }

    return [$endpoint . $uri, $http_headers];
}

function storage_r2_request(string $method, string $object_key, ?string $body = null, string $content_type = 'application/octet-stream'): array
{
    $payload_hash = $body === null ? hash('sha256', '') : hash('sha256', $body);
    $extra_headers = [];
    if ($method === 'PUT') {
        $extra_headers['content-type'] = $content_type !== '' ? $content_type : 'application/octet-stream';
    }

    [$url, $headers] = storage_r2_signed_headers($method, $object_key, $payload_hash, $extra_headers);
    $ch = curl_init($url);
    $options = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CUSTOMREQUEST => $method,
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_HTTP_VERSION => CURL_HTTP_VERSION_1_1,
        CURLOPT_TIMEOUT => 60,
    ];
    if ($body !== null) {
        $options[CURLOPT_POSTFIELDS] = $body;
    }
    curl_setopt_array($ch, $options);
    $response = curl_exec($ch);
    $status = (int) curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $error = curl_error($ch);
    curl_close($ch);

    if ($response === false) {
        throw new RuntimeException('R2 request failed: ' . $error);
    }
    if ($status < 200 || $status >= 300) {
        throw new RuntimeException('R2 request failed with HTTP ' . $status . ': ' . substr((string) $response, 0, 300));
    }

    return ['status' => $status, 'body' => (string) $response];
}

function storage_store_file(string $absolute_path, string $relative_path, string $mime_type = '', ?int $tenant_id = null): array
{
    if (!storage_is_r2_enabled()) {
        return ['driver' => 'local', 'key' => ltrim(str_replace('\\', '/', $relative_path), '/'), 'bucket' => ''];
    }

    if (!is_file($absolute_path)) {
        throw new RuntimeException('Storage source file does not exist.');
    }

    $tenant_id = $tenant_id ?: (function_exists('current_tenant_id') ? current_tenant_id() : 1);
    $key = storage_object_key((int) $tenant_id, $relative_path);
    $body = file_get_contents($absolute_path);
    if ($body === false) {
        throw new RuntimeException('Unable to read file for R2 upload.');
    }

    storage_r2_request('PUT', $key, $body, $mime_type !== '' ? $mime_type : 'application/octet-stream');
    @unlink($absolute_path);

    return ['driver' => 'r2', 'key' => $key, 'bucket' => storage_r2_bucket()];
}

function storage_read_object(array $attachment): ?string
{
    if (($attachment['storage_driver'] ?? '') !== 'r2') {
        return null;
    }

    $key = trim((string) ($attachment['storage_key'] ?? ''));
    if ($key === '') {
        return null;
    }

    return storage_r2_request('GET', $key)['body'];
}

function storage_delete_object(array $attachment): bool
{
    if (($attachment['storage_driver'] ?? '') !== 'r2') {
        return false;
    }

    $key = trim((string) ($attachment['storage_key'] ?? ''));
    if ($key === '') {
        return false;
    }

    storage_r2_request('DELETE', $key);
    return true;
}

function storage_r2_healthcheck(int $tenant_id = 1, bool $keep = false): array
{
    $tenant_id = max(1, $tenant_id);
    $result = [
        'ok' => false,
        'driver' => storage_driver(),
        'bucket' => storage_r2_bucket(),
        'endpoint_configured' => storage_r2_endpoint() !== '',
        'access_key_configured' => storage_r2_access_key() !== '',
        'secret_key_configured' => storage_r2_secret_key() !== '',
        'tenant_id' => $tenant_id,
        'object_key' => null,
        'tenant_prefixed' => false,
        'put' => false,
        'read' => false,
        'deleted' => false,
        'kept' => $keep,
        'error' => null,
    ];

    try {
        storage_r2_assert_configured();

        $payload = 'FoxDesk R2 health ' . gmdate('c') . ' ' . bin2hex(random_bytes(8));
        $object_key = storage_object_key(
            $tenant_id,
            'healthchecks/r2-health-' . gmdate('Ymd-His') . '-' . bin2hex(random_bytes(4)) . '.txt'
        );
        $result['object_key'] = $object_key;
        $result['tenant_prefixed'] = str_starts_with($object_key, 'tenants/' . $tenant_id . '/');
        if (!$result['tenant_prefixed']) {
            throw new RuntimeException('R2 health object key is not tenant-prefixed.');
        }

        storage_r2_request('PUT', $object_key, $payload, 'text/plain; charset=UTF-8');
        $result['put'] = true;

        $read = storage_r2_request('GET', $object_key)['body'];
        $result['read'] = hash_equals($payload, $read);
        if (!$result['read']) {
            throw new RuntimeException('R2 health read content did not match uploaded content.');
        }

        if (!$keep) {
            storage_r2_request('DELETE', $object_key);
            $result['deleted'] = true;
        }

        $result['ok'] = true;
    } catch (Throwable $e) {
        $result['error'] = $e->getMessage();
    }

    return $result;
}
