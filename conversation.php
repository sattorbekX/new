<?php

namespace App\Telegram\Conversations;

use App\Jobs\TelegramMailer;
use SergiX44\Nutgram\Conversations\Conversation;
use SergiX44\Nutgram\Nutgram;

class SendToAllConversation extends Conversation
{
    public function start(Nutgram $bot)
    {
        $bot->sendMessage("Send your message: (Can be Image, Video, Audio, File or plain text message)");
        $this->next('secondStep');
    }

    public function secondStep(Nutgram $bot)
    {
        $messageId = $bot->asResponse()->sendMessage(
            text: "✅ Sending message...",
        )->message_id;
        $bot->setGlobalData('mail_message_id', $bot->messageId());
        $bot->setGlobalData('mail_creator_id', $bot->chatId());
        $bot->setGlobalData('mail_sent', 0);
        $bot->setGlobalData('mail_failed', 0);
        $bot->setGlobalData('notification_message_id', $messageId);

        dispatch(new TelegramMailer());
        $this->end();
    }
}
