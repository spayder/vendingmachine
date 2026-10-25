<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\InvalidSelectorException;
use VendingMachine\Domain\Machine\Selector;

describe('fromString', function () {
    it('normalises the code to upper case', function (string $input, string $expected) {
        expect(Selector::fromString($input)->value())->toBe($expected);
    })->with([
        'already upper case' => ['WATER', 'WATER'],
        'lower case' => ['water', 'WATER'],
        'mixed case' => ['Juice', 'JUICE'],
        'with digits' => ['soda2', 'SODA2'],
        'with a hyphen' => ['cola-zero', 'COLA-ZERO'],
        'single character' => ['a', 'A'],
    ]);

    it('rejects codes that are not letters, digits and single hyphens', function (string $input) {
        expect(fn() => Selector::fromString($input))
            ->toThrow(InvalidSelectorException::class, 'Invalid selector');
    })->with([
        'empty' => [''],
        'only spaces' => ['   '],
        'surrounding space' => [' water'],
        'inner space' => ['wa ter'],
        'trailing newline' => ["water\n"],
        'symbol' => ['wa$ter'],
        'underscore' => ['wa_ter'],
        'leading hyphen' => ['-water'],
        'trailing hyphen' => ['water-'],
        'double hyphen' => ['cola--zero'],
        'non-ascii letters' => ['café'],
        'too long' => [str_repeat('A', Selector::MAX_LENGTH + 1)],
    ]);

    it('accepts the longest allowed code', function () {
        $longest = str_repeat('A', Selector::MAX_LENGTH);

        expect(Selector::fromString($longest)->value())->toBe($longest);
    });
});

describe('equals', function () {
    it('compares by value, ignoring the case it was written in', function () {
        expect(Selector::fromString('water')->equals(Selector::fromString('WATER')))->toBeTrue();
    });

    it('tells different codes apart', function () {
        expect(Selector::fromString('water')->equals(Selector::fromString('juice')))->toBeFalse();
    });
});
