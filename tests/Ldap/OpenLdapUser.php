<?php

namespace Tests\Ldap;

use LdapRecord\Models\OpenLDAP\User as LdapRecordUser;

class OpenLdapUser extends LdapRecordUser
{
    /**
     * The object classes of the LDAP model.
     *
     * @var array
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
     *
     * @var string
     */
    protected string $guidKey = 'entryuuid';
}
