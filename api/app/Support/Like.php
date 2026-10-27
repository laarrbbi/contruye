<?php

namespace App\Support;

/**
 * A LIKE pattern that matches the typed text literally. Unescaped, an admin
 * searching "100%" or "a_b" got wildcards: "%" alone listed every row.
 */
final class Like
{
    public static function contains(string $term): string
    {
        return '%'.addcslashes($term, '%_\\').'%';
    }
}
