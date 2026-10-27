<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;

abstract class Controller
{
    /**
     * Page size for an admin collection, clamped.
     *
     * One definition rather than four: the clamp was copy-pasted into three
     * admin controllers and forgotten in the fourth, where `per_page=-1`
     * returned a 500 and a large value dumped the whole table.
     */
    protected function perPage(Request $request, int $default = 20, int $max = 100): int
    {
        return min($max, max(1, (int) $request->query('per_page', $default)));
    }
}
