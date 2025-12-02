<?php

namespace App\Support;

use Illuminate\Contracts\Pagination\LengthAwarePaginator;

final class PaginationHelper
{
    public static function meta(LengthAwarePaginator $paginator): array
    {
        return [
            'currentPage' => $paginator->currentPage(),
            'lastPage' => $paginator->lastPage(),
            'perPage' => $paginator->perPage(),
            'total' => $paginator->total(),
        ];
    }

    public static function empty(int $perPage = 10): array
    {
        return [
            'currentPage' => 1,
            'lastPage' => 1,
            'perPage' => $perPage,
            'total' => 0,
        ];
    }
}
