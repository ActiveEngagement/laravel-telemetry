<?php

namespace Tests\Fixtures;

use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Contracts\HasTools;
use Laravel\Ai\Promptable;

class SupportAgent implements Agent, HasTools
{
    use Promptable;

    public function instructions(): string
    {
        return 'Answer support questions.';
    }

    public function tools(): iterable
    {
        return [new LookupOrder];
    }
}
