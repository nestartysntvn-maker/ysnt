<?php
namespace App\Ai;

use Anthropic\Client as Anthropic;
use Anthropic\Core\Exceptions\APIConnectionException;
use Anthropic\Core\Exceptions\APIStatusException;
use Anthropic\Core\Exceptions\RateLimitException;
use App\Models\AiLog;
use App\Models\AppSetting;
use Illuminate\Support\Facades\Http;

/** Single entry point for every AI call. The provider is picked on the settings page; callers never change. */
class Ai
{
    public const PROVIDERS = ['claude' => 'Claude (Anthropic)', 'openai' => 'ChatGPT (OpenAI)', 'gemini' => 'Gemini (Google)', 'deepseek' => 'DeepSeek', 'qwen' => 'Qwen (Alibaba)'];
    public const DEFAULT_MODELS = ['claude' => 'claude-opus-5-5', 'deepseek' => 'deepseek-chat', 'qwen' => 'qwen-plus', 'openai' => '', 'gemini' => ''];
    public const GROUPS = ['advisor' => 'Tư vấn khách', 'staff' => 'Hỗ trợ nhân viên', 'internal' => 'Giải đáp nội bộ'];
    private const OPENAI_COMPAT = ['openai' => 'https://api.openai.com/v1', 'deepseek' => 'https://api.deepseek.com', 'qwen' => 'https://dashscope-intl.aliyuncs.com/compatible-mode/v1'];

    public static function provider(string $group): string { $p = (string) AppSetting::get("ai_provider_$group", 'claude'); return isset(self::PROVIDERS[$p]) ? $p : 'claude'; }
    public static function model(string $p): string { return (string) (AppSetting::get("ai_model_$p") ?: (self::DEFAULT_MODELS[$p] ?? '')); }
    public static function ready(string $group): bool { $p = self::provider($group); return AppSetting::get("ai_key_$p") && self::model($p) !== ''; }

    /**
     * @param array<int,array{role:string,content:string}> $messages user / assistant turns, oldest first
     * @param array{effort?:string,schema?:array,timeout?:float,max_tokens?:int,conversation_id?:int} $opt
     * @return array{status:string,text:?string,error:?string}  status ok | dry_run | error
     */
    public static function chat(string $task, string $group, string $system, array $messages, array $opt = []): array
    {
        $p = self::provider($group); $model = self::model($p); $key = (string) AppSetting::get("ai_key_$p");
        $log = ['task' => $task, 'provider' => $p, 'model' => $model ?: null, 'user_id' => auth()->id(), 'conversation_id' => $opt['conversation_id'] ?? null];
        if ($key === '' || $model === '') {
            AiLog::create($log + ['status' => 'dry_run', 'error' => 'Chưa có khóa API hoặc tên model']);
            return ['status' => 'dry_run', 'text' => null, 'error' => 'Chưa kết nối AI ' . self::PROVIDERS[$p]];
        }
        $messages = self::normalize($messages);
        if (! $messages) return ['status' => 'error', 'text' => null, 'error' => 'Không có câu hỏi'];
        $t0 = hrtime(true);
        try {
            [$text, $in, $out] = match ($p) {
                'claude' => self::claude($key, $model, $system, $messages, $opt),
                'gemini' => self::gemini($key, $model, $system, $messages, $opt),
                default => self::openaiCompat(self::OPENAI_COMPAT[$p], $key, $model, $system, $messages, $opt),
            };
            AiLog::create($log + ['status' => 'ok', 'input_tokens' => $in, 'output_tokens' => $out, 'ms' => (int) ((hrtime(true) - $t0) / 1e6)]);
            return ['status' => 'ok', 'text' => trim($text), 'error' => null];
        } catch (RateLimitException) {
            $err = 'Nhà cung cấp AI đang giới hạn lượt gọi, thử lại sau ít phút';
        } catch (APIConnectionException) {
            $err = 'Không kết nối được máy chủ AI';
        } catch (APIStatusException $e) {
            $err = 'AI báo lỗi: ' . $e->getMessage();
        } catch (\Throwable $e) {
            $err = $e->getMessage();
        }
        $err = mb_substr($err, 0, 480);
        AiLog::create($log + ['status' => 'error', 'error' => $err, 'ms' => (int) ((hrtime(true) - $t0) / 1e6)]);
        return ['status' => 'error', 'text' => null, 'error' => $err];
    }

