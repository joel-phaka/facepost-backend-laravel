<?php


namespace App\Helpers;


use Illuminate\Pagination\LengthAwarePaginator;

class Utils
{
    public static function formatPagination(LengthAwarePaginator $paginator): array
    {
        $paginatorArr = $paginator->toArray();
        $data = $paginatorArr['data'];

        data_forget($paginatorArr, 'data');

        $meta = array_merge($paginatorArr, [
            'is_last_page' => $paginatorArr['current_page'],
            'has_more_pages' => $paginator->hasMorePages()
        ]);

        return [
            'meta' => $meta,
            'data' => $data
        ];
    }

    public static function paginate($collection, ?int $perPage = null, array $appends = []): array
    {
        $itemsPerPage = (intval($perPage) ?: intval(config('const.pagination.items_per_page'))) ?: 10;
        $maxItemsPerPages = intval(config('const.pagination.max_items_per_page')) ?: 20;

        if (!!request()->integer('per_page')) {
            $itemsPerPage = request()->integer('per_page');
        }

        if ($itemsPerPage > $maxItemsPerPages) {
            $itemsPerPage = $maxItemsPerPages;
        }

        $appends['per_page'] = $itemsPerPage;

        $collection = $collection->paginate($itemsPerPage);
        $collection->appends($appends);

        return self::formatPagination($collection);
    }

    public static function extractNonNullOrEmpty(array $arr): array
    {
        return array_filter($arr, function($v) {
            if (is_numeric($v) || is_array($v)) {
                return true;
            } else if (is_string($v)) {
                return !!trim($v);
            }

            return !empty($v);
        });
    }

    public static function baseUrl(?string $path = ''): string
    {
        $host = data_get($_SERVER, 'HTTP_X_FORWARDED_HOST') ?: data_get($_SERVER,'HTTP_HOST');
        $proto = null;

        if (!!data_get($_SERVER, 'HTTP_X_FORWARDED_PROTO')) {
            $proto = data_get($_SERVER, 'HTTP_X_FORWARDED_PROTO');
        } else {
            $isSecure = data_get($_SERVER, 'HTTP_X_FORWARDED_SSL') == 'on' ||
                        data_get($_SERVER, 'SERVER_PORT') == 443 ||
                        !!data_get($_SERVER, 'HTTPS') && (
                            strtolower(data_get($_SERVER, 'HTTPS')) == 'on' || strtolower(data_get($_SERVER, 'HTTPS')) != 'off'
                        );

            $proto = $isSecure ? "https" : "http";
        }

        $url = (!!$host ? ($proto . '://' . $host) : null);

        $path = trim($path ?? '');

        if (!!$path && !str_starts_with($path, '?')) {
            $path = preg_replace('/^\//', '/', $path);
            $path = preg_replace('/\/$/', '', $path);
            $path = (!!$path ? "/" . $path : null);
        }

        return $url . $path;
    }
}
