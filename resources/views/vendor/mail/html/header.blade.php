@props(['url'])
@php
    $logoSetting = \App\Models\SystemSetting::where('key', 'app_logo')->value('value');
    $logoUrl = $logoSetting ? url($logoSetting) : null;
@endphp
<tr>
<td class="header">
<a href="{{ $url }}" style="display: inline-block;">
@if ($logoUrl)
<img src="{{ $logoUrl }}" class="logo" alt="{{ config('app.name') }} Logo" style="width: auto; max-height: 75px; border-radius: 12px;">
@else
{{ config('app.name') }}
@endif
</a>
</td>
</tr>
