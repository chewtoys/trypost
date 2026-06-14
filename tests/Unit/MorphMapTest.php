<?php

declare(strict_types=1);

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;

test('every Eloquent model is registered in the morph map', function () {
    $mapped = array_values(Relation::morphMap());

    $missing = collect(glob(app_path('Models/*.php')))
        ->map(fn (string $file): string => 'App\\Models\\'.pathinfo($file, PATHINFO_FILENAME))
        ->filter(fn (string $class): bool => class_exists($class)
            && is_subclass_of($class, Model::class)
            && ! (new ReflectionClass($class))->isAbstract())
        ->reject(fn (string $class): bool => in_array($class, $mapped, true))
        ->values()
        ->all();

    expect($missing)->toBe([]);
});
