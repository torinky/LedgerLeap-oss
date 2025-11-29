<?php

namespace App\Ldap;

use LdapRecord\Models\OpenLDAP\User as LdapRecordUser;

class User extends LdapRecordUser
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
     * The attribute key that contains the models object GUID.
     *
     * @var string
     */
    protected string $guidKey = 'entryuuid';
}
