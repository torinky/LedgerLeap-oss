<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class UserOrganization extends Pivot
{
    protected $table = 'user_organizations';

    protected $fillable = ['user_id', 'organization_id', 'is_primary'];

    public function user()
    {
        return $this->belongsTo(User::class);
    }

    public function organization()
    {
        return $this->belongsTo(Organization::class);
    }
}
