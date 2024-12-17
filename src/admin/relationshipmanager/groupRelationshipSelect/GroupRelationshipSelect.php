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
use \session as session;
use \filter as filter;
use \listing as listing;

class GroupRelationshipSelect extends management
{

  protected $skipFooter = TRUE;
  protected $skipHeader = TRUE;

  var $plHeadline = "Group selection";

  function __construct($config,$ui)
  {
    global $BASE_DIR;

    $this->config = $config;
    $this->ui = $ui;

    $this->storagePoints = array(get_ou("core", "groupRDN"));

    // Build filter
    if (session::global_is_set(get_class($this)."_filter")){
      $filter= session::global_get(get_class($this)."_filter");
    } else {
      $filter = new filter(get_template_path("PosixGroupFilter.xml", true, dirname(__FILE__)));
      $filter->setObjectStorage($this->storagePoints);
    }
    $this->setFilter($filter);

    // Build headpage
    $headpage = new listing(get_template_path("GroupList.xml", true, dirname(__FILE__)));
    // Change the path to current stucture.
    $headpage->xmlData['definition']['template'] = $BASE_DIR . '/' . $headpage->xmlData['definition']['template'];
    $headpage->setFilter($filter);
    parent::__construct($config, $ui, "groups", $headpage);
  }
} 
// vim:tabstop=2:expandtab:shiftwidth=2:filetype=php:syntax:ruler:
?>