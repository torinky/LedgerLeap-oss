<?php

namespace App\Services\Config;

class SynonymServiceConfig
{
    public bool $useSynonym = true;

    public bool $useTechnicalTerm = true;

    public function __construct(array $config = [])
    {
        $this->useSynonym = $config['useSynonym'] ?? $this->useSynonym;
        $this->useTechnicalTerm = $config['useTechnicalTerm'] ?? $this->useTechnicalTerm;
    }
}
