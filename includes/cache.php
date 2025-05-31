<?php
// Create includes/cache.php
class SimpleCache
{
    private static $cache = [];

    public static function get($key)
    {
        return isset(self::$cache[$key]) ? self::$cache[$key] : null;
    }

    public static function set($key, $value, $ttl = 300)
    {
        self::$cache[$key] = [
            'data' => $value,
            'expires' => time() + $ttl
        ];
    }

    public static function isValid($key)
    {
        return isset(self::$cache[$key]) &&
            self::$cache[$key]['expires'] > time();
    }
}

/**
 * Generates a versioned asset URL for cache busting.
 * Appends the last modification time of the file as a query string.
 *
 * @param string $file_path The path to the asset file relative to the document root.
 * @return string The asset URL with a version query string, or original path if file not found.
 */
function asset_version($file_path)
{
    // Construct the full server path to the file
    // Ensure $file_path starts with a '/' if it's relative from web root, or adjust as needed.
    $server_file_path = $_SERVER['DOCUMENT_ROOT'] . '/Dentaly/' . ltrim($file_path, '/');

    if (file_exists($server_file_path)) {
        $version = filemtime($server_file_path);
        return htmlspecialchars($file_path) . '?v=' . $version;
    }
    // Fallback if file not found (should not happen in production)
    return htmlspecialchars($file_path);
}