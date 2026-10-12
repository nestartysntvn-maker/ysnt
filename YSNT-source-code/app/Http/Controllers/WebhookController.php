<?php
namespace App\Http\Controllers;

use App\Inbox\Inbox;
use App\Meta\Meta;
use App\Models\AppSetting;
use App\Models\CustomerIdentity;
use App\Models\IntegrationLog;
use App\Zalo\Oa;
use Illuminate\Http\Request;

/** Inbound messages from Zalo OA and Facebook / Instagram. Unsigned or unconfigured calls are logged and ignored. */
class WebhookController extends Controller
{
    public function zalo(Request $r)
    {
        $raw = $r->getContent(); $j = json_decode($raw, true) ?? [];
        if (! Oa::signatureOk($raw, $r->header('X-ZEvent-Signature'), (string) ($j['timestamp'] ?? ''))) {
            IntegrationLog::write('zalo_webhook', (string) ($j['event_name'] ?? 'unknown'), 'skipped', AppSetting::get('zalo_oa_secret') ? 'Sai chữ ký' : 'Chưa nhập OA secret key nên bỏ qua');
            return response()->json(['ok' => true]);
        }
        $event = (string) ($j['event_name'] ?? ''); $uid = (string) ($j['sender']['id'] ?? $j['follower']['id'] ?? '');
        if ($uid === '') return response()->json(['ok' => true]);
        if ($event === 'user_submit_info') {
            // the customer shared phone / address from the OA form: link this Zalo user to the customer with that phone
            $info = $j['info'] ?? [];
            CustomerIdentity::resolve('zalo', $uid, $info['name'] ?? null, $info['phone'] ?? null);
        } elseif (str_starts_with($event, 'user_send_')) {
            $msg = $j['message'] ?? [];
            $att = collect($msg['attachments'] ?? [])->map(fn ($a) => ['type' => $a['type'] ?? 'file', 'url' => $a['payload']['url'] ?? ($a['payload']['thumbnail'] ?? null)])->filter(fn ($a) => $a['url'])->values()->all();
            $text = (string) ($msg['text'] ?? ($att ? '[' . ($att[0]['type']) . ']' : '[' . str_replace('user_send_', '', $event) . ']'));
            Inbox::receive('zalo', $uid, $text, ['message_id' => $msg['msg_id'] ?? null, 'attachments' => $att]);
        }
        return response()->json(['ok' => true]);
    }

    public function metaVerify(Request $r)
    {
        $token = (string) AppSetting::get('meta_verify_token');
        if ($r->query('hub_mode') === 'subscribe' && $token !== '' && hash_equals($token, (string) $r->query('hub_verify_token'))) return response((string) $r->query('hub_challenge'), 200);
        return response('forbidden', 403);
    }

    public function meta(Request $r)
    {
        $raw = $r->getContent();
        if (! Meta::signatureOk($raw, $r->header('X-Hub-Signature-256'))) {
            IntegrationLog::write('meta_webhook', 'message', 'skipped', AppSetting::get('meta_app_secret') ? 'Sai chữ ký' : 'Chưa nhập App secret nên bỏ qua');
            return response('ok');
        }
        $j = json_decode($raw, true) ?? [];
        $channel = ($j['object'] ?? '') === 'instagram' ? 'instagram' : 'facebook';
        foreach ($j['entry'] ?? [] as $entry) {
            foreach ($entry['messaging'] ?? [] as $ev) {
                $msg = $ev['message'] ?? null;
                if (! $msg || ! empty($msg['is_echo']) || empty($ev['sender']['id'])) continue;
                $att = collect($msg['attachments'] ?? [])->map(fn ($a) => ['type' => $a['type'] ?? 'file', 'url' => $a['payload']['url'] ?? null])->filter(fn ($a) => $a['url'])->values()->all();
                Inbox::receive($channel, (string) $ev['sender']['id'], (string) ($msg['text'] ?? ($att ? '[' . $att[0]['type'] . ']' : '[tin nhắn]')), ['message_id' => $msg['mid'] ?? null, 'attachments' => $att]);
            }
        }
        return response('ok');
    }
}
