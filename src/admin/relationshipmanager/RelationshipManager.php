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

use \plugin as Plugin;
use \msgPool as msgPool;
use \log as log;
use \msg_dialog as msg_dialog;
use \sortableListing as sortableListing;
use \stats as stats;
use \LDAP as LDAP;
use \session as session;
use \GosaRelationshipManager\admin\relationshipmanager\groupRelationshipSelect\GroupRelationshipSelect as GroupRelationshipSelect;

class RelationshipManager extends Plugin
{
    // Definitions
    public $plHeadline = "Relationship manager";
    public $plDescription = "Manage user relationship";
    public $plIcon = "";
    public $matIcon = "groups";

    // Class attributes
    public $view_logged = false;
    public $uid = "";
    public $groupRelationSelect;
    public sortableListing $list;
    public $listData;
    public $initTime;
    public $plugin;
    public $addToPosixGroups = [];
    public $addToObjectgroups = [];
    public GroupType $currentGroupType;

    // attribute list for save action
    public $objectClasses = ["gosaGroupOfNames", "posixGroup"];
    public $objectList = [];

    function __construct(&$config, $dn = NULL, $parent = NULL)
    {
        parent::__construct($config, $dn, $parent);

        $this->initTime = microtime(TRUE);
        $this->uid = $this->attrs['uid'][0];

        // Remember account status
        $this->initially_was_account = $this->is_account;

        stats::log(
            'plugin',
            $class = get_class($this),
            $category = array($this->acl_category),
            $action = 'open',
            $amount = 1,
            $duration = (microtime(TRUE) - $this->initTime)
        );
    }

    function execute()
    {
        parent::execute();

        // Log view
        if ($this->is_account && !$this->view_logged) {
            $this->view_logged = true;
            new log("view", "groups/" . get_class($this), $this->dn);
        }

        // Display dialog to allow selection of groups
        if (isset($_POST['edit_posixgroupmembership'])) {
            $this->currentGroupType = GroupType::POSIX_GROUP;
            $this->groupRelationSelect = new GroupRelationshipSelect($this->config, get_userinfo(), $this->currentGroupType, $this->uid);
        }

        // Display dialog to allow selection of groups
        if (isset($_POST['edit_objectgroupmembership'])) {
            $this->currentGroupType = GroupType::OBJECT_GROUP;
            $this->groupRelationSelect = new GroupRelationshipSelect($this->config, get_userinfo(), $this->currentGroupType, $this->dn);
        }

        // Cancel group dialog
        if (isset($_POST['add_groups_cancel']) || isset($_POST['cancel-abort'])) {
            $this->groupRelationSelect = NULL;
        }

        // Add groups selected in groupSelect dialog to ours.
        if ((isset($_POST['add_groups_finish']) || isset($_POST['ok-save'])) && $this->groupRelationSelect) {
            $groups = $this->groupRelationSelect->detectPostActions();
            if (isset($groups['targets'])) {
                switch ($this->currentGroupType) {
                    case GroupType::POSIX_GROUP:
                        $this->addToPosixGroups = $groups['targent'];
                        break;

                    case GroupType::OBJECT_GROUP:
                        $this->addToObjectgroups = $groups['targent'];
                        break;
                }
                $this->is_modified = TRUE;
            }
            $this->groupRelationSelect = NULL;
            $this->refreshGroupList();
        }

        if (empty($this->list)) {
            $this->refreshGroupList();
        }

        foreach (array_keys($_POST) as $postParam) {
            if (strpos($postParam, 'del_') === 0) {
                $releaseAction = "removeFromGroup";
                $list = $this->list;
                if ($list !== null) {
                    if (strpos($postParam, $list->getListId())) {
                        // ATTENTION: WORKAROUND
                        // sortableListing is checking $_REQUEST['PID'] for being the active one
                        // but having more than one listing on one page will set the PID value
                        // to the latest sortableListing object that is displayed.
                        $_REQUEST['PID'] = $list->getListId();
                        $list->save_object();
                        $action = $list->getAction();
                        $this->$releaseAction($list->getData($action['targets'][0])['dn']);
                    }
                }
            }
        }


        // Load Smarty
        $smarty = get_smarty();

        // Render group select template if set.
        if ($this->groupRelationSelect) {
            $this->dialog = TRUE;

            // Build up blocklist
            session::set('filterBlacklist', array('dn' => array_keys($this->listData)));
            return ($this->groupRelationSelect->execute());
        }

        // Assign acls
        $tmp = $this->plInfo();
        foreach ($tmp['plProvidedAcls'] as $name => $translation) {
            $smarty->assign($name . "ACL", $this->getacl($name));
        }

        // Assign values
        $smarty->assign('objectList', $this->objectList);
        $smarty->assign('posixGroups', $this->getAllPosixGroups());
        $smarty->assign('objectGroups', $this->getAllObjectGroups());

        return ($smarty->fetch(get_template_path('GroupList.tpl', TRUE, dirname(__FILE__))));
    }

