- [{{ $task['label'] }}]({{ $task['url'] }})
@if(! empty($task['description']))
<br>{{ $task['description'] }}
@endif
@if(! empty($task['checklist_progress']))
<br>☑ {{ $task['checklist_progress'] }} checklist items completed
@endif
