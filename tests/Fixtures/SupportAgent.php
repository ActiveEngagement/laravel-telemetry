<?php

namespace Tests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

class SupportAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        return 'Answer support questions.';
    }
}
