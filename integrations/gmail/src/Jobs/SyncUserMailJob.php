<?php

declare(strict_types=1);

namespace Timatic\Gmail\Jobs;

use App\Integrations\Suggestions\CustomerEmailDomainResolver;
use App\Models\Event;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Timatic\Gmail\Connector;
use Timatic\Gmail\Models\GmailConnection;
use Timatic\Gmail\OAuthService;
use Timatic\Gmail\Requests\GetMessageRequest;
use Timatic\Gmail\Requests\GetProfileRequest;
use Timatic\Gmail\Requests\ListHistoryRequest;
use Timatic\Gmail\ServiceProvider;

class SyncUserMailJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    /** A user may disconnect between the job being queued and it running. */
    public bool $deleteWhenMissingModels = true;

    public function __construct(private readonly GmailConnection $gmailConnection) {}

    public function handle(OAuthService $oauthService, CustomerEmailDomainResolver $customerResolver): void
    {
        $connection = $oauthService->refreshIfExpired($this->gmailConnection);

        if ($connection === null) {
            return;
        }

        $gmail = new Connector((string) $connection->access_token);

        // A first sync, or one after Gmail forgot the old point (404, after about a week), starts
        // from now: mail from before is not read back.
        if ($connection->history_id === null) {
            $this->startFromNow($gmail, $connection);

            return;
        }

        $messageIds = collect();
        $pageToken = null;

        do {
            $response = $gmail->send(new ListHistoryRequest($connection->history_id, $pageToken));

            if ($response->status() === 404) {
                $this->startFromNow($gmail, $connection);

                return;
            }

            if ($response->failed()) {
                return;
            }

            $page = $response->dto();
            $messageIds = $messageIds->merge($page->messageIds);
            $pageToken = $page->nextPageToken;
        } while ($pageToken !== null);

        foreach ($messageIds->unique() as $messageId) {
            $response = $gmail->send(new GetMessageRequest($messageId));

            // Each save replaces a draft by a new message, so an earlier save is usually gone by now.
            if ($response->failed()) {
                continue;
            }

            $message = $response->dto();

            if (! $message->isSent() && ! $message->isDraft()) {
                continue;
            }

            Event::firstOrCreate([
                'source_id' => ServiceProvider::SOURCE_ID,
                'external_id' => $message->threadId,
                'user_id' => $connection->user_id,
                'event_type_id' => $message->isSent() ? ServiceProvider::EVENT_TYPE_EMAIL_SENT : ServiceProvider::EVENT_TYPE_EMAIL_DRAFT_SAVED,
                'ended_at' => $message->writtenAt,
            ], [
                'title' => $message->subject === null ? null : mb_substr($message->subject, 0, 255),
                'customer_id' => $customerResolver->resolve($message->recipients)?->id,
                'started_at' => null,
            ]);
        }

        $connection->update(['history_id' => $page->historyId]);
    }

    private function startFromNow(Connector $gmail, GmailConnection $connection): void
    {
        $response = $gmail->send(new GetProfileRequest);

        if ($response->successful()) {
            $connection->update(['history_id' => $response->dto()]);
        }
    }
}
