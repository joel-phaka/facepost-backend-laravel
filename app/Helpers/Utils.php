<?php


namespace App\Helpers;


use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class Utils
{
    public static function formatPagination(LengthAwarePaginator $paginator): array
    {
        $meta = $paginator->toArray();
        $data = $meta['data'] ?? [];

        if (isset($meta['data'])) unset($meta['data']);

        $meta['is_last_page'] = $meta['current_page'] == $meta['last_page'];
        $meta['has_more_pages'] = $meta['current_page'] < $meta['last_page'];
        $meta['has_data'] = count($data) > 0;
        $meta['is_empty'] = count($data) == 0;

        return [
            'meta' => $meta,
            'data' => $data
        ];
    }

    public static function paginate($collection, ?int $perPage = null, array $appends = []): array
    {
        $itemsPerPage = intval($perPage) ?: config('const.pagination.items_per_page');

        if (request()->has('per_page') && is_numeric(request()->input('per_page'))) {
            $itemsPerPage = intval(request()->input('per_page'));
        }

        if ($itemsPerPage < 1 || $itemsPerPage > config('const.pagination.max_items_per_page')) {
            $itemsPerPage = 10;
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

    public static function baseUrl($path = null): string
    {
        $host = data_get($_SERVER, 'HTTP_X_FORWARDED_HOST') ?: data_get($_SERVER,'HTTP_HOST');
        $proto = null;

        if (!!data_get($_SERVER, 'HTTP_X_FORWARDED_PROTO')) {
            $proto = $_SERVER['HTTP_X_FORWARDED_PROTO'];
        } else {
            $isSecure = data_get($_SERVER, 'HTTP_X_FORWARDED_SSL') == 'on' ||
                        data_get($_SERVER, 'SERVER_PORT') == 443 ||
                        !!data_get($_SERVER, 'HTTPS') && (
                            strtolower(data_get($_SERVER, 'HTTPS')) == 'on' || strtolower(data_get($_SERVER, 'HTTPS')) != 'off'
                        );

            $proto = $isSecure ? "https" : "http";
        }

        $url = (!!$host ? ($proto . '://' . $host) : null);

        $path = trim($path);

        if (!!$path && !str_starts_with($path, '?')) {
            $path = preg_replace('/^\//', '/', $path);
            $path = preg_replace('/\/$/', '', $path);
            $path = (!!$path ? "/" . $path : null);
        }

        return $url . $path;
    }
}
