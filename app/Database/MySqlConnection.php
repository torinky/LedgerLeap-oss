<?php

namespace App\Database;

use App\Database\Query\Grammars\MySqlGrammar;

class MySqlConnection extends \Illuminate\Database\MySqlConnection
{
    /**
     * Get the default query grammar instance.
     *
     * @return App\Database\Query\Grammars\MySqlGrammar
     */
    protected function getDefaultQueryGrammar()
    {
        return $this->withTablePrefix(new MySqlGrammar);
    }
}
