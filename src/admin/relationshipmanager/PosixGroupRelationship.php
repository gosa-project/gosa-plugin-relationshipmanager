<?php

namespace GosaRelationshipManager\admin\relationshipmanager;

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

    private function fetchUid(): void
    {
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

    public function relationInfo(): string
    {
        return sprintf(_("Relation between %s and %s"), $this->uid, $this->attracted);
    }
}
