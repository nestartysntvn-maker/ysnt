<?php
namespace App\Models;

use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthentication;
use Filament\Auth\MultiFactor\App\Contracts\HasAppAuthenticationRecovery;
use Filament\Models\Contracts\FilamentUser;
use Filament\Panel;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;

class User extends Authenticatable implements FilamentUser, HasAppAuthentication, HasAppAuthenticationRecovery
{
    use Notifiable;

    public const ROLES = ['admin' => 'Quản trị', 'accountant' => 'Kế toán', 'sales' => 'Bán hàng', 'warehouse' => 'Kho', 'content' => 'Nội dung', 'hr' => 'Nhân sự'];
    protected $fillable = ['name', 'email', 'phone', 'password', 'role', 'is_active'];
    protected $hidden = ['password', 'remember_token', 'app_authentication_secret', 'app_authentication_recovery_codes'];

    protected function casts(): array
    {
        return ['email_verified_at' => 'datetime', 'password' => 'hashed', 'is_active' => 'boolean',
            'app_authentication_secret' => 'encrypted', 'app_authentication_recovery_codes' => 'encrypted:array'];
    }

    public function canAccessPanel(Panel $panel): bool { return $this->is_active && array_key_exists($this->role, self::ROLES); }
    public function hasRole(string ...$roles): bool { return $this->role === 'admin' || in_array($this->role, $roles, true); }

    public function getAppAuthenticationSecret(): ?string { return $this->app_authentication_secret; }
    public function saveAppAuthenticationSecret(#[\SensitiveParameter] ?string $secret): void { $this->app_authentication_secret = $secret; $this->save(); }
    public function getAppAuthenticationHolderName(): string { return $this->email; }
    public function getAppAuthenticationRecoveryCodes(): ?array { return $this->app_authentication_recovery_codes; }
    public function saveAppAuthenticationRecoveryCodes(#[\SensitiveParameter] ?array $codes): void { $this->app_authentication_recovery_codes = $codes; $this->save(); }
}
