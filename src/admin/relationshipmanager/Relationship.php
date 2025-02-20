<?php

namespace GosaRelationshipManager\admin\relationshipmanager;

abstract class Relationship
{
    public const LDAPBASECLASS = '';
    protected ResourceType $resourceType = ResourceType::INVALID;
    protected \ldapMultiplexer $ldap;
    protected string $attracted;
    protected string $attractor;

    protected function __construct(string $attractor, string $attracted, \ldapMultiplexer $ldap)
    {
        $this->attractor = $attractor;
        $this->attracted = $attracted;
        $this->ldap = $ldap;
    }

    public function getResourceType(): ResourceType
    {
        return $this->resourceType;
    }
    public function setResourceType(ResourceType $resourceType)
    {
        $this->resourceType = $resourceType;
    }
    public function getLdapBaseClass(): string
    {
        return self::LDAPBASECLASS;
    }
    protected function relationshipIsValid(): bool
    {
        return $this->resourceType !== ResourceType::INVALID;
    }
    public abstract function disassociate(): void;
    public abstract function relationInfo(): string;
}
