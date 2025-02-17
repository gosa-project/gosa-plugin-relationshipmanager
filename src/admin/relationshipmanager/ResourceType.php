<?php
/*
  This code is part of GOsa (https://gosa.gonicus.de)
  Copyright (C) 2024  Sebastian Sternfeld

  This program is free software; you can redistribute it and/or modify
  it under the terms of the GNU General Public License as published by
  the Free Software Foundation; either version 2 of the License, or
  (at your option) any later version.

  This program is distributed in the hope that it will be useful,
  but WITHOUT ANY WARRANTY; without even the implied warranty of
  MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
  GNU General Public License for more details.

  You should have received a copy of the GNU General Public License
  along with this program; if not, write to the Free Software
  Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
*/

namespace GosaRelationshipManager\admin\relationshipmanager;

enum ResourceType
{
    case INVALID;
    case POSIX_GROUP;
    case OBJECT_GROUP;
}

abstract class Relationship
{
    protected ResourceType $resourceType = ResourceType::INVALID;
    protected string $ldapBaseClass;
    protected \ldapMultiplexer $ldap;
    protected string $entry;

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
        return $this->ldapBaseClass;
    }
    protected function entryMatchesLdapClass(): bool
    {
        $res = $this->ldap->cat($this->entry, ['dn', 'objectClass']);

        if ($res) {
            $values = $this->ldap->fetch($res);
            if (isset($values['objectClass'])) {
                if (in_array($this->ldapBaseClass, $values['objctClass'])) {
                    return true;
                }
            }
        }

        return false;
    }
}

class PosixGroupRelationship extends Relationship
{
    protected string $ldapBaseClass = 'posixGroup';

    public function __construct(string $dn, \ldapMultiplexer $ldap)
    {
        $this->entry = $dn;
        $this->ldap = $ldap;

        if ($this->entryMatchesLdapClass()) {
            $this->setResourceType(ResourceType::POSIX_GROUP);
        }
    }
}

class ObjectGroupRelationship extends Relationship
{
    protected string $ldapBaseClass = 'gosaGroupOfNames';

    public function __construct(string $dn, \ldapMultiplexer $ldap)
    {
        $this->entry = $dn;
        $this->ldap = $ldap;
        if ($this->entryMatchesLdapClass()) {
            $this->setResourceType(ResourceType::OBJECT_GROUP);
        }
    }
}
