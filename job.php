<?php

namespace App\Jobs;

use App\Models\TelegramUser;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Nutgram\Laravel\Facades\Telegram;
use SergiX44\Nutgram\Telegram\Properties\ParseMode;
use Illuminate\Support\Facades\Log;

class TelegramMailer implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    private $offset;
    private $limit = 200;
    public $timeout = 300;

    private static $mail_sent = 0;
    private static $mail_failed = 0;

    /**
     * Create a new job instance.
     */
    public function __construct($offset = 0)
    {
        $this->offset = $offset;
    }

    /**
     * Execute the job.
     */
    public function handle(): void
    {
        $offset = $this->offset;
        $limit = $this->limit;
        $users = TelegramUser::skip($offset)->limit($limit)->get();

        $mail_message_id = Telegram::getGlobalData('mail_message_id');
        $mail_creator_id = Telegram::getGlobalData('mail_creator_id');
        $notification_message_id = Telegram::getGlobalData('notification_message_id');

        foreach ($users as $user) {
            try {
                Telegram::copyMessage(
                    chat_id: $user->user_id,
                    from_chat_id: $mail_creator_id,
                    message_id: $mail_message_id
                );
                self::$mail_sent++;
            } catch (\Throwable $e) {
                self::$mail_failed++;
                Log::error('Failed to send message to user ' . $user->user_id . ': ' . $e->getMessage());
            }
        }

        try {
            Telegram::editMessageText(
                chat_id: $mail_creator_id,
                message_id: $notification_message_id,
                text: "Sending message... (Sent: " . self::$mail_sent . ", Failed: " . self::$mail_failed . ")",
                parse_mode: ParseMode::HTML
            );
        } catch (\Throwable $e) {
            Log::error('Failed to edit message: ' . $e->getMessage());
        }

        $nextOffset = $offset + $limit;
        if (TelegramUser::skip($nextOffset)->exists()) {
            try {
                dispatch(new TelegramMailer($nextOffset))->delay(\DateInterval::createFromDateString('1 minutes'));
            } catch (\Throwable $e) {
                Log::error('Failed to dispatch next job: ' . $e->getMessage());
            }
        } else {
            try {
                Telegram::sendMessage(
                    chat_id: $mail_creator_id,
                    text: "Successfully sent.\n\nSent: " . self::$mail_sent . ", Failed: " . self::$mail_failed,
                    parse_mode: ParseMode::HTML
                );
            } catch (\Throwable $e) {
                Log::error('Failed to send final message: ' . $e->getMessage());
            }
        }
    }
}
