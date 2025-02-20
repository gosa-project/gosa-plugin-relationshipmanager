<?php

namespace GosaRelationshipManager\admin\relationshipmanager;

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
        }
    }

    public function relationInfo(): string
    {
        return sprintf(_("Relation between %s and %s"), [$this->attractor, $this->attracted]);
    }
}
