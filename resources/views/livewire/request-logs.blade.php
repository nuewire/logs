@php
    $t = static fn (string $key, array $replace = []): string => (string) trans("nuewire-logs::logs.{$key}", $replace, $locale);
    $canDelete = ! app()->bound('nuewire.acl.enabled') || (auth()->user()?->can('logs.requests.delete') ?? false);
    $statusClass = static fn (int $code): string => $code >= 500 ? 'nwl__badge--bad' : ($code >= 400 ? 'nwl__badge--warn' : ($code >= 200 && $code < 400 ? 'nwl__badge--ok' : 'nwl__badge--info'));
@endphp
<div class="nwl" lang="{{ $locale }}" wire:key="nuewire-request-logs">
    @include('nuewire-logs::livewire._styles')
    <section class="nwl__shell">
        <header class="nwl__head"><div><h2 class="nwl__title">{{ $t('requests.title') }}</h2><p class="nwl__lead">{{ $t('requests.lead') }}</p></div><div class="nwl__locale"><label for="nwl-request-locale">{{ $t('language') }}</label><select id="nwl-request-locale" wire:model.live="locale">@foreach($localeOptions as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div></header>
        <div class="nwl__body">
            @if(! $ready)<div class="nwl__notice">{{ $t('common.install') }}</div>@else
                <div class="nwl__filters">
                    <div><label for="nwl-request-search">{{ $t('common.search') }}</label><input id="nwl-request-search" type="search" wire:model.live.debounce.350ms="search" placeholder="{{ $t('requests.search_placeholder') }}"></div>
                    <div><label for="nwl-request-method">{{ $t('requests.method') }}</label><select id="nwl-request-method" wire:model.live="method"><option value="">{{ $t('common.all') }}</option>@foreach($methods as $name)<option value="{{ $name }}">{{ $name }}</option>@endforeach</select></div>
                    <div><label for="nwl-request-status">{{ $t('requests.status') }}</label><select id="nwl-request-status" wire:model.live="status"><option value="">{{ $t('common.all') }}</option>@foreach(['1xx','2xx','3xx','4xx','5xx'] as $range)<option value="{{ $range }}">{{ $range }}</option>@endforeach</select></div>
                </div>
                <div class="nwl__tablewrap"><table class="nwl__table"><thead><tr><th>{{ $t('requests.time') }}</th><th>{{ $t('requests.method') }}</th><th>{{ $t('requests.path') }}</th><th>{{ $t('requests.status') }}</th><th>{{ $t('requests.duration') }}</th><th>IP</th><th></th></tr></thead><tbody>
                @forelse($logs as $row)<tr wire:key="request-{{ $row->id }}"><td class="nwl__nowrap">{{ $row->created_at?->format('Y-m-d H:i:s') ?? '—' }}</td><td><span class="nwl__badge nwl__badge--info">{{ $row->method }}</span></td><td><div class="nwl__strong nwl__mono">{{ $row->path }}</div><div class="nwl__muted">{{ $row->route_name ?: '—' }}</div></td><td><span class="nwl__badge {{ $statusClass($row->status_code) }}">{{ $row->status_code }}</span></td><td><span class="{{ $row->duration_ms >= $slowThreshold ? 'nwl__badge nwl__badge--warn' : '' }}">{{ $row->duration_ms }} ms{{ $row->duration_ms >= $slowThreshold ? ' · '.$t('requests.slow') : '' }}</span></td><td class="nwl__mono">{{ $row->ip_address ?: '—' }}</td><td><button class="nwl__button" type="button" wire:click="select({{ $row->id }})">{{ $t('common.details') }}</button></td></tr>
                @empty<tr><td colspan="7" class="nwl__empty">{{ $t('common.no_data') }}</td></tr>@endforelse
                </tbody></table></div>
                @if($logs->hasPages())<div class="nwl__pager"><span>{{ $t('common.page', ['current' => $logs->currentPage(), 'last' => $logs->lastPage()]) }}</span><div class="nwl__actions"><button class="nwl__button" wire:click="previousPage" @disabled($logs->onFirstPage())>{{ $t('common.previous') }}</button><button class="nwl__button" wire:click="nextPage" @disabled(! $logs->hasMorePages())>{{ $t('common.next') }}</button></div></div>@endif
                @if($selected)
                    <section class="nwl__details"><div class="nwl__detailshead"><h3>{{ $selected['method'] }} {{ $selected['path'] }}</h3><div class="nwl__actions">@if($canDelete)<button class="nwl__button nwl__button--danger" type="button" wire:click="deleteSelected" wire:confirm="{{ $t('common.confirm_delete') }}">{{ $t('requests.delete') }}</button>@endif<button class="nwl__button" type="button" wire:click="closeDetails">{{ $t('common.close') }}</button></div></div><div class="nwl__detailsbody"><dl class="nwl__grid">
                        <div class="nwl__field nwl__field--full"><dt>{{ $t('requests.identity') }}</dt><dd class="nwl__mono">{{ $selected['request_id'] }}</dd></div><div class="nwl__field"><dt>{{ $t('requests.status') }}</dt><dd>{{ $selected['status_code'] }}</dd></div><div class="nwl__field"><dt>{{ $t('requests.duration') }}</dt><dd>{{ $selected['duration_ms'] }} ms</dd></div><div class="nwl__field"><dt>{{ $t('requests.route') }}</dt><dd class="nwl__mono">{{ $selected['route_name'] ?: '—' }}</dd></div><div class="nwl__field"><dt>{{ $t('requests.user') }}</dt><dd class="nwl__mono">{{ $selected['user_type'] ?: '—' }}{{ $selected['user_id'] ? '#'.$selected['user_id'] : '' }}</dd></div><div class="nwl__field nwl__field--full"><dt>{{ $t('requests.client') }}</dt><dd><span class="nwl__mono">{{ $selected['ip_address'] ?: '—' }}</span><br>{{ $selected['user_agent'] ?: '—' }}</dd></div>
                        @foreach(['query' => 'requests.query','payload' => 'requests.payload','headers' => 'requests.headers'] as $field => $label)<div class="nwl__field nwl__field--full"><dt>{{ $t($label) }}</dt><dd><pre class="nwl__code">{{ json_encode($selected[$field] ?? [], JSON_PRETTY_PRINT|JSON_UNESCAPED_SLASHES|JSON_UNESCAPED_UNICODE) ?: '{}' }}</pre></dd></div>@endforeach
                        @if($selected['exception_class'])<div class="nwl__field nwl__field--full"><dt>{{ $t('requests.exception') }}</dt><dd class="nwl__mono">{{ $selected['exception_class'] }}</dd></div>@endif
                    </dl></div></section>
                @endif
            @endif
        </div>
    </section>
</div>
