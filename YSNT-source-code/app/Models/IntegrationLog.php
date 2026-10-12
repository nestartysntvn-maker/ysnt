<?php
namespace App\Models;
use Illuminate\Database\Eloquent\Model;
class IntegrationLog extends Model
{
    public const UPDATED_AT = null;
    protected $guarded = [];
    protected $casts = ['payload' => 'array'];
    public const STATUS = ['ok' => 'Thành công', 'dry_run' => 'Chạy thử', 'error' => 'Lỗi', 'skipped' => 'Bỏ qua'];
    public static function write(string $integration, string $action, string $status, ?string $message = null, ?array $payload = null, ?string $ref = null): self
    {
        return static::create(['integration' => $integration, 'action' => $action, 'status' => $status, 'ref' => $ref ? mb_substr($ref, 0, 64) : null,
            'message' => $message ? mb_substr($message, 0, 2000) : null, 'payload' => $payload]);
    }
}
