<?php

namespace MohamedSaid\HttpClientCache\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;

class UncacheableResponseException extends RuntimeException implements ShouldntReport
{
    public function __construct(public array $payload)
    {
        parent::__construct('Response is not cacheable.');
    }
}
