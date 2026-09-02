@component('mail::message')
# 🔴 Service Down

**{{ $service->label }}** ({{ $service->service_name }}) on `{{ $service->host }}` is not active.

@component('mail::panel')
Status: **{{ $result['status'] }}**
@if ($result['detail'])
Detail: {{ $result['detail'] }}
@endif
Time: {{ $result['checked_at']->toDateTimeString() }}
@endcomponent

@if ($result['raw'])
```
{{ $result['raw'] }}
```
@endif

@component('mail::button', ['url' => config('app.url').'/services'])
View Services Page
@endcomponent

Thanks,<br>
{{ config('app.name') }}
@endcomponent
