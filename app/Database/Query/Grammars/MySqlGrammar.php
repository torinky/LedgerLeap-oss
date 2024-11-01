<?php

namespace App\Database\Query\Grammars;

use Illuminate\Support\Str;

/**
 * Class MysqlGrammar
 */
class MySqlGrammar extends \Illuminate\Database\Query\Grammars\MySqlGrammar
{
    /**
     * Wrap the given JSON path segment.
     *
     * @param string $segment
     * @return string
     */
    protected function wrapJsonPathSegment($segment)
    {
        if (preg_match('/(\[[^\]]+\])+$/', $segment, $parts)) {
            $key = Str::beforeLast($segment, $parts[0]);

            if (!empty($key)) {
                return '"' . $key . '"' . $parts[0];
            }

            return $parts[0];
        }

        //        配列に対応
        if (is_numeric($segment)) {
            return '[' . $segment . ']';
        }

        return '"' . $segment . '"';
    }
}
