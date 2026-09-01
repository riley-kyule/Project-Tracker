<x-mail::message>
# Welcome to {{ config('app.name') }}

Hi {{ $user->name }}, an account has been set up for you{{ $departmentName ? " in **{$departmentName}**" : '' }}{{ $roleName ? " as **{$roleName}**" : '' }}.

Sign in with your company Google account using this email address: **{{ $user->email }}**.

<x-mail::button :url="$url">
Sign in to {{ config('app.name') }}
</x-mail::button>

Thanks,<br>
{{ config('app.name') }}
</x-mail::message>
