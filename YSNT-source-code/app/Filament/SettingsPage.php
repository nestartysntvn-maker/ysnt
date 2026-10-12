<?php
namespace App\Filament;

use App\Models\AppSetting;
use Filament\Notifications\Notification;
use Filament\Pages\Page;
use Livewire\WithFileUploads;
use UnitEnum;

/**
 * Every add-on settings page is a list of fields stored in app_settings (encrypted).
 * Field types: text, secret, textarea, toggle, select, color, file (private), image (public), info (read-only, not saved).
 */
abstract class SettingsPage extends Page
{
    use WithFileUploads;

    protected string $view = 'filament.pages.settings-form';
    protected static string|UnitEnum|null $navigationGroup = 'Kết nối & cấu hình';
    public array $f = [];
    public array $up = [];
    public ?string $status = null;

    /** @return array<string, array{desc?:string, fields:array<string,array{label:string,type?:string,hint?:string,options?:array,placeholder?:string,default?:string,value?:string,wide?:bool}>}> */
    abstract public function sections(): array;
    /** @return array{tone:string,html:string}|null tone: info | ok | warn */
    public function banner(): ?array { return null; }
    /** @return array<string,string> livewire method => button label */
    public function buttons(): array { return []; }
    protected function saved(): void {}

    public static function canAccess(): bool { return auth()->user()?->role === 'admin'; }

    public function specs(): array { return collect($this->sections())->flatMap(fn ($s) => $s['fields'])->all(); }

    public function mount(): void
    {
        foreach ($this->specs() as $k => $s) {
            $type = $s['type'] ?? 'text';
            $this->f[$k] = match ($type) {
                'secret', 'file', 'image', 'info' => '',
                'toggle' => AppSetting::get($k, $s['default'] ?? '0') === '1',
                default => (string) AppSetting::get($k, $s['default'] ?? ''),
            };
        }
    }

    public function has(string $k): bool { return (bool) AppSetting::get($k); }

    public function save(): void
    {
        $rules = [];
        foreach ($this->specs() as $k => $s) {
            if (($s['type'] ?? '') === 'file') $rules["up.$k"] = 'nullable|file|max:10240|mimes:pdf,jpg,jpeg,png';
            if (($s['type'] ?? '') === 'image') $rules["up.$k"] = 'nullable|image|max:2048|mimes:png,jpg,jpeg,webp';
            if (($s['type'] ?? '') === 'color') $rules["f.$k"] = ['nullable', 'regex:/^#[0-9a-fA-F]{6}$/'];
        }
        if ($rules) $this->validate($rules, ['f.*.regex' => 'Mã màu dạng #0f766e', 'up.*.mimes' => 'Sai loại tệp', 'up.*.max' => 'Tệp quá lớn']);
        foreach ($this->specs() as $k => $s) {
            $type = $s['type'] ?? 'text';
            if ($type === 'info') continue;
            if ($type === 'toggle') { AppSetting::put($k, ! empty($this->f[$k]) ? '1' : '0'); continue; }
            if ($type === 'file' || $type === 'image') {
                if (! empty($this->up[$k])) AppSetting::put($k, $this->up[$k]->store($type === 'file' ? 'company' : 'brand', $type === 'file' ? 'local' : 'public'));
                continue;
            }
            $v = trim((string) ($this->f[$k] ?? ''));
            if ($type === 'secret' && $v === '') continue;   // blank secret = keep the stored one
            AppSetting::put($k, $v);
        }
        $this->up = [];
        $this->saved();
        $this->mount();
        Notification::make()->title('Đã lưu')->success()->send();
    }
}