    function save()
    {
        $ldap = $this->config->get_ldap_link();

        parent::save();

        if (isset($this->addToObjectgroups)) {
            $attrs = ['member' => $this->dn];

            foreach ($this->addToObjectgroups as $groupDN) {
                $ldap->cd($groupDN);
                $ldap->modify($attrs);
                if (!$ldap->success()) {
                    msg_dialog::display(_("LDAP error"), msgPool::ldaperror($ldap->get_error(), $groupDN, LDAP_MOD, __CLASS__));
                } else {
                    new log("modify", "groups/" . get_class($this), $groupDN, array_keys($attrs), $ldap->get_error());
                }
            }

            $this->addToObjectgroups = NULL;
        }

        if (isset($this->addToPosixGroups)) {
            $attrs = ['memberUid' => $this->uid];

            foreach ($this->addToPosixGroups as $groupDN) {
                $ldap->cd($groupDN);
                $ldap->modify($attrs);
                if (!$ldap->success()) {
                    msg_dialog::display(_("LDAP error"), msgPool::ldaperror($ldap->get_error(), $groupDN, LDAP_MOD, __CLASS__));
                } else {
                    new log("modify", "groups/" . get_class($this), $groupDN, array_keys($attrs), $ldap->get_error());
                }
            }

            $this->addToPosixGroups = NULL;
        }
    }

    /**
     * Updates the list of groups in which the current user is a member. 
     */
    function refreshGroupList()
    {
        $msg = _("Relationship Manager");
        $attrs = ['cn' => _("Name"), 'description' => _("Description")];

        $list = new sortableListing();
        $list->setDeleteable(true);
        $list->setEditable(false);
        $list->setWidth("100%");
        $list->setHeight("80px");
        $list->setHeader(array_values(array_merge($attrs, [_("Type")])));
        $list->setDefaultSortColumn(0);
        $list->setAcl('rwcdm');

        $data = [];
        $displayData = [];

        foreach ($this->objectClasses as $key => $objectClass) {
            $ldap = $this->config->get_ldap_link();
            $ldap->cd($this->config->current['BASE']);
            $str = "";
            $type = "";

            if ($objectClass == 'gosaGroupOfNames') {
                $ldap->search(
                    "(&(objectClass=$objectClass)(member=" . LDAP::escapeValue($this->dn) . "))",
                    array_merge(array_keys($attrs), ['dn'])
                );
                $type = _("Objectgroup");
            } elseif ($objectClass == 'posixGroup') {
                $ldap->search(
                    "(&(objectClass=$objectClass)(memberUid=" . LDAP::escapeValue($this->uid) . "))",
                    array_merge(array_keys($attrs), ['dn'])
                );
                $type = _("Posix group");
            }

            foreach ($this->addToObjectgroups as $key => $value) {
            }

            foreach ($this->addToPosixGroups as $key => $value) {
            }

            if (!$ldap->success()) {
                msg_dialog::display(_("LDAP error"), msgPool::ldaperror($ldap->get_error(), $this->dn, LDAP_SEARCH, __CLASS__));
            } elseif ($ldap->count()) {
                while ($result = $ldap->fetch()) {
                    $entry = array();
                    foreach ($attrs as $name => $desc) {
                        $value = "";
                        if (isset($result[$name][0])) $value = $result[$name][0];
                        $entry['data'][] = $value;
                    }
                    $entry = array_filter($entry);

                    array_push($entry['data'], $type);
                    $displayData[] = $entry;
                    $entry['dn'] = $result['dn'];
                    $data[] = $entry;
                }
            }
        }

        $list->setListData($data, $displayData);
        $list->update();
        $str .= "<h2>" . $msg . "</h2><div class='row'><div class='col s12'>";
        $str .= $list->render();
        $str .= "</div></div>";
        $str .= "<br>";
        $this->list = $list;
        $this->listData = $data;
        $this->objectList = $str;
    }

