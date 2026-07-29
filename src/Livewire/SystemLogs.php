<?php

declare(strict_types=1);

namespace Nuewire\Logs\Livewire;

use Livewire\Component;
use Nuewire\Logs\Concerns\InteractsWithLogsPage;
use Nuewire\Logs\Support\SystemLogReader;

final class SystemLogs extends Component
{
    use InteractsWithLogsPage;

    public string $locale = 'id';
    public string $selectedFile = '';
    public string $search = '';
    public string $level = '';
    public int $lines = 500;
    public ?string $statusMessage = null;

    public function mount(SystemLogReader $reader, ?string $locale = null): void
    {
        $this->ensureAuthorized('logs.system.view');
        $this->locale = $this->resolveLocale($locale);
        $this->rememberLocale($this->locale);
        $this->lines = max(100, min(
            (int) config('nuewire.logs.system.tail_lines', 500),
            (int) config('nuewire.logs.system.max_lines', 2000),
        ));
        $this->selectedFile = (string) ($reader->files()[0]['id'] ?? '');
    }

    public function updatedLocale(string $locale): void
    {
        $this->locale = $this->resolveLocale($locale);
        $this->rememberLocale($this->locale);
    }

    public function refreshLogs(): void
    {
        $this->ensureAuthorized('logs.system.view');
        $this->statusMessage = null;
    }

    public function clearSelected(): void
    {
        $this->ensureAuthorized('logs.system.delete');
        $this->statusMessage = app(SystemLogReader::class)->clear($this->selectedFile) ? 'cleared' : 'clear_failed';
    }

    public function render()
    {
        $this->ensureAuthorized('logs.system.view');
        $reader = app(SystemLogReader::class);
        $files = $reader->files();

        if ($this->selectedFile === '' && $files !== []) {
            $this->selectedFile = (string) $files[0]['id'];
        }

        $result = $reader->read($this->selectedFile, $this->lines, $this->search, $this->level);

        return view('nuewire-logs::livewire.system-logs', [
            'files' => $files,
            'result' => $result,
            'localeOptions' => $this->localeOptions(),
        ]);
    }
}
