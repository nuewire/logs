<?php

declare(strict_types=1);

namespace Nuewire\Logs\Livewire;

use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Nuewire\Logs\Concerns\InteractsWithLogsPage;
use Nuewire\Logs\Models\RequestLog;
use Throwable;

final class RequestLogs extends Component
{
    use InteractsWithLogsPage;
    use WithPagination;

    public string $locale = 'id';
    public string $search = '';
    public string $method = '';
    public string $status = '';
    public int $perPage = 25;
    public ?int $selectedId = null;

    public function mount(?string $locale = null): void
    {
        $this->ensureAuthorized('logs.requests.view');
        $this->locale = $this->resolveLocale($locale);
        $this->rememberLocale($this->locale);
        $this->perPage = max(10, min(100, (int) config('nuewire.logs.request.per_page', 25)));
    }

    public function updatedLocale(string $locale): void
    {
        $this->locale = $this->resolveLocale($locale);
        $this->rememberLocale($this->locale);
    }

    public function updatedSearch(): void
    {
        $this->resetPage();
    }

    public function updatedMethod(): void
    {
        $this->resetPage();
    }

    public function updatedStatus(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->ensureAuthorized('logs.requests.view');
        $this->selectedId = $id;
    }

    public function closeDetails(): void
    {
        $this->selectedId = null;
    }

    public function deleteSelected(): void
    {
        $this->ensureAuthorized('logs.requests.delete');

        if ($this->selectedId !== null && $this->tableReady()) {
            RequestLog::query()->whereKey($this->selectedId)->delete();
        }

        $this->selectedId = null;
        $this->resetPage();
    }

    public function render()
    {
        $this->ensureAuthorized('logs.requests.view');
        $ready = $this->tableReady();
        $logs = null;
        $selected = null;
        $methods = [];

        if ($ready) {
            $query = RequestLog::query()->orderByDesc('id');

            if ($this->search !== '') {
                $search = '%'.$this->search.'%';
                $query->where(static function ($builder) use ($search): void {
                    $builder->where('request_id', 'like', $search)
                        ->orWhere('path', 'like', $search)
                        ->orWhere('route_name', 'like', $search)
                        ->orWhere('ip_address', 'like', $search)
                        ->orWhere('user_id', 'like', $search);
                });
            }

            if ($this->method !== '') {
                $query->where('method', $this->method);
            }

            if (preg_match('/^[1-5]xx$/', $this->status) === 1) {
                $from = ((int) $this->status[0]) * 100;
                $query->whereBetween('status_code', [$from, $from + 99]);
            }

            $logs = $query->paginate($this->perPage);
            $methods = RequestLog::query()->distinct()->orderBy('method')->pluck('method')->filter()->values()->all();

            if ($this->selectedId !== null) {
                $selected = RequestLog::query()->find($this->selectedId)?->toArray();
            }
        }

        return view('nuewire-logs::livewire.request-logs', [
            'ready' => $ready,
            'logs' => $logs,
            'selected' => $selected,
            'methods' => $methods,
            'slowThreshold' => (int) config('nuewire.logs.request.slow_threshold_ms', 1000),
            'localeOptions' => $this->localeOptions(),
        ]);
    }

    private function tableReady(): bool
    {
        try {
            $model = new RequestLog();

            return Schema::connection($model->getConnectionName())->hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }
}
