<?php

namespace App\Ldap;

use LdapRecord\Models\OpenLDAP\OrganizationalUnit as LdapRecordOrganizationalUnit;

class OrganizationalUnit extends LdapRecordOrganizationalUnit
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
     */
    public static array $objectClasses = [
        'top',
        'organizationalUnit',
    ];

    /**
     * The attribute key that contains the models object GUID.
     *
     * @var string
     */
    protected string $guidKey = 'entryuuid';
}