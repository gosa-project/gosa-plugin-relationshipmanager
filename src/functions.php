<?php

namespace GosaRelationshipManager\admin\relationshipmanager;

$success = bindtextdomain('GosaRelationshipManager', dirname(dirname(__FILE__)) . '/locale/compiled');

function __(string $GETTEXT): string
{
    return dgettext('GosaRelationshipManager', $GETTEXT);
}
