<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Login Search Base DNs
    |--------------------------------------------------------------------------
    |
    | The base DNs to search for users during login.
    | If not specified, it will default to the connection's base DN.
    |
    */
    'login_search_base_dns' => array_filter(explode(',', env('LDAP_LOGIN_SEARCH_BASE_DNS', env('LDAP_BASE_DN')))),

    /*
    |--------------------------------------------------------------------------
    | Sync Search Base DNs
    |--------------------------------------------------------------------------
    |
    | The base DNs to search for users during the synchronization process.
    | This allows synchronizing users from multiple OUs or containers.
    | If not specified, it will default to the connection's base DN.
    |
    */
    'sync_search_base_dns' => array_filter(explode(',', env('LDAP_SYNC_SEARCH_BASE_DNS', env('LDAP_BASE_DN')))),

    /*
    |--------------------------------------------------------------------------
    | Manual Sync Extension Days
    |--------------------------------------------------------------------------
    |
    | The number of days to extend the manual synchronization period when
    | a user is marked for manual management (e.g., during inventory).
    |
    */
    'manual_sync_extension_days' => env('LDAP_MANUAL_SYNC_EXTENSION_DAYS', 90),

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
    | You can specify a key-value pair: 'AD_CODE_ATTRIBUTE' => 'AD_NAME_ATTRIBUTE'
    |   - AD_CODE_ATTRIBUTE: Used as the unique identifier for the organization (stored in org_id).
    |   - AD_NAME_ATTRIBUTE: Used as the display name for the organization.
    |
    | If only the value is provided, it will be used as both the code and the name.
    |
    */
    'hierarchy_attributes' => [
        // Example: If AD users have 'extensionAttribute1' for company code and 'company' for company name
        // 'extensionAttribute1' => 'company',
        // 'extensionAttribute2' => 'department',
        'ou', // Default for rroemhild/test-openldap
    ],

    /*
    |--------------------------------------------------------------------------
    | Delete Missing Organizations
    |--------------------------------------------------------------------------
    |
    | Whether to soft-delete organizations that are no longer
    | present in the LDAP directory after a sync.
    |
    */
    'delete_missing' => env('AD_SYNC_DELETE_MISSING', true),

    /*
    |--------------------------------------------------------------------------
    | Deletion Threshold Percentage
    |--------------------------------------------------------------------------
    |
    | If the percentage of organizations to be deleted exceeds this threshold,
    | the deletion process will be aborted as a safety measure.
    | Set to 0 to disable this safety check.
    |
    */
    'deletion_threshold_percentage' => env('AD_SYNC_DELETION_THRESHOLD_PERCENTAGE', 20),
];
