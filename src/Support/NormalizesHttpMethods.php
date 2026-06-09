<?php

namespace MohamedSaid\HttpClientCache\Support;

use UnitEnum;

use function Illuminate\Support\enum_value;

trait NormalizesHttpMethods
{
    protected function normalizeMethods(array|string|UnitEnum|null $methods): array
    {
        if ($methods === null) {
            return [];
        }

        $methods = is_array($methods) ? $methods : [$methods];

        return array_values(array_filter(array_map(
            fn ($method): string => strtoupper((string) enum_value($method)),
            $methods
        )));
    }
}
