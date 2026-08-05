<?php

namespace App\Services\Telegram;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Exception;

class TelegramBotService
{
    protected string $token;
    protected string $baseUrl;

    public function __construct()
    {
        $this->token = config('services.telegram.bot_token', env('TELEGRAM_BOT_TOKEN', ''));
        $this->baseUrl = "https://api.telegram.org/bot{$this->token}";
    }

    /**
     * Set webhook for the Telegram Bot API.
     */
    public function setWebhook(string $url): bool
    {
        try {
            $response = Http::post("{$this->baseUrl}/setWebhook", [
                'url' => $url,
                'allowed_updates' => ['message', 'callback_query', 'pre_checkout_query', 'my_chat_member']
            ]);
            return $response->json('ok', false);
        } catch (Exception $e) {
            Log::channel('telegram_errors')->error("SetWebhook failed: " . $e->getMessage());
            return false;
        }
    }

    /**
     * Send text messages.
     */
    public function sendMessage(int|string $chatId, string $text, array $options = []): array
    {
        return $this->callApi('sendMessage', array_merge([
            'chat_id' => $chatId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ], $options));
    }

    /**
     * Send photo messages.
     */
    public function sendPhoto(int|string $chatId, string $photo, string $caption = '', array $options = []): array
    {
        return $this->callApi('sendPhoto', array_merge([
            'chat_id' => $chatId,
            'photo' => $photo,
            'caption' => $caption,
            'parse_mode' => 'Markdown',
        ], $options));
    }

    /**
     * Send video messages.
     */
    public function sendVideo(int|string $chatId, string $video, string $caption = '', array $options = []): array
    {
        return $this->callApi('sendVideo', array_merge([
            'chat_id' => $chatId,
            'video' => $video,
            'caption' => $caption,
            'parse_mode' => 'Markdown',
        ], $options));
    }

    /**
     * Edit message text.
     */
    public function editMessageText(int|string $chatId, int $messageId, string $text, array $options = []): array
    {
        return $this->callApi('editMessageText', array_merge([
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'text' => $text,
            'parse_mode' => 'Markdown',
        ], $options));
    }

    /**
     * Edit inline keyboard markup.
     */
    public function editMessageReplyMarkup(int|string $chatId, int $messageId, array $replyMarkup = []): array
    {
        return $this->callApi('editMessageReplyMarkup', [
            'chat_id' => $chatId,
            'message_id' => $messageId,
            'reply_markup' => $replyMarkup,
        ]);
    }

    /**
     * Delete message.
     */
    public function deleteMessage(int|string $chatId, int $messageId): bool
    {
        try {
            $response = $this->callApi('deleteMessage', [
                'chat_id' => $chatId,
                'message_id' => $messageId,
            ]);
            return $response['ok'] ?? false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Answer callback queries from inline buttons.
     */
    public function answerCallbackQuery(string $callbackQueryId, string $text = '', bool $showAlert = false): bool
    {
        try {
            $response = $this->callApi('answerCallbackQuery', [
                'callback_query_id' => $callbackQueryId,
                'text' => $text,
                'show_alert' => $showAlert,
            ]);
            return $response['ok'] ?? false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Answer pre-checkout query for Telegram Stars payments.
     */
    public function answerPreCheckoutQuery(string $preCheckoutQueryId, bool $ok, string $errorMessage = ''): bool
    {
        try {
            $response = $this->callApi('answerPreCheckoutQuery', [
                'pre_checkout_query_id' => $preCheckoutQueryId,
                'ok' => $ok,
                'error_message' => $errorMessage,
            ]);
            return $response['ok'] ?? false;
        } catch (Exception $e) {
            return false;
        }
    }

    /**
     * Create Telegram Stars invoice link.
     */
    public function createInvoiceLink(string $title, string $description, string $payload, string $currency, array $prices): string
    {
        $response = $this->callApi('createInvoiceLink', [
            'title' => $title,
            'description' => $description,
            'payload' => $payload,
            'currency' => $currency,
            'prices' => $prices,
            'provider_token' => '', // Stars payments require empty provider token
        ]);

        if (!isset($response['result'])) {
            throw new Exception("Failed to generate Telegram invoice link: " . json_encode($response));
        }

        return $response['result'];
    }

    /**
     * Check if the bot is admin in the target channel and has post permissions.
     */
    public function checkBotAdminPermission(int|string $channelId): array
    {
        try {
            $botUserResponse = $this->callApi('getMe');
            $botId = $botUserResponse['result']['id'] ?? null;

            if (!$botId) {
                return ['status' => false, 'message' => "Bot ma'lumotlarini olishda xato."];
            }

            $response = $this->callApi('getChatMember', [
                'chat_id' => $channelId,
                'user_id' => $botId,
            ]);

            if (!isset($response['result'])) {
                return ['status' => false, 'message' => "Bot kanalga a'zo qilinmagan yoki kanal topilmadi."];
            }

            $member = $response['result'];
            $status = $member['status'] ?? '';

            if (in_array($status, ['administrator', 'creator'])) {
                // Check if can post messages
                $canPost = $member['can_post_messages'] ?? true; // creators can always post
                if ($status === 'administrator' && !$canPost) {
                    return ['status' => false, 'message' => "Bot kanalda admin, lekin post joylash huquqi berilmagan."];
                }
                return ['status' => true, 'message' => "Bot kanalda admin."];
            }

            return ['status' => false, 'message' => "Bot kanalda admin emas."];
        } catch (Exception $e) {
            return ['status' => false, 'message' => "Kanal ruxsatlarini tekshirishda xatolik: " . $e->getMessage()];
        }
    }

    /**
     * Perform Telegram Bot API request.
     */
    protected function callApi(string $method, array $data = []): array
    {
        if (empty($this->token)) {
            throw new Exception("Telegram Bot token is not configured.");
        }

        $url = "{$this->baseUrl}/{$method}";
        
        try {
            $response = Http::post($url, $data);
            
            $json = $response->json();
            if (!$response->successful() || (isset($json['ok']) && !$json['ok'])) {
                Log::channel('telegram_errors')->error("Telegram API call [{$method}] failed: " . $response->body(), [
                    'data' => $data,
                ]);
            }
            return $json;
        } catch (Exception $e) {
            Log::channel('telegram_errors')->error("Telegram HTTP request [{$method}] failed: " . $e->getMessage());
            throw $e;
        }
    }
}