    /** merge same-role neighbours, start on a user turn and end on one (no assistant prefill on current Claude models) */
    private static function normalize(array $messages): array
    {
        $out = [];
        foreach ($messages as $m) {
            $role = $m['role'] === 'assistant' ? 'assistant' : 'user'; $text = trim((string) $m['content']);
            if ($text === '') continue;
            if ($out && end($out)['role'] === $role) { $out[array_key_last($out)]['content'] .= "\n" . $text; continue; }
            if (! $out && $role === 'assistant') continue;
            $out[] = ['role' => $role, 'content' => $text];
        }
        while ($out && end($out)['role'] === 'assistant') array_pop($out);
        return $out;
    }

    private static function claude(string $key, string $model, string $system, array $messages, array $opt): array
    {
        $client = new Anthropic(apiKey: $key, requestOptions: ['timeout' => (float) ($opt['timeout'] ?? 60), 'maxRetries' => 1]);
        $args = ['maxTokens' => $opt['max_tokens'] ?? 16000, 'messages' => $messages, 'model' => $model,
            'system' => [['type' => 'text', 'text' => $system, 'cacheControl' => ['type' => 'ephemeral']]]];
        $out = [];
        if (preg_match('/^claude-(opus|sonnet|fable|mythos)-(4-[5-9]|5)/', $model)) $out['effort'] = $opt['effort'] ?? 'low';
        if (isset($opt['schema'])) $out['format'] = ['type' => 'json_schema', 'schema' => $opt['schema']];
        if ($out) $args['outputConfig'] = $out;
        if (preg_match('/^claude-(opus-5(-5)?|sonnet-5-5|fable-5-1)$/', $model)) { $args['betas'] = ['server-side-fallback-2026-07-01']; $args['fallbacks'] = 'default'; }
        $m = $client->beta->messages->create(...$args);
        if ($m->stopReason === 'refusal') throw new \RuntimeException('AI từ chối trả lời câu này');
        $text = '';
        foreach ($m->content as $b) if ($b->type === 'text') $text .= $b->text;
        return [$text, (int) $m->usage->inputTokens, (int) $m->usage->outputTokens];
    }

    private static function openaiCompat(string $base, string $key, string $model, string $system, array $messages, array $opt): array
    {
        $res = Http::timeout((int) ($opt['timeout'] ?? 60))->withToken($key)->post($base . '/chat/completions', array_filter([
            'model' => $model, 'messages' => array_merge([['role' => 'system', 'content' => $system]], $messages),
            'response_format' => isset($opt['schema']) ? ['type' => 'json_object'] : null]));
        if (! $res->ok()) throw new \RuntimeException(($res->json('error.message') ?? mb_substr($res->body(), 0, 300)) . ' (HTTP ' . $res->status() . ')');
        return [(string) $res->json('choices.0.message.content'), (int) $res->json('usage.prompt_tokens'), (int) $res->json('usage.completion_tokens')];
    }

    private static function gemini(string $key, string $model, string $system, array $messages, array $opt): array
    {
        $res = Http::timeout((int) ($opt['timeout'] ?? 60))->withHeaders(['x-goog-api-key' => $key])
            ->post('https://generativelanguage.googleapis.com/v1beta/models/' . rawurlencode($model) . ':generateContent', array_filter([
                'systemInstruction' => ['parts' => [['text' => $system]]],
                'contents' => array_map(fn ($m) => ['role' => $m['role'] === 'assistant' ? 'model' : 'user', 'parts' => [['text' => $m['content']]]], $messages),
                'generationConfig' => isset($opt['schema']) ? ['responseMimeType' => 'application/json'] : null]));
        if (! $res->ok()) throw new \RuntimeException(($res->json('error.message') ?? mb_substr($res->body(), 0, 300)) . ' (HTTP ' . $res->status() . ')');
        $text = collect($res->json('candidates.0.content.parts') ?? [])->pluck('text')->implode('');
        return [$text, (int) $res->json('usageMetadata.promptTokenCount'), (int) $res->json('usageMetadata.candidatesTokenCount')];
    }

    /** first {...} block of a reply, for providers that wrap JSON in prose */
    public static function json(?string $text): ?array
    {
        if (! $text) return null;
        $j = json_decode($text, true);
        if (is_array($j)) return $j;
        return preg_match('/\{.*\}/s', $text, $m) && is_array($j = json_decode($m[0], true)) ? $j : null;
    }
}
