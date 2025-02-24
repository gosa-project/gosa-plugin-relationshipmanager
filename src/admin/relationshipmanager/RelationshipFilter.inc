<?php
/*
 * This code is part of GOsa (http://www.gosa-project.org)
 * Copyright (C) 2003-2008 GONICUS GmbH
 *
 * ID: $$Id: class_filter.inc 19251 2010-07-29 13:21:27Z hickert $$
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

use \filter as filter;

class RelationshipFilter extends filter
{
    private array $predefinedParameter = [];

    public function __construct(string $filename, array $filterParameter = [])
    {
        parent::__construct($filename);
        $this->predefinedParameter = $filterParameter;
    }

    /**
     * this filter has the ability to store predefinied parameters
     * the given filter is searched for placeholders that are replaced
     * by the predefinied values
     * 
     * if you want to use this filter you have to define the parameter
     * like this in the filter.xml objectClass=%OC 
     */
    private function processPredefinedParameter(string $filter): string
    {
        foreach ($this->predefinedParameter as $parameter => $value) {
            $filter = preg_replace('/%' . $parameter . '/', $value, $filter);
        }

        return $filter;
    }

    /**
     * process queries as in the base class
     * the only diffrents is calling processPredefinedParameter
     */
    public function query()
    {
        global $class_mapping, $composerClassMap;
        $result = [];

        // Return empty list if initial is not set
        if (!$this->initial) {
            $this->initial = true;

            return $result;
        }
        // Go thru all queries and merge results
        foreach ($this->query as $query) {
            if (!isset($query['backend']) || !isset($query['filter'])) {
                exit('No backend specified in search config.');
            }

            // Is backend available?
            $backend = 'filter' . $query['backend'];
            if (!isset($class_mapping["$backend"])) {
                if (!isset($composerClassMap[$query['backend']])) {
                    exit('Invalid backend specified in search config.');
                } else {
                    $backend = $query['backend'];
                }
            }

            // Load filter and attributes
            $filter = $query['filter'];

            // Handle converters if present
            if ($this->converter) {
                preg_match('/([^:]+)::(.*)$/', $this->converter, $m);
                if ($this->value == '') {
                    $filter = call_user_func([$m[1], $m[2]], preg_replace('/\$/', '*', $filter));
                } else {
                    $filter = call_user_func([$m[1], $m[2]], preg_replace('/\$/', $this->value, $filter));
                }
            }

            // Do not replace escaped \$ - This is required to be able to search for e.g. windows machines.
            if ($this->value == '') {
                $filter = preg_replace('/\$/', '*', $filter);
            } else {
                $filter = preg_replace('/\$/', '*' . LDAP::escapeValue($this->value) . '*', $filter);
            }

            // replace placeholders with predefined values
            $filter = $this->processPredefinedParameter($filter);

            $result = array_unique(array_merge($result, call_user_func([$backend, 'query'], $this->base, $this->scope, $filter, $this->attributes, $this->category, $this->objectStorage)), SORT_REGULAR);
        }

        /* If we had additional Constraints filter the $result array */
        $finalResult = [];
        if ($this->customConstraints) {
            $finalResult = $this->constraintFilter($this->customConstraints, $result);
        } else {
            $finalResult = $result;
        }

        return $finalResult;
    }
}
