<?php

return [
    /*
    |--------------------------------------------------------------------------
    | AD Sync Mode
    |--------------------------------------------------------------------------
    |
    | The mode used to synchronize organizations.
    |
    | Supported: "attribute", "ou"
    |
    */
    'mode' => env('AD_SYNC_MODE', 'attribute'),

    /*
    |--------------------------------------------------------------------------
    | Hierarchy Attributes
    |--------------------------------------------------------------------------
    |
    | The attributes used to generate the organization hierarchy when using
    | the "attribute" sync mode. The order of attributes determines the
    | depth and structure of the hierarchy.
    |
    */
    'hierarchy_attributes' => [
        'ou', // rroemhild/test-openldap のデータに合わせて 'ou' を使用
        // 'department',
        // 'physicalDeliveryOfficeName',
    ],

    /*
    |--------------------------------------------------------------------------
    | LDAP Filters
    |--------------------------------------------------------------------------
    |
    | The filters used to retrieve users and organizations from LDAP.
    |
    */
    'filters' => [
        'users' => env('AD_SYNC_USER_FILTER', '(objectClass=inetOrgPerson)'), // OpenLDAP用
        'ous' => env('AD_SYNC_OU_FILTER', '(objectClass=organizationalUnit)'),
    ],

    /*
    |--------------------------------------------------------------------------
    | Delete Missing
    |--------------------------------------------------------------------------
    |
    | Whether to soft-delete organizations and users that are no longer
    | present in the LDAP directory.
    |
    */
    'delete_missing' => env('AD_SYNC_DELETE_MISSING', true),
];
