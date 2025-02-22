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

namespace GosaRelationshipManager\admin\relationshipmanager\groupRelationshipSelect;

use \filterLDAP as filterLDAP;
use \session as session;

class FilterLdapBlacklist
{

    static function query($base, $scope, $filter, $attributes, $category, $objectStorage = "")
    {
        $result = filterLDAP::query($base, $scope, $filter, $attributes, $category, $objectStorage);
        return (FilterLdapBlacklist::filterByBlacklist($result));
    }

    static function filterByBlacklist($entries)
    {
        if (session::is_set('filterBlacklist')) {
            $blist = session::get('filterBlacklist');
            foreach ($blist as $attr_name => $attr_values) {
                foreach ($attr_values as $match) {
                    foreach ($entries as $id => $entry) {
                        if (isset($entry[$attr_name])) {
                            $test = $entry[$attr_name];
                            if (!is_array($test)) $test = array($test);
                            if (in_array_strict($match, $test)) unset($entries[$id]);
                        }
                    }
                }
            }
        }
        return (array_values($entries));
    }
}
