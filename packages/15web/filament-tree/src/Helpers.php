<?php

declare(strict_types=1);

namespace Studio15\FilamentTree;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NestedSet;

/**
 * Plugin helpers
 */
final class Helpers
{
    /**
     * @return non-empty-string
     */
    public static function treeKey(Model $model): string
    {
        $attributes = $model->only([
            $model->getKeyName(),
            NestedSet::LFT,
            NestedSet::RGT,
        ]);

        $attributes[] = $model->getAttribute(NestedSet::PARENT_ID) ?: -1;

        return implode(
            separator: '.',
            array: array_values($attributes),
        );
    }
}
