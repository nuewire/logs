@php
    $t = static fn (string $key, array $replace = []): string => (string) trans("nuewire-logs::logs.{$key}", $replace, $locale);
    $canDelete = ! app()->bound('nuewire.acl.enabled') || (auth()->user()?->can('logs.system.delete') ?? false);
    $levelClass = static fn (string $level): string => in_array($level, ['ERROR','CRITICAL','ALERT','EMERGENCY'], true) ? 'nwl__badge--bad' : (in_array($level, ['WARNING','NOTICE'], true) ? 'nwl__badge--warn' : (in_array($level, ['INFO','DEBUG'], true) ? 'nwl__badge--info' : ''));
    $formatBytes = static function (int $bytes): string { $units=['B','KB','MB','GB']; $index=0; $value=max(0,$bytes); while($value>=1024&&$index<count($units)-1){$value/=1024;$index++;} return number_format($value,$index===0?0:1).' '.$units[$index]; };
@endphp
<div class="nwl" lang="{{ $locale }}" wire:key="nuewire-system-logs">
    @include('nuewire-logs::livewire._styles')
    <section class="nwl__shell">
        <header class="nwl__head"><div><h2 class="nwl__title">{{ $t('system.title') }}</h2><p class="nwl__lead">{{ $t('system.lead') }}</p></div><div class="nwl__locale"><label for="nwl-system-locale">{{ $t('language') }}</label><select id="nwl-system-locale" wire:model.live="locale">@foreach($localeOptions as $code => $label)<option value="{{ $code }}">{{ $label }}</option>@endforeach</select></div></header>
        <div class="nwl__body">
            @if($files === [])<div class="nwl__notice">{{ $t('system.no_files') }}</div>@else
                <div class="nwl__filters nwl__filters--system">
                    <div><label for="nwl-system-file">{{ $t('system.file') }}</label><select id="nwl-system-file" wire:model.live="selectedFile">@foreach($files as $file)<option value="{{ $file['id'] }}">{{ $file['name'] }}</option>@endforeach</select></div>
                    <div><label for="nwl-system-level">{{ $t('system.level') }}</label><select id="nwl-system-level" wire:model.live="level"><option value="">{{ $t('common.all') }}</option>@foreach(['DEBUG','INFO','NOTICE','WARNING','ERROR','CRITICAL','ALERT','EMERGENCY','RAW'] as $item)<option value="{{ $item }}">{{ $item }}</option>@endforeach</select></div>
                    <div><label for="nwl-system-lines">{{ $t('system.lines') }}</label><select id="nwl-system-lines" wire:model.live="lines">@foreach([100,250,500,1000,2000] as $count)<option value="{{ $count }}">{{ $count }}</option>@endforeach</select></div>
                    <div><label for="nwl-system-search">{{ $t('common.search') }}</label><input id="nwl-system-search" type="search" wire:model.live.debounce.350ms="search"></div>
                </div>
                <div class="nwl__actions" style="margin-bottom:14px"><button class="nwl__button nwl__button--primary" type="button" wire:click="refreshLogs">{{ $t('system.refresh') }}</button>@if($canDelete)<button class="nwl__button nwl__button--danger" type="button" wire:click="clearSelected" wire:confirm="{{ $t('common.confirm_delete') }}">{{ $t('system.clear') }}</button>@endif</div>
                @if($statusMessage)<div class="nwl__notice {{ $statusMessage === 'cleared' ? 'nwl__notice--success' : 'nwl__notice--danger' }}" style="margin-bottom:14px">{{ $t('system.'.$statusMessage) }}</div>@endif
                @if($result['file'])<div class="nwl__filemeta"><span class="nwl__badge">{{ $result['file']['name'] }}</span><span class="nwl__badge">{{ $t('system.size', ['size' => $formatBytes((int)$result['file']['size'])]) }}</span><span class="nwl__badge">{{ $t('system.modified', ['time' => date('Y-m-d H:i:s', (int)$result['file']['modified_at'])]) }}</span>@if($result['truncated'])<span class="nwl__badge nwl__badge--warn">{{ $t('system.truncated', ['lines' => $lines]) }}</span>@endif</div>@endif
                <div class="nwl__entries">
                    @forelse($result['entries'] as $index => $entry)<article class="nwl__entry" wire:key="system-entry-{{ $index }}"><div class="nwl__entryhead"><span class="nwl__badge {{ $levelClass($entry['level']) }}">{{ $entry['level'] }}</span>@if($entry['environment'] !== '')<span>{{ $entry['environment'] }}</span>@endif<span class="nwl__entrytime">{{ $entry['datetime'] ?: '—' }}</span></div><pre>{{ $entry['message'] }}</pre></article>@empty<div class="nwl__empty">{{ $t('system.no_entries') }}</div>@endforelse
                </div>
            @endif
        </div>
    </section>
</div>