    function getAllPosixGroups()
    {

        $filter = "(&(objectClass=posixGroup)(!(memberUid=" . LDAP::escapeValue($this->uid) . ")))";
        $attrs  = ['cn' => _("Name"), 'description' => _("Description")];

        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);

        $ldap->search($filter, array_merge(array_keys($attrs), ['dn']));
        if ($ldap->count()) {
            $data = [];
            $displayData = [];
            while ($result = $ldap->fetch()) {
                $entry = array();
                foreach ($attrs as $name => $desc) {
                    $value = "";
                    if (isset($result[$name][0])) $value = $result[$name][0];
                    $entry[] = $value;
                }
                $displayData[$result['dn']] = $entry[0] . ': ' . $entry[1];
                $entry['dn'] = $result['dn'];
                $data[] = $entry;
            }
            return $displayData;
        }
        return null;
    }

    function getAllObjectGroups()
    {
        $filter = "(&(objectClass=gosaGroupOfNames)(!(member=" . LDAP::escapeValue($this->dn) . ")))";
        $attrs  = ['cn' => _("Name"), 'description' => _("Description")];

        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);

        $ldap->search($filter, array_merge(array_keys($attrs), ['dn']));
        if ($ldap->success()) {
            $data = [];
            $displayData = [];
            while ($result = $ldap->fetch()) {
                $entry = array();
                foreach ($attrs as $name => $desc) {
                    $value = "";
                    if (isset($result[$name][0])) $value = $result[$name][0];
                    $entry[] = $value;
                }
                $displayData[$result['dn']] = $entry[0] . ': ' . $entry[1];
                $entry['dn'] = $result['dn'];
                $data[] = $entry;
            }
            return $displayData;
        }
        return null;
    }

    function removeFromGroup(string $dn)
    {
        $removeMember = "";
        $groupMemberName = "";

        $ldap = $this->config->get_ldap_link();
        $ldap->cat($dn);
        if ($ldap->count() == 1) {
            $group = $ldap->fetch();
            if (isset($group["member"]) && in_array($this->dn, $group['member'])) {
                $groupMemberName = 'member';
                $removeMember = $this->dn;
            }
            if (isset($group["memberUid"]) && in_array($this->uid, $group['memberUid'])) {
                $groupMemberName = 'memberUid';
                $removeMember = $this->uid;
            }

            $ldap->cd($dn);
            $ldap->rm([$groupMemberName => $removeMember]);
            if (!$ldap->success()) {
                msg_dialog::display(_("LDAP error"), msgPool::ldaperror($ldap->get_error(), $dn, LDAP_MOD, __CLASS__));
            }
        }

        $this->refreshGroupList();
    }

    function addToGroup($groups)
    {
        /* include global link_info */
        $ldap = $this->config->get_ldap_link();

        /* Walk through groups and add the descriptive entry if not exists */
        foreach ($groups as $value) {
        }
    }

    // Plugin informations for acl handling
    static function plInfo()
    {
        return (array(
            "plShortName"   => _('Relationship manager'),
            "plDescription" => _('Manage user relationship'),
            "plSelfModify"  => FALSE,
            "plDepends"     => array(),
            "plPriority"    => 1,
            "plSection"     => array("admin"),
            "plCategory"    => array("groupmembership" => array("description" => _("Manage user relationship"))),

            "plProvidedAcls" => ['']
        ));
    }
}
