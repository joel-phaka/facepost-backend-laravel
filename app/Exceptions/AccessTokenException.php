<?php

namespace App\Exceptions;

use Illuminate\Auth\AuthenticationException;
use Illuminate\Contracts\Support\Arrayable;

class AccessTokenException extends AuthenticationException implements Arrayable
{
    private array $data;
    private string $reason;

    public function __construct(?array $data = null, ?string $reason = null)
    {
        parent::__construct('Unauthorized.');
        $this->code = 401;
        $this->data = $data ?? [];

        $error = $this->data['error'] ?? null;
        $error_description = $this->data['error_description'] ?? null;

        if (!empty($error_description)) {
            if (stripos($error_description, 'refresh token') !== false && stripos($error_description, 'invalid') !== false) {
                $this->reason = 'auth_invalid_refresh_token';
            } else if (stripos($error_description, 'credentials') !== false && stripos($error_description, 'invalid') !== false) {
                $this->reason = 'auth_invalid_credentials';
            }
        } else if (!empty($error)) {
            if (stripos($error, 'invalid_client') !== false) {
                $this->reason = 'auth_invalid_credentials';
            }
        }

        if (empty($this->reason)) {
            $this->reason = !$reason ?? 'auth_error' ;
        }
    }

    /**
     * @return string
     */
    public function getReason(): string
    {
        return $this->reason;
    }

    /**
     * @return array
     */
    public function getData(): array
    {
        return $this->data;
    }

    public function toArray(): array
    {
        return [
            'message' => $this->getMessage(),
            'error_code' => $this->getReason(),
        ];
    }
}
