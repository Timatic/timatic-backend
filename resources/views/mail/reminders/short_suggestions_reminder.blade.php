<p>
    {{ trans_choice('messages.suggestions_reminder.count', $suggestionsCount, ['count' => $suggestionsCount ]) }}
    <a href="{{ $url }}">{{ trans('messages.suggestions_reminder.link') }}</a>
</p>
