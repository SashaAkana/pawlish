<?php

namespace App\Http\Controllers;

use App\Models\Setting;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class TelegramWebhookController extends Controller
{
    public function handle(Request $request): JsonResponse
    {
        $expected = (string) config('services.telegram.webhook_secret', '');
        $incoming = (string) $request->header('X-Telegram-Bot-Api-Secret-Token', '');
        if ($expected === '' || ! hash_equals($expected, $incoming)) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        if ($request->input('message.text') !== '/start') {
            return response()->json(['status' => 'ignored']);
        }

        $settings = Setting::getSettings();
        $chatId = $request->input('message.chat.id');
        if (! $settings?->telegram_bot_token || ! $settings?->master_chat_id
            || $request->input('message.chat.type') !== 'private'
            || ! is_int($chatId) || (string) $chatId !== (string) $settings->master_chat_id) {
            return response()->json(['status' => 'forbidden'], 403);
        }

        try {
            $response = Http::connectTimeout(5)->timeout(10)->post(
                "https://api.telegram.org/bot{$settings->telegram_bot_token}/sendMessage",
                ['chat_id' => $chatId, 'text' => 'Бот подключён. Напоминания будут приходить сюда.']
            );
            if (! $response->successful() || $response->json('ok') !== true) {
                throw new \RuntimeException('Telegram rejected confirmation');
            }
        } catch (\Throwable) {
            Log::warning('Telegram confirmation failed.');
            return response()->json(['status' => 'error'], 502);
        }

        return response()->json(['status' => 'ok']);
    }
}
