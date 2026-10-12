<?php
namespace App\Inbox;

use App\Ai\Ai;
use App\Channels\Connectors\TikTokShop;
use App\Jobs\AiReply;
use App\Meta\Meta;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CustomerIdentity;
use App\Zalo\Oa;

/** One inbox for every channel: identity -> conversation -> messages, replies go back through the channel they came from. */
class Inbox
{
    public const AI_AUTO = ['web' => 'ai_auto_web', 'zalo' => 'ai_auto_zalo', 'facebook' => 'ai_auto_facebook', 'instagram' => 'ai_auto_instagram', 'tiktok' => 'ai_auto_tiktok'];

    /**
     * @param array{name?:?string,phone?:?string,message_id?:?string,attachments?:array,external_ref?:?string} $o
     * @return ConversationMessage|null null when the platform re-delivered a message we already have
     */
    public static function receive(string $channel, string $externalUserId, string $text, array $o = [], bool $queueAi = true): ?ConversationMessage
    {
        $ident = CustomerIdentity::resolve($channel, $externalUserId, $o['name'] ?? null, $o['phone'] ?? null);
        $q = Conversation::where('channel', $channel);
        $conv = ! empty($o['external_ref']) ? $q->where('external_ref', $o['external_ref'])->first() : $q->where('customer_identity_id', $ident->id)->latest('id')->first();
        $conv ??= Conversation::create(['channel' => $channel, 'customer_identity_id' => $ident->id, 'customer_id' => $ident->customer_id, 'external_ref' => $o['external_ref'] ?? null,
            'name' => $ident->display_name ?: (Conversation::CHANNELS[$channel] ?? $channel) . ' …' . substr($externalUserId, -4)]);
        if (! empty($o['message_id']) && $conv->messages()->where('external_id', $o['message_id'])->exists()) return null;
        $m = $conv->messages()->create(['direction' => 'in', 'sender' => 'customer', 'body' => mb_substr($text, 0, 5000), 'attachments' => $o['attachments'] ?? null,
            'external_id' => $o['message_id'] ?? null, 'status' => 'received']);
        $conv->update(['status' => 'open', 'unread' => $conv->unread + 1, 'last_preview' => mb_substr($text, 0, 190), 'last_message_at' => now(),
            'customer_identity_id' => $ident->id, 'customer_id' => $ident->customer_id ?? $conv->customer_id]);
        if ($queueAi && self::aiOn($conv)) AiReply::dispatch($conv->id);
        return $m;
    }

    public static function aiOn(Conversation $c): bool
    {
        // web chat answers with AI by default; the other channels only when switched on
        $on = AppSetting::get(self::AI_AUTO[$c->channel] ?? '-', $c->channel === 'web' ? '1' : '0') === '1';
        return $c->ai_enabled && ! $c->needs_human && $on && Ai::ready('advisor');
    }

    /** staff or AI reply; stored first, then delivered through the conversation's own channel */
    public static function send(Conversation $c, string $text, string $sender = 'staff', array $attachments = []): ConversationMessage
    {
        $m = $c->messages()->create(['direction' => 'out', 'sender' => $sender, 'user_id' => $sender === 'staff' ? auth()->id() : null,
            'body' => $text, 'attachments' => $attachments ?: null, 'status' => 'queued']);
        try {
            [$status, $extId, $err] = self::deliver($c, self::plain($text, $attachments));
        } catch (\Throwable $e) {
            [$status, $extId, $err] = ['failed', null, mb_substr($e->getMessage(), 0, 480)];
        }
        $m->update(['status' => $status, 'external_id' => $extId, 'error' => $err]);
        $c->update(['last_preview' => mb_substr(($sender === 'ai' ? 'AI: ' : ($sender === 'system' ? '' : 'NV: ')) . $text, 0, 190), 'last_message_at' => now(), 'unread' => 0]
            + ($sender === 'staff' && ! $c->assigned_to ? ['assigned_to' => auth()->id()] : []));
        return $m;
    }

    /** channels without product cards get the suggestions as text lines */
    public static function plain(string $text, array $attachments): string
    {
        $lines = collect($attachments)->where('type', 'product')->map(fn ($p) => '• ' . $p['name'] . ($p['variant'] ? ' – ' . $p['variant'] : '') . ': ' . number_format((int) $p['price'], 0, ',', '.') . 'đ ' . $p['url']);
        return trim($text . ($lines->isNotEmpty() ? "\n" . $lines->implode("\n") : ''));
    }

    private static function deliver(Conversation $c, string $text): array
    {
        $to = $c->identity?->external_id;
        return match ($c->channel) {
            'web' => ['sent', null, null],
            'zalo' => Oa::sendText($to, $text),
            'facebook', 'instagram' => Meta::sendText((string) $to, $text),
            'tiktok' => TikTokShop::sendChat($c, $text),
            default => ['failed', null, 'Kênh chưa hỗ trợ gửi'],
        };
    }
}
