<?php

namespace App\Jobs;

use App\DataTransferObjects\UnusedSuggestion;
use App\Mail\UnusedSuggestionsMail;
use App\Models\User;
use App\Queries\UnusedSuggestionsPerWeek;
use Carbon\Carbon;
use Illuminate\Bus\Dispatcher;
use Illuminate\Bus\Queueable;
use Illuminate\Config\Repository;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\Mail;

class RemindUsersOfUnusedSuggestions implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function __construct() {}

    public function handle(
        Dispatcher $dispatcher,
        Repository $config
    ): void {
        $startOfWeek = Carbon::now(config('timatic.preferred_timezone'))->subWeek()->startOfWeek();
        $endOfWeek = Carbon::now(config('timatic.preferred_timezone'))->subWeek()->endOfWeek();

        $secondsDelay = 0;

        $userSuggestions = UnusedSuggestionsPerWeek::query($startOfWeek, $endOfWeek)
            ->get()
            ->mapInto(UnusedSuggestion::class)
            ->groupBy('user_id');

        foreach ($userSuggestions as $userId => $suggestions) {
            $hasSuggestionsWithTickets = $suggestions->contains(fn (UnusedSuggestion $suggestion) => ! is_null($suggestion->ticket_number));

            if ($hasSuggestionsWithTickets) {
                $content = $this->getContentOfReminderWithTicketNumbers($suggestions, $config);
            } else {
                $content = $this->getContentOfReminderWithoutTickets($suggestions, $config);
            }

            /** @var ?User $user */
            $user = User::query()->find($userId);
            assert(! is_null($user) && ! is_null($user->external_id));

            Mail::to($user->email)->send(
                new UnusedSuggestionsMail(content: $content)
            );
        }
    }

    private function getContentOfReminderWithTicketNumbers(
        Collection $suggestions,
        Repository $config
    ): string {
        $customers = $suggestions
            ->reject(fn (UnusedSuggestion $suggestion) => is_null($suggestion->ticket_number))
            ->groupBy('customer_id')
            ->map(function ($customerSuggestions) use ($config) {
                return (object) [
                    'hours' => floor($customerSuggestions->sum('duration_in_minutes') / 60),
                    'minutes' => $customerSuggestions->sum('duration_in_minutes') % 60,
                    'name' => $customerSuggestions->first()->customer_name,
                    'tickets' => $customerSuggestions
                        ->groupBy('ticket_number')
                        ->map(function ($ticketSuggestions) use ($config) {
                            $query = http_build_query(['date' => $ticketSuggestions->min('date')]);

                            return rtrim($config->get('app.frontend_url'), '/').'?'.$query;
                        }),
                ];
            })
            ->sortByDesc('hours');

        return Blade::render('mail.reminders.ticket_reminder', [
            'customers' => $customers,
        ]);
    }

    private function getContentOfReminderWithoutTickets(
        Collection $suggestions,
        Repository $config
    ): string {
        $query = http_build_query(['date' => $suggestions->min('date')]);
        $url = rtrim($config->get('app.frontend_url'), '/').'?'.$query;

        return Blade::render('mail.reminders.short_suggestions_reminder', [
            'url' => $url,
            'suggestionsCount' => $suggestions->count(),
        ]);
    }
}
