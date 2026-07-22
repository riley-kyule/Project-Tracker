<x-mail::message>
# New task assigned

{{ $assigner->name }} assigned you **{{ $task->title }}**{{ $task->due_at ? ' — due '.$task->due_at->format('M j, Y') : '' }}.

<x-mail::button :url="$url">
View task
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
