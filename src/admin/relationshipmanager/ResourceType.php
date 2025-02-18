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

class RelationshipFactory
{
    public static function createRelationhip(string $attractor, string $attracted, \ldapMultiplexer $ldap): Relationship
    {
        $res = $ldap->cat($attracted, ['dn', 'objectClass']);

        if ($res) {
            $values = $ldap->fetch($res);
            foreach ($values['objctClass'] as $objectClass) {
                if (PosixGroupRelationship::LDAPBASECLASS == $objectClass) {
                    return new PosixGroupRelationship($attractor, $attracted, $ldap);
                }
                if (ObjectGroupRelationship::LDAPBASECLASS == $objectClass) {
                    return new ObjectGroupRelationship($attractor, $attracted, $ldap);
                }
            }
        }

        return new Divorced();
    }
}

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
        return $this->ldapBaseClass;
    }
    protected function relationshipIsValid(): bool
    {
        return $this->resourceType !== ResourceType::INVALID;
    }
    public abstract function disassociate(): void;
}

class PosixGroupRelationship extends Relationship
{
    public const LDAPBASECLASS = 'posixGroup';
    private string $uid = '';

    public function __construct(string $attractor, string $attracted, \ldapMultiplexer $ldap)
    {
        parent::__construct($attractor, $attracted, $ldap);
        $this->setResourceType(ResourceType::POSIX_GROUP);
        $this->fetchUid();
    }

    private function fetchUid(): void{
        $this->ldap->cat($this->attractor);
        if ($this->ldap->count() == 1) {
            $user = $this->ldap->fetch();
            $this->uid = $user['uid'][0];
        }
    }

    public function disassociate(): void
    {
        $this->ldap->cat($this->attracted);
        if ($this->ldap->count() == 1) {
            $group = $this->ldap->fetch();
            if (isset($group["memberUid"]) && in_array($this->attractor, $group['memberUid'])) {
                $this->ldap->cd($this->attracted);
                $this->ldap->rm(['memberUid' => $this->uid]);
                if (!$this->ldap->success()) {
                    \msg_dialog::display(_("LDAP error"), \msgPool::ldaperror($this->ldap->get_error(), $this->attracted, LDAP_MOD, __CLASS__));
                }
            }
        }
    }
}

class ObjectGroupRelationship extends Relationship
{
    public const LDAPBASECLASS = 'gosaGroupOfNames';

    public function __construct(string $attractor, string $attracted, \ldapMultiplexer $ldap)
    {
        parent::__construct($attractor, $attracted, $ldap);
        $this->setResourceType(ResourceType::OBJECT_GROUP);
    }

    public function disassociate(): void
    {
        $this->ldap->cat($this->attracted);
        if ($this->ldap->count() == 1) {
            $group = $this->ldap->fetch();
            if (isset($group["member"]) && in_array($this->attractor, $group['member'])) {
                $this->ldap->cd($this->attracted);
                $this->ldap->rm(['member' => $this->attractor]);
                if (!$this->ldap->success()) {
                    \msg_dialog::display(_("LDAP error"), \msgPool::ldaperror($this->ldap->get_error(), $this->attracted, LDAP_MOD, __CLASS__));
                }
            }
            // if (isset($group["memberUid"]) && in_array($this->uid, $group['memberUid'])) {
            //     $groupMemberName = 'memberUid';
            //     $removeMember = $this->uid;
            // }


        }
    }
}

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
}
