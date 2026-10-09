<?php

namespace Archipro\SilverstripeOAuth2\Discovery;

/**
 * Resolves a key-file path, including paths relative to the project root.
 */
final class KeyFilePath
{
    /**
     * Return an absolute path. An empty path stays empty. Absolute paths are unchanged.
     */
    public static function resolve(string $path): string
    {
        if ($path === '') {
            return '';
        }

        if (str_starts_with($path, '/') || preg_match('#^[A-Za-z]:[\\\\/]#', $path) === 1) {
            return $path;
        }

        return rtrim(BASE_PATH, '/') . '/' . ltrim($path, '/');
    }
}
