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
use \listing as listing;
use \filter as filter;
use \LDAP as LDAP;
use \GosaRelationshipManager\admin\relationshipmanager\groupRelationshipSelect\GroupRelationshipSelect as GroupRelationshipSelect;
use \GosaRelationshipManager\admin\relationshipmanager\RelationshipFactory as RelationshipFactory;

class RelationshipManager extends Plugin
{
    // Definitions
    public $plHeadline = 'Relationship manager';
    public $plDescription = 'Manage user relationship';
    public $plIcon = '';
    public $matIcon = 'groups';

    // Class attributes
    public $view_logged = false;
    public $uid = "";
    public $groupRelationSelect;
    public listing $list;
    public filter $filter;
    public $listData = [];
    public $initTime;
    public $addToPosixGroups = [];
    public $addToObjectgroups = [];
    public ResourceType $currentResourceType;
    public $storage = [];

    // attribute list for save action
    public $objectClasses = ['gosaGroupOfNames', 'posixGroup'];
    public $objectList = [];

    function __construct($config, $dn = null, $parent = null)
    {
        parent::__construct($config, $dn, $parent);
        $this->plHeadline = __('Relationship manager');
        $this->plDescription = __('Manage user relationship');

        $this->initTime = microtime(true);
        $this->uid = $this->attrs['uid'][0];

        $this->storage = [get_ou('core', 'groupRDN')];

        // Remember account status
        $this->initially_was_account = $this->is_account;
        $this->list = new listing(__DIR__ . '/themes/default/RelatedList.xml');
        $this->filter = new RelationshipFilter(__DIR__ . '/themes/default/RelatedListFilter.xml', ['DN' => $dn, 'UID' => $this->uid]);
        $this->filter->setObjectStorage($this->storage);
        $this->list->setFilter($this->filter);
        $this->list->showFooter = false;
    }

    function execute()
    {
        global $config;
        parent::execute();

        // Log view
        if ($this->is_account && !$this->view_logged) {
            $this->view_logged = true;
            new log('view', 'groups/' . get_class($this), $this->dn);
        }

        // Display dialog to allow selection of groups
        if (isset($_POST['edit_posixgroupmembership'])) {
            $this->currentResourceType = ResourceType::POSIX_GROUP;
            $this->groupRelationSelect = new GroupRelationshipSelect($config, get_userinfo(), $this->currentResourceType, $this->uid);
        }

        // Display dialog to allow selection of groups
        if (isset($_POST['edit_objectgroupmembership'])) {
            $this->currentResourceType = ResourceType::OBJECT_GROUP;
            $this->groupRelationSelect = new GroupRelationshipSelect($config, get_userinfo(), $this->currentResourceType, $this->dn);
        }

        // Cancel group dialog
        if (isset($_POST['cancel-abort'])) {
            $this->groupRelationSelect = null;
        }

        // Add groups selected in groupSelect dialog to ours.
        if (isset($_POST['ok-save']) && $this->groupRelationSelect) {
            $groups = $this->groupRelationSelect->detectPostActions();
            if (isset($groups['targets'])) {
                switch ($this->currentResourceType) {
                    case ResourceType::POSIX_GROUP:
                        $this->addToPosixGroups = $groups['targets'];
                        break;

                    case ResourceType::OBJECT_GROUP:
                        $this->addToObjectgroups = $groups['targets'];
                        break;
                }
                $this->is_modified = true;
                $this->save();
            }
            $this->groupRelationSelect = null;
        }

        // get action from our plugins list
        if ($this->list->getAction() !== null) {
            $tAction = $this->list->getAction();
            // for now we just use the delete action
            if ($tAction['action'] === 'delete') {
                $relationships = [];
                foreach ($tAction['targets'] as $group) {
                    $relationships[] = RelationshipFactory::createRelationhip($this->dn, $group, $config->get_ldap_link());
                }

                foreach ($relationships as $relationship) {
                    $relationship->disassociate();
                }
            }
        }

        // Load Smarty
        $smarty = get_smarty();

        // Render group select template if set.
        if ($this->groupRelationSelect) {
            $this->dialog = true;
            return $this->groupRelationSelect->execute();
        } else {
            $this->dialog = false;
        }

        // Assign acls
        $tmp = $this->plInfo();
        foreach ($tmp['plProvidedAcls'] as $name => $translation) {
            $smarty->assign($name . "ACL", $this->getacl($name));
        }

        // Assign values
        $this->list->update();
        $smarty->assign('objectList', $this->list->render());
        $smarty->assign('posixGroups', $this->getAllPosixGroups());
        $smarty->assign('objectGroups', $this->getAllObjectGroups());

        $defaultDomain = textdomain();
        textdomain('GosaRelationshipManager');
        $display = $smarty->fetch(get_template_path('GroupList.tpl', true, dirname(__FILE__) . '/themes'));
        textdomain($defaultDomain);
        return $display;
    }

    function save()
    {
        global $config;
        $ldap = $config->get_ldap_link();

        parent::save();

        foreach ($this->addToObjectgroups as $groupDN) {
            $tObjectRelationship = new ObjectGroupRelationship($this->dn, $groupDN, $ldap);
            $tObjectRelationship->associate();
        }

        $this->addToObjectgroups = [];

        foreach ($this->addToPosixGroups as $groupDN) {
            $tPosixRelationship = new PosixGroupRelationship($this->dn, $groupDN, $ldap);
            $tPosixRelationship->associate();
        }

        $this->addToPosixGroups = [];
    }

    function getAllPosixGroups()
    {

        $filter = '(&(objectClass=posixGroup)(!(memberUid=' . LDAP::escapeValue($this->uid) . ')))';
        $attrs  = ['cn' => _('Name'), 'description' => _('Description')];

        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);

        $ldap->search($filter, array_merge(array_keys($attrs), ['dn']));
        if ($ldap->count()) {
            $data = [];
            $displayData = [];
            while ($result = $ldap->fetch()) {
                $entry = [];
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
        $filter = '(&(objectClass=gosaGroupOfNames)(!(member=' . LDAP::escapeValue($this->dn) . ')))';
        $attrs  = ['cn' => _('Name'), 'description' => _('Description')];

        $ldap = $this->config->get_ldap_link();
        $ldap->cd($this->config->current['BASE']);

        $ldap->search($filter, array_merge(array_keys($attrs), ['dn']));
        if ($ldap->success()) {
            $data = [];
            $displayData = [];
            while ($result = $ldap->fetch()) {
                $entry = [];
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

    // Plugin informations for acl handling
    static function plInfo()
    {
        return [
            'plShortName'   => __('Relationship manager'),
            'plDescription' => __('Manage user relationship'),
            'plSelfModify'  => false,
            'plDepends'     => [],
            'plPriority'    => 1,
            'plSection'     => ['admin'],
            'plCategory'    => ['groupmembership' => array('description' => _('Manage user relationship'))],
            'plProvidedAcls' => [
                'relationshipmanager' => __('Allow to edit relationships.')
            ]
        ];
    }
}
