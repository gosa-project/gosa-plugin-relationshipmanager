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

class GroupMembership extends plugin
{
    // Definitions
    var $plHeadline = "Group Membership";
    var $plDescription = "Manage membership for user in posix or object groups";
    var $plIcon = "";
    var $matIcon = "groups";

    /* attribute list for save action */
    var $attributes = [];
    var $objectClasses = [];
    var $objectGroups = [];
    var $objectList = [];
    var $lists = [];
    var $initTime;
    var $plugin;

    function __construct(&$config, $dn = NULL, $parent = NULL)
    {
        $this->initTime = microtime(TRUE);

        parent::__construct($config, $dn, $parent);

        stats::log(
            'plugin',
            $class = get_class($this),
            $category = array($this->acl_category),
            $action = 'open',
            $amount = 1,
            $duration = (microtime(TRUE) - $this->initTime)
        );

        // Check for objectGroup membership
        $this->objectGroups = array(
            'filter' => "(&(objectClass=gosaGroupOfNames)(member=" . LDAP::escapeValue($this->dn) . "))",
            'attrs'  => ['cn' => _("Name"), 'description' => _("Description")],
            'msg'    => _("Object group membership"),
            'releaseAction' => 'removeMember',
            'listObject' => new sortableListing()
        );
    }

    function execute()
    {
        parent::execute();

        $this->refreshGroupList();
        $allObjectGroups = $this->getAllObjectGroups();
        $allGroups = $this->getAllGroups();

        $smarty = get_smarty();

        $smarty->assign('objectList', $this->objectList);
        $smarty->assign('allGroups', $allGroups);
        $smarty->assign('allObjectGroups', $allObjectGroups);

        return ($smarty->fetch(get_template_path('group-list.tpl', TRUE, dirname(__FILE__))));
    }

    function refreshGroupList()
    {
        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);
        $str = "";

        $ldap->search($this->objectGroups['filter'], array_merge(array_keys($this->objectGroups['attrs']), ['dn']));
        if (!$ldap->success()) {
            msg_dialog::display(_("LDAP error"), msgPool::ldaperror($ldap->get_error(), $this->dn, LDAP_SEARCH, __CLASS__));
        } elseif ($ldap->count()) {
            $list = $this->objectGroups['listObject'];
            if (isset($this->objectGroups['releaseAction'])) {
                $list->setDeleteable(true);
            } else {
                $list->setDeleteable(false);
            }

            $list->setEditable(false);
            $list->setWidth("100%");
            $list->setHeight("80px");
            $list->setHeader(array_values($this->objectGroups['attrs']));
            $list->setDefaultSortColumn(0);
            $list->setAcl('rwcdm');

            $data = [];
            $displayData = [];
            while ($attrs = $ldap->fetch()) {
                $entry = array();
                foreach ($this->objectGroups['attrs'] as $name => $desc) {
                    $$name = "";
                    if (isset($attrs[$name][0])) $$name = $attrs[$name][0];
                    $entry['data'][] = $$name;
                }
                $displayData[] = $entry;
                $entry['dn'] = $attrs['dn'];
                $data[] = $entry;
            }

            $list->setListData($data, $displayData);

            $list->update();

            $str .= "<h2>" . $this->objectGroups['msg'] . "</h2><div class='row'><div class='col s12'>";
            $str .= $list->render();
            $str .= "</div></div>";
            $str .= "<br>";
            $this->lists[] = $list;
        }

        $this->objectList = $str;
    }

    function getAllGroups()
    {
        $filter = "(&(objectClass=posixGroup))";
        $attrs  = ['cn' => _("Name"), 'description' => _("Description")];

        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);
        $res = "";

        $ldap->search($filter, array_merge(array_keys($this->objectGroups['attrs']), ['dn']));
        if ($ldap->success()) {
            $data = [];
            $displayData = [];
            while ($attrs = $ldap->fetch()) {
                $entry = array();
                foreach ($this->objectGroups['attrs'] as $name => $desc) {
                    $$name = "";
                    if (isset($attrs[$name][0])) $$name = $attrs[$name][0];
                    $entry[] = $$name;
                }
                $displayData[$attrs['dn']] = $entry[0] . ': ' . $entry[1];
                $entry['dn'] = $attrs['dn'];
                $data[] = $entry;
            }

            // echo "<pre>";
            // var_dump($displayData);
            // echo "</pre>";
        }

        return $displayData;
    }

    function getAllObjectGroups()
    {
        $filter = "(&(objectClass=gosaGroupOfNames))";
        $attrs  = ['cn' => _("Name"), 'description' => _("Description")];

        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);
        $res = "";

        $ldap->search($filter, array_merge(array_keys($this->objectGroups['attrs']), ['dn']));
        if ($ldap->success()) {
            $data = [];
            $displayData = [];
            while ($attrs = $ldap->fetch()) {
                $entry = array();
                foreach ($this->objectGroups['attrs'] as $name => $desc) {
                    $$name = "";
                    if (isset($attrs[$name][0])) $$name = $attrs[$name][0];
                    $entry[] = $$name;
                }
                $displayData[$attrs['dn']] = $entry[0] . ': ' . $entry[1];
                $entry['dn'] = $attrs['dn'];
                $data[] = $entry;
            }

            // echo "<pre>";
            // var_dump($displayData);
            // echo "</pre>";
        }

        return $displayData;
    }

    // Plugin informations for acl handling
    static function plInfo()
    {
        return (array(
            "plShortName"   => _('Group membership'),
            "plDescription" => _('Group membership'),
            "plSelfModify"  => FALSE,
            "plDepends"     => array(),
            "plPriority"    => 1,
            "plSection"     => array("admin"),
            "plCategory"    => array("groupmembership" => array("description" => _("Group membership"))),

            "plProvidedAcls" => array()
        ));
    }
}
