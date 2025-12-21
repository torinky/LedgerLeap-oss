<?php

namespace App\Ldap;

use LdapRecord\Models\ActiveDirectory\OrganizationalUnit as LdapRecordOrganizationalUnit;

class OrganizationalUnit extends LdapRecordOrganizationalUnit
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'organizationalUnit',
    ];
}
