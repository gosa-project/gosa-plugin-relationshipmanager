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

namespace GosaRelManager\admin\relationshipmanager;

use \management as management;
use \filter as filter;
use \listing as listing;
use \LDAP as LDAP;

/**
 * Use to build group selection for posix - or objectgroups
 */
class GroupRelationshipSelect extends management
{

  protected $skipFooter = TRUE;
  protected $skipHeader = TRUE;

  var $plHeadline = "Group selection";

  var $dn;
  var $uid;

  /**
   * construct an group relationship select dialog
   * @param $config GOsa config
   * @param $ui userinfo
   * @param GroupType group type for the group selection e.g. posixGroup
   * @param string $identifier the needed identifier to sort out already related groups,
   * posixGroup's require the uid and objectgroups require the dn of a user.
   */
  function __construct($config, $ui, $type, $identifier)
  {
    global $BASE_DIR;

    $this->config = $config;
    $this->ui = $ui;

    $this->storagePoints = array(get_ou("core", "groupRDN"));

    // Build filter
    if ($type == GroupType::POSIX_GROUP){
      $filter = new filter(get_template_path("PosixGroupFilter.xml", true, dirname(__FILE__)));

      // filter already related groups
      if ($identifier != "") {
        $matchDN = "(!(memberUID=" . LDAP::escapeValue($identifier) . "))";
        $filter->query[0]['filter'] = preg_replace('/\#/', $matchDN, $filter->query[0]['filter']);
      } else {
        $filter->query[0]['filter'] = preg_replace('/\#/', "", $filter->query[0]['filter']);
      }
      $filter->setObjectStorage($this->storagePoints);
      $this->setFilter($filter);

      // Build headpage
      $headpage = new listing(get_template_path("PosixGroupList.xml", true, dirname(__FILE__)));

      // Change the path to current stucture.
      $headpage->xmlData['definition']['template'] = $BASE_DIR . '/' . $headpage->xmlData['definition']['template'];
      $headpage->setFilter($filter);

      $plugname = 'groups';
    } 

    if ($type == GroupType::OBJECT_GROUP) {
      $filter = new filter(get_template_path("ObjectgroupFilter.xml", true, dirname(__FILE__)));

      // filter already related groups
      if ($identifier != "") {
        $matchDN = "(!(member=" . LDAP::escapeValue($identifier) . "))";
        $filter->query[0]['filter'] = preg_replace('/\#/', $matchDN, $filter->query[0]['filter']);
      } else {
        $filter->query[0]['filter'] = preg_replace('/\#/', "", $filter->query[0]['filter']);
      }
      $filter->setObjectStorage($this->storagePoints);
      $this->setFilter($filter);

      // Build headpage
      $headpage = new listing(get_template_path("ObjectgroupList.xml", true, dirname(__FILE__)));
      
      // Change the path to current stucture.
      $headpage->xmlData['definition']['template'] = $BASE_DIR . '/' . $headpage->xmlData['definition']['template'];
      $headpage->setFilter($filter);

      $plugname = 'ogroups';
    }
    
    parent::__construct($config, $ui, $plugname, $headpage);
  }
} 
// vim:tabstop=2:expandtab:shiftwidth=2:filetype=php:syntax:ruler:
?>