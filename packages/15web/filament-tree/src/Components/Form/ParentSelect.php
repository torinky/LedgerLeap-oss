<?php

declare(strict_types=1);

namespace Studio15\FilamentTree\Components\Form;

use Filament\Forms\Components\Select;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;
use Kalnoy\Nestedset\NestedSet;
use Kalnoy\Nestedset\QueryBuilder;
use RuntimeException;

/**
 * Parent select builder
 */
final readonly class ParentSelect
{
    public static function make(QueryBuilder $query): Select
    {
        $buildTitle = static function (Model $item) use ($query): string {
            $title = $item->getAttribute(
                $query->getModel()::class::getTreeLabelAttribute(),
            );

            $depth = $item->getAttribute('depth');
            if ($depth < 0) {
                throw new RuntimeException('The tree is corrupted, please Fix tree');
            }

            $prefix = Str::repeat(
                string: '--',
                times: $depth,
            );

            return trim("{$prefix} {$title}");
        };

        $options = static fn (): array => $query
            ->withDepth()
            ->defaultOrder()
            ->get()
            ->mapWithKeys(static fn (Model $item): array => [
                $item->getKey() => $buildTitle($item),
            ])
            ->all();

        return Select::make(NestedSet::PARENT_ID)
            ->label(__('filament-tree::translations.parent_node'))
            ->options($options)
            ->disableOptionWhen(static function (?Model $record, string $value): bool {
                $modelValue = (string) $record?->getKey();

                if ($modelValue === $value) {
                    return true;
                }

                if ($record !== null) {
                    return $record->descendants->contains($value);
                }

                return false;
            })
            ->searchable()
            ->nullable()
            ->native(false)
            ->preload(false);
    }
}
