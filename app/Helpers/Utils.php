<?php


namespace App\Helpers;


use App\Models\User;
use Illuminate\Pagination\LengthAwarePaginator;

class Utils
{
    public static function formatPagination(LengthAwarePaginator $paginator, array $appends = []): array
    {
        $meta = $paginator->toArray();
        $data = $meta['data'] ?? [];

        if (isset($meta['data'])) unset($meta['data']);

        $meta['is_last_page'] = $meta['current_page'] == $meta['last_page'];
        $meta['has_more_pages'] = $meta['current_page'] < $meta['last_page'];

        return array_merge($appends, [
            'meta' => $meta,
            'data' => $data
        ]);
    }

    public static function paginate($collection, int $perPage = 20, array $appends = [])
    {
        $itemsPerPage = $perPage > 0 ? $perPage : 20;

        if (request()->has('per_page') && is_numeric(request()->input('per_page'))) {
            $itemsPerPage = (int)request()->input('per_page');
        }

        return self::formatPagination($collection->paginate($itemsPerPage), $appends);
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
