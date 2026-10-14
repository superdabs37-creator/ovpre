<?php
declare(strict_types=1);

require_once __DIR__ . '/../config.php';

function supabase_request(string $method, string $path, ?array $payload = null, ?string $accessToken = null, bool $admin = false): array
{
    $apiKey = $admin ? SUPABASE_SECRET_KEY : SUPABASE_ANON_KEY;
    if (SUPABASE_URL === '' || $apiKey === '') {
        throw new RuntimeException($admin
            ? 'Supabase server credentials are not configured.'
            : 'Supabase Auth is not configured. Ask the administrator to check the server settings.');
    }
    if (!function_exists('curl_init')) {
        throw new RuntimeException('The PHP cURL extension is required for Supabase Auth.');
    }

    $url = SUPABASE_URL . $path;
    $curl = curl_init($url);
    $headers = [
        'apikey: ' . $apiKey,
        'Authorization: Bearer ' . ($accessToken ?: $apiKey),
        'Accept: application/json',
    ];
    if ($payload !== null) {
        $headers[] = 'Content-Type: application/json';
    }
    curl_setopt_array($curl, [
        CURLOPT_CUSTOMREQUEST => strtoupper($method),
        CURLOPT_HTTPHEADER => $headers,
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8,
        CURLOPT_TIMEOUT => 20,
    ]);
    if ($payload !== null) {
        curl_setopt($curl, CURLOPT_POSTFIELDS, json_encode($payload, JSON_THROW_ON_ERROR));
    }
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($response === false) {
        throw new RuntimeException('Supabase Auth request failed: ' . $curlError);
    }
    $decoded = json_decode((string) $response, true);
    if ($status < 200 || $status >= 300) {
        $message = is_array($decoded)
            ? (string) ($decoded['msg'] ?? $decoded['message'] ?? $decoded['error_description'] ?? $decoded['error'] ?? 'Supabase request was rejected.')
            : 'Supabase request was rejected.';
        throw new RuntimeException($message);
    }
    return is_array($decoded) ? $decoded : [];
}

function supabase_auth_invite(string $email, array $metadata = []): array
{
    $redirect = rawurlencode(APP_BASE_URL . '/complete-invitation.php');
    return supabase_request('POST', '/auth/v1/invite?redirect_to=' . $redirect, ['email' => $email, 'data' => $metadata], null, true);
}

function supabase_auth_password_login(string $email, string $password): array
{
    return supabase_request('POST', '/auth/v1/token?grant_type=password', ['email' => $email, 'password' => $password]);
}

function supabase_auth_recover(string $email): void
{
    supabase_request('POST', '/auth/v1/recover?redirect_to=' . rawurlencode(APP_BASE_URL . '/reset-password.php'), ['email' => $email]);
}

function supabase_storage_upload(string $localFile, string $objectPath, string $contentType): void
{
    if (SUPABASE_URL === '' || SUPABASE_SECRET_KEY === '' || !function_exists('curl_init')) {
        throw new RuntimeException('Private file storage is not configured. Contact the administrator.');
    }
    $bucket = rawurlencode(SUPABASE_STORAGE_BUCKET);
    $path = implode('/', array_map('rawurlencode', explode('/', ltrim($objectPath, '/'))));
    $curl = curl_init(SUPABASE_URL . '/storage/v1/object/' . $bucket . '/' . $path);
    $headers = ['apikey: ' . SUPABASE_SECRET_KEY, 'Authorization: Bearer ' . SUPABASE_SECRET_KEY,
        'Content-Type: ' . $contentType, 'x-upsert: false'];
    curl_setopt_array($curl, [CURLOPT_POST => true, CURLOPT_HTTPHEADER => $headers,
        CURLOPT_POSTFIELDS => file_get_contents($localFile), CURLOPT_RETURNTRANSFER => true,
        CURLOPT_CONNECTTIMEOUT => 8, CURLOPT_TIMEOUT => 60]);
    $response = curl_exec($curl);
    $status = (int) curl_getinfo($curl, CURLINFO_RESPONSE_CODE);
    $curlError = curl_error($curl);
    curl_close($curl);
    if ($response === false || $status < 200 || $status >= 300) {
        error_log('Supabase Storage upload failed: HTTP ' . $status . ' ' . ($response ?: $curlError));
        throw new RuntimeException('The file could not be stored securely. Please try again.');
    }
}

function supabase_storage_signed_url(string $objectPath, int $expiresIn = 300): string
{
    $bucket = rawurlencode(SUPABASE_STORAGE_BUCKET);
    $path = implode('/', array_map('rawurlencode', explode('/', ltrim($objectPath, '/'))));
    $result = supabase_request('POST', '/storage/v1/object/sign/' . $bucket . '/' . $path,
        ['expiresIn' => max(60, min($expiresIn, 3600))], null, true);
    $signed = (string) ($result['signedURL'] ?? $result['signedUrl'] ?? '');
    if ($signed === '') {
        throw new RuntimeException('A secure file link could not be created.');
    }
    return str_starts_with($signed, 'http') ? $signed : SUPABASE_URL . '/storage/v1' . (str_starts_with($signed, '/') ? $signed : '/' . $signed);
}

function supabase_auth_user(string $accessToken): array
{
    return supabase_request('GET', '/auth/v1/user', null, $accessToken);
}

function supabase_auth_set_password(string $accessToken, string $password): array
{
    return supabase_request('PUT', '/auth/v1/user', ['password' => $password], $accessToken);
}

function supabase_auth_logout(string $accessToken): void
{
    try {
        supabase_request('POST', '/auth/v1/logout', [], $accessToken);
    } catch (Throwable $error) {
        error_log('Supabase logout failed: ' . $error->getMessage());
    }
}
