<?php

declare(strict_types=1);

namespace Studio15\FilamentTree;

use Illuminate\Database\Eloquent\Model;
use Kalnoy\Nestedset\NestedSet;

/**
 * Move node on tree
 */
final readonly class MoveItem
{
    /**
     * @param non-negative-int|null $parent
     * @param non-negative-int $from
     * @param non-negative-int $to
     */
    public function __invoke(Model $node, ?int $parent, int $from, int $to): void
    {
        if ($parent === $node->getAttribute(NestedSet::PARENT_ID)) {
            $this->moveItem($node, $from, $to);

            return;
        }

        if ($parent === null) {
            $this->moveToRoot(
                node: $node,
                position: $to,
            );

            return;
        }

        /** @var Model $parentNode */
        $parentNode = $node->query()->findOrFail($parent);

        $parentNode->prependNode($node);
        if ($to > 0) {
            $node->down($to);
        }
    }

    private function moveItem(Model $node, int $from, int $to): void
    {
        $shift = $from - $to;
        if ($shift === 0) {
            return;
        }

        if ($from > $to) {
            $node->up($shift);

            return;
        }

        $node->down($shift);
    }

    private function moveToRoot(Model $node, int $position): void
    {
        $node->saveAsRoot();

        $siblingsCount = $node->refresh()->siblings()->count();
        $shift = $siblingsCount - $position;

        $node->up($shift);
    }
}
