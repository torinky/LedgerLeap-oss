<?php

namespace App\Filament\Resources\TenantResource\Pages;

use App\Filament\Resources\TenantResource;
use App\Models\User;
use Exception;
use Filament\Notifications\Notification;
use Filament\Resources\Pages\CreateRecord;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Stancl\Tenancy\Database\Models\Domain;

class CreateTenant extends CreateRecord
{
    protected static string $resource = TenantResource::class;

    protected function handleRecordCreation(array $data): Model
    {
        $tenantId = $data['id'];
        $adminEmail = $data['admin_email'];
        $domain = $tenantId . '.localhost'; // or your domain logic

        // Domain existence check
        if (Domain::where('domain', $domain)->exists()) {
            Notification::make()
                ->title(__('ledger.tenant_creation_failed'))
                ->body(__('ledger.domain_already_exists', ['domain' => $domain]))
                ->danger()
                ->send();
            $this->halt();
        }

        $tenant = null;

        try {
            DB::transaction(function () use ($data, $tenantId, $domain, $adminEmail, &$tenant) {
                // Filamentがフォームデータを自動的にモデルの属性にマッピングしてくれる
                $tenant = static::getModel()::create($data);

                // ★ nameとdescriptionをdataカラムに保存するために明示的に代入し保存
                $tenant->name = $data['name'];
                $tenant->description = $data['description'] ?? null;
                $tenant->save();

                $tenant->domains()->create(['domain' => $domain]);

                tenancy()->initialize($tenant);

                Artisan::call('tenants:migrate', ['--tenants' => [$tenant->id], '--force' => true]);
                activity()->disableLogging();
                Artisan::call('tenants:seed', [
                    '--tenants' => [$tenant->id],
                    '--class' => 'DatabaseSeeder',
                    '--force' => true
                ]);
                activity()->enableLogging();

                $user = tenancy()->central(function () use ($adminEmail) {
                    return User::where('email', $adminEmail)->first();
                });

                if (!$user) {
                    throw new Exception("Admin user with email '{$adminEmail}' not found.");
                }

                $tenant->run(function () use ($user) {
                    \App\Models\Folder::create([
                        'title' => '/',
                        'creator_id' => $user->id,
                        'modifier_id' => $user->id,
                    ]);
                });

                $tenant->users()->syncWithoutDetaching([$user->id]);
                $user->assignRole('Super Admin');
            });

            Notification::make()
                ->title(__('ledger.tenant_creation_successful'))
                ->success()
                ->send();

            return $tenant;

        } catch (Exception $e) {
            if ($tenant) {
                $tenant->delete(); // Rollback
            }

            Notification::make()
                ->title(__('ledger.tenant_creation_failed'))
                ->body($e->getMessage())
                ->danger()
                ->send();

            $this->halt();
        }
    }
}