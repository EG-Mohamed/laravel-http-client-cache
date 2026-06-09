<?php

use function Pest\Laravel\artisan;

it('publishes config under the http-client-cache-config tag', function () {
    $target = config_path('http-client-cache.php');
    @unlink($target);

    artisan('vendor:publish', ['--tag' => 'http-client-cache-config'])->run();

    expect(file_exists($target))->toBeTrue();
    @unlink($target);
});
