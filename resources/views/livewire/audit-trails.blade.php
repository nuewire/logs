@php
    $t = static fn (string $key, array $replace = []): string => (string) trans("nuewire-logs::logs.{$key}", $replace, $locale);
    $canDelete = ! app()->bound('nuewire.acl.enabled') || (auth()->user()?->can('logs.audit.delete') ?? false);
@endphp
<div class="nwl" lang="{{ $locale }}" wire:key="nuewire-audit-trails">
    @include('nuewire-logs::livewire._styles')
    <section class="nwl__shell">
        <header class="nwl__head">
            <div><h2 class="nwl__title">{{ $t('audit.title') }}</h2><p class="nwl__lead">{{ $t('audit.lead') }}</p></div>
            <div class="nwl__locale"><label for="nwl-audit-locale">{{ $t('language') }}</label><select id="nwl-audit-locale" wire:model.live="locale">@foreach($localeOptions as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div>
        </header>
        <div class="nwl__body">
            @if(! $ready)
                <div class="nwl__notice">{{ $t('common.install') }}</div>
            @else
                <div class="nwl__filters">
                    <div><label for="nwl-audit-search">{{ $t('common.search') }}</label><input id="nwl-audit-search" type="search" wire:model.live.debounce.350ms="search" placeholder="{{ $t('audit.search_placeholder') }}"></div>
                    <div><label for="nwl-audit-log">{{ $t('audit.log_name') }}</label><select id="nwl-audit-log" wire:model.live="logName"><option value="">{{ $t('common.all') }}</option>@foreach($logNames as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select></div>
                    <div><label for="nwl-audit-event">{{ $t('audit.event') }}</label><select id="nwl-audit-event" wire:model.live="event"><option value="">{{ $t('common.all') }}</option>@foreach($events as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select></div>
                </div>
                <div class="nwl__tablewrap">
                    <table class="nwl__table">
                        <thead><tr><th>{{ $t('audit.time') }}</th><th>{{ $t('audit.event') }}</th><th>{{ $t('audit.description') }}</th><th>{{ $t('audit.subject') }}</th><th>{{ $t('audit.causer') }}</th><th>{{ $t('audit.log_name') }}</th><th></th></tr></thead>
                        <tbody>
                        @forelse($activities as $row)
                            <tr wire:key="audit-{{ $row['id'] }}">
                                <td class="nwl__nowrap">{{ $row['created_at']?->format('Y-m-d H:i:s') ?? '—' }}</td>
                                <td><span class="nwl__badge nwl__badge--info">{{ $row['event'] !== '' ? $row['event'] : '—' }}</span></td>
                                <td><span class="nwl__strong">{{ $row['description'] }}</span></td>
                                <td>{{ $row['subject'] }}</td><td>{{ $row['causer'] }}</td><td class="nwl__mono">{{ $row['log_name'] ?: '—' }}</td>
                                <td><button class="nwl__button" type="button" wire:click="select({{ $row['id'] }})">{{ $t('common.details') }}</button></td>
                            </tr>
                        @empty<tr><td colspan="7" class="nwl__empty">{{ $t('common.no_data') }}</td></tr>@endforelse
                        </tbody>
                    </table>
                </div>
                @if($activities->hasPages())<div class="nwl__pager"><span>{{ $t('common.page', ['current' => $activities->currentPage(), 'last' => $activities->lastPage()]) }}</span><div class="nwl__actions"><button class="nwl__button" wire:click="previousPage" @disabled($activities->onFirstPage())>{{ $t('common.previous') }}</button><button class="nwl__button" wire:click="nextPage" @disabled(! $activities->hasMorePages())>{{ $t('common.next') }}</button></div></div>@endif
                @if($selected)
                    <section class="nwl__details">
                        <div class="nwl__detailshead"><h3>#{{ $selected['id'] }} · {{ $selected['description'] }}</h3><div class="nwl__actions">@if($canDelete)<button class="nwl__button nwl__button--danger" type="button" wire:click="deleteSelected" wire:confirm="{{ $t('common.confirm_delete') }}">{{ $t('audit.delete') }}</button>@endif<button class="nwl__button" type="button" wire:click="closeDetails">{{ $t('common.close') }}</button></div></div>
                        <div class="nwl__detailsbody"><dl class="nwl__grid">
                            <div class="nwl__field"><dt>{{ $t('audit.event') }}</dt><dd>{{ $selected['event'] ?: '—' }}</dd></div><div class="nwl__field"><dt>{{ $t('audit.log_name') }}</dt><dd>{{ $selected['log_name'] ?: '—' }}</dd></div>
                            <div class="nwl__field"><dt>{{ $t('audit.subject') }}</dt><dd>{{ $selected['subject'] }}</dd></div><div class="nwl__field"><dt>{{ $t('audit.causer') }}</dt><dd>{{ $selected['causer'] }}</dd></div>
                            <div class="nwl__field nwl__field--full"><dt>{{ $t('audit.identity') }}</dt><dd class="nwl__mono">subject={{ $selected['subject_type'] }}#{{ $selected['subject_id'] ?: 'null' }} · causer={{ $selected['causer_type'] }}#{{ $selected['causer_id'] ?: 'null' }}</dd></div>
                            @if($selected['batch_uuid'] !== '')<div class="nwl__field nwl__field--full"><dt>{{ $t('audit.batch') }}</dt><dd class="nwl__mono">{{ $selected['batch_uuid'] }}</dd></div>@endif
                            <div class="nwl__field nwl__field--full"><dt>{{ $t('audit.changes') }}</dt><dd><pre class="nwl__code">{{ json_encode($selected['changes'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}' }}</pre></dd></div>
                            <div class="nwl__field nwl__field--full"><dt>{{ $t('audit.properties') }}</dt><dd><pre class="nwl__code">{{ json_encode($selected['properties'], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}' }}</pre></dd></div>
                        </dl></div>
                    </section>
                @endif
            @endif
        </div>
    </section>
</div>
