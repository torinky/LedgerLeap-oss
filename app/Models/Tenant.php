<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Stancl\Tenancy\Database\Concerns\HasDomains;
use Stancl\Tenancy\Database\Models\Tenant as BaseTenant;

/**
 * LedgerLeap のテナントモデル。
 *
 * 組織・プロジェクト単位の論理的な分離境界を表す。
 * パスベースのテナント解決や Livewire コンポーネントの
 * テナントコンテキスト復元の基盤となる。
 */
class Tenant extends BaseTenant
{
    use HasDomains, HasFactory;
}
