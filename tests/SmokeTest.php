<?php

declare(strict_types=1);

it('runs on the expected environment', function () {
    expect(PHP_VERSION_ID)->toBeGreaterThanOrEqual(80500);
});
