<?php

namespace Tests\Ldap;

use LdapRecord\Models\OpenLDAP\User as LdapRecordUser;

class OpenLdapUser extends LdapRecordUser
{
    /**
     * The object classes of the LDAP model.
     */
    public static array $objectClasses = [
        'top',
        'person',
        'organizationalPerson',
        'inetOrgPerson',
    ];

    /**
     * The GUID key of the model.
     * OpenLDAP uses entryuuid.
     */
    protected string $guidKey = 'entryuuid';

    /**
     * The password hashing method.
     */
    protected string $passwordHashMethod = 'none';
}
