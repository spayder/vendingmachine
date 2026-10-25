<?php

declare(strict_types=1);

use VendingMachine\Domain\Machine\InvalidMachineId;
use VendingMachine\Domain\Machine\MachineId;

describe('fromString', function () {
    it('keeps the identifier it was given', function (string $value) {
        expect(MachineId::fromString($value)->value())->toBe($value);
    })->with([
        'default' => ['default'],
        'with digits' => ['machine-42'],
        'uuid' => ['3f2504e0-4f89-11d3-9a0c-0305e82c3301'],
        'underscore' => ['lobby_east'],
    ]);

    it('rejects identifiers that are empty, padded or contain odd characters', function (string $value) {
        expect(fn() => MachineId::fromString($value))
            ->toThrow(InvalidMachineId::class, 'Invalid machine id');
    })->with([
        'empty' => [''],
        'only spaces' => ['   '],
        'surrounding space' => [' default'],
        'inner space' => ['my machine'],
        'slash' => ['a/b'],
        'trailing newline' => ["default\n"],
        'too long' => [str_repeat('a', MachineId::MAX_LENGTH + 1)],
    ]);

    it('accepts the longest allowed identifier', function () {
        $longest = str_repeat('a', MachineId::MAX_LENGTH);

        expect(MachineId::fromString($longest)->value())->toBe($longest);
    });
});

describe('equals', function () {
    it('compares by value', function () {
        expect(MachineId::fromString('default')->equals(MachineId::fromString('default')))->toBeTrue()
            ->and(MachineId::fromString('default')->equals(MachineId::fromString('other')))->toBeFalse();
    });
});
