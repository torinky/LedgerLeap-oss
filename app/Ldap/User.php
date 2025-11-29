<?php

namespace App\Ldap;

use LdapRecord\Models\ActiveDirectory\User as LdapRecordUser;

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
        'user',
    ];
}
