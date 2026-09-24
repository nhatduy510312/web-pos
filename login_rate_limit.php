<?php
function loginRateLimitKey(string $username): string { return hash('sha256', ($_SERVER['REMOTE_ADDR'] ?? '') . '|' . strtolower($username)); }
function withLoginRateLimit(callable $callback) {
    $handle = fopen(rtrim(sys_get_temp_dir(), DIRECTORY_SEPARATOR) . DIRECTORY_SEPARATOR . 'pos-login-rate-limit.json', 'c+');
    if (!$handle || !flock($handle, LOCK_EX)) return $callback([]);
    $records = json_decode(stream_get_contents($handle) ?: '{}', true); $records = is_array($records) ? $records : [];
    $now = time(); foreach ($records as $key => $record) if (($record['last'] ?? 0) < $now - 900) unset($records[$key]);
    $result = $callback($records); ftruncate($handle, 0); rewind($handle); fwrite($handle, json_encode($records)); fflush($handle); flock($handle, LOCK_UN); fclose($handle);
    return $result;
}
function loginIsRateLimited(string $username): bool { return withLoginRateLimit(function ($records) use ($username) { return (($records[loginRateLimitKey($username)]['count'] ?? 0) >= 5); }); }
function recordLoginFailure(string $username): void { withLoginRateLimit(function (&$records) use ($username) { $key = loginRateLimitKey($username); $records[$key] = ['count' => ($records[$key]['count'] ?? 0) + 1, 'last' => time(), 'account' => hash('sha256', strtolower($username))]; }); }
function clearLoginFailures(string $username): void { withLoginRateLimit(function (&$records) use ($username) { unset($records[loginRateLimitKey($username)]); }); }
function clearLoginFailuresForAccount(string $username): int { return (int)withLoginRateLimit(function (&$records) use ($username) { $account = hash('sha256', strtolower($username)); $removed = 0; foreach ($records as $key => $record) { if (!isset($record['account']) || hash_equals((string)$record['account'], $account)) { unset($records[$key]); $removed++; } } return $removed; }); }
