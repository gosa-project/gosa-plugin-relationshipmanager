<?php

namespace GosaRelationshipManager\admin\relationshipmanager;

class Divorced extends Relationship
{
    public const LDAPBASECLASS = '';

    public function __construct()
    {
        $this->setResourceType(ResourceType::INVALID);
    }

    public function disassociate(): void
    {
        return;
    }

    public function relationInfo(): string
    {
        return "";
    }
}
