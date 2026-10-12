<?php
namespace App\Http\Controllers;

use App\Ai\Advisor;
use App\Inbox\Inbox;
use App\Models\AppSetting;
use App\Models\Conversation;
use App\Models\ConversationMessage;
use App\Models\CustomerIdentity;
use App\Support\Company;
use Illuminate\Http\Request;

/** Chat bubble on the website. The visitor key (random, kept in the browser) is the only handle on the conversation. */
class ChatController extends Controller
{
    public static function enabled(): bool { return AppSetting::get('chat_web_on', '1') === '1'; }

    private function conv(Request $r): ?Conversation
    {
        $k = (string) $r->input('k');
        return preg_match('/^[A-Za-z0-9]{24,64}$/', $k) ? Conversation::where('channel', 'web')->where('external_ref', $k)->first() : null;
    }

    private function out(Conversation $c, int $after): array
    {
        return $c->messages()->where('id', '>', $after)->whereIn('status', ['received', 'sent'])->get()->map(fn (ConversationMessage $m) => [
            'id' => $m->id, 'me' => $m->direction === 'in', 'by' => $m->sender, 'text' => $m->body, 'at' => $m->created_at->format('H:i'),
            'cards' => collect($m->attachments ?? [])->where('type', 'product')->values()->all()])->all();
    }

    public function poll(Request $r)
    {
        abort_unless(self::enabled(), 404);
        $c = $this->conv($r);
        return response()->json(['messages' => $c ? $this->out($c, (int) $r->query('after')) : [], 'human' => (bool) $c?->needs_human]);
    }

    public function send(Request $r)
    {
        abort_unless(self::enabled(), 404);
        $in = $r->validate(['k' => ['required', 'regex:/^[A-Za-z0-9]{24,64}$/'], 'text' => 'required|string|max:1000', 'after' => 'nullable|integer', 'human' => 'nullable|boolean']);
        $m = Inbox::receive('web', $in['k'], trim($in['text']), ['external_ref' => $in['k'], 'name' => 'Khách web'], false);
        $c = $m->conversation;
        if (! empty($in['human'])) {
            $c->update(['needs_human' => true]);
            Inbox::send($c, 'Em đã báo nhân viên, anh/chị đợi một chút ạ. Cần gấp, anh/chị gọi hotline ' . Company::get('hotline_sales') . '.', 'system');
        } elseif (Inbox::aiOn($c)) {
            Advisor::reply($c);
        } elseif (! $c->messages()->where('direction', 'out')->exists()) {
            Inbox::send($c, 'Cảm ơn anh/chị đã nhắn. Nhân viên sẽ trả lời ngay trong giờ làm việc. Cần gấp, anh/chị gọi hotline ' . Company::get('hotline_sales') . '.', 'system');
            $c->update(['needs_human' => true]);
        }
        return response()->json(['messages' => $this->out($c->fresh(), (int) ($in['after'] ?? 0)), 'human' => (bool) $c->fresh()->needs_human]);
    }

    /** visitor leaves a name + phone: the web identity joins the customer with that phone */
    public function contact(Request $r)
    {
        abort_unless(self::enabled(), 404);
        $in = $r->validate(['k' => ['required', 'regex:/^[A-Za-z0-9]{24,64}$/'], 'name' => 'required|string|min:2|max:100', 'phone' => ['required', 'regex:/^0\d{9}$/']],
            ['phone.regex' => 'Số điện thoại gồm 10 chữ số, bắt đầu bằng 0.']);
        $c = $this->conv($r) ?? abort(404);
        $ident = CustomerIdentity::resolve('web', $in['k'], $in['name'], $in['phone']);
        if (! $ident->customer_id) $ident->update(['customer_id' => \App\Models\Customer::firstOrCreate(['phone' => $in['phone']], ['name' => $in['name']])->id]);
        $c->update(['customer_identity_id' => $ident->id, 'customer_id' => $ident->fresh()->customer_id, 'name' => $in['name'], 'needs_human' => true]);
        Inbox::send($c, 'Em đã ghi số ' . $in['phone'] . '. Nhân viên sẽ gọi lại cho ' . $in['name'] . ' sớm nhất ạ.', 'system');
        return response()->json(['messages' => $this->out($c, (int) $r->input('after'))]);
    }
}
