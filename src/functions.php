<?php

namespace GosaRelationshipManager\admin\relationshipmanager;

$success = bindtextdomain("GosaRelationshipManager", dirname(dirname(__FILE__)) . "/locale/compiled");

function __($GETTEXT)
{
    return dgettext("GosaRelationshipManager", $GETTEXT);
}
