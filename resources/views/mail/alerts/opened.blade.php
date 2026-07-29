<x-mail::message>
# {{ $ruleName }}

**الخطورة:** {{ $severity }}
@if ($project)
**المشروع:** {{ $project }}
@endif

**{{ $metric }}** بلغ **{{ $observed }}**، والحدّ **{{ $threshold }}**{{ $window ? "، خلال {$window} دقيقة" : '' }}.

<x-mail::button :url="config('app.url') . '/alerts'">
افتح شاشة التنبيهات
</x-mail::button>

هذه رسالة آلية من مركز التحكم.
</x-mail::message>
