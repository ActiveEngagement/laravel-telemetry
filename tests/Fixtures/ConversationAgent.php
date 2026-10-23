<?php

namespace Tests\Fixtures;

class ConversationAgent extends SupportAgent
{
    public function __construct(protected ?object $participant = null) {}

    public function conversationParticipant(): ?object
    {
        return $this->participant;
    }
}
