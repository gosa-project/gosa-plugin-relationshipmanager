<?php

/**
 * This code is part of GOsa (https://gosa.gonicus.de)
 * Copyright (C) 2025 Gonicus GmbH
 * 
 * developed by zapiec@gonicus.de
 * 
 * This program is free software; you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation; either version 2 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program; if not, write to the Free Software
 * Foundation, Inc., 59 Temple Place, Suite 330, Boston, MA  02111-1307  USA
 */

namespace GosaRelationshipManager\admin\relationshipmanager;

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
