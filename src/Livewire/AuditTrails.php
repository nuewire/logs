<?php

declare(strict_types=1);

namespace Nuewire\Logs\Livewire;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Schema;
use Livewire\Component;
use Livewire\WithPagination;
use Nuewire\Logs\Concerns\InteractsWithLogsPage;
use Throwable;

final class AuditTrails extends Component
{
    use InteractsWithLogsPage;
    use WithPagination;

    public string $locale = 'id';
    public string $search = '';
    public string $logName = '';
    public string $event = '';
    public int $perPage = 25;
    public ?int $selectedId = null;

    public function mount(?string $locale = null): void
    {
        $this->ensureAuthorized('logs.audit.view');
        $this->locale = $this->resolveLocale($locale);
        $this->rememberLocale($this->locale);
        $this->perPage = max(10, min(100, (int) config('nuewire.logs.audit.per_page', 25)));
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

    public function updatedLogName(): void
    {
        $this->resetPage();
    }

    public function updatedEvent(): void
    {
        $this->resetPage();
    }

    public function select(int $id): void
    {
        $this->ensureAuthorized('logs.audit.view');
        $this->selectedId = $id;
    }

    public function closeDetails(): void
    {
        $this->selectedId = null;
    }

    public function deleteSelected(): void
    {
        $this->ensureAuthorized('logs.audit.delete');

        if ($this->selectedId === null || ! $this->tableReady()) {
            return;
        }

        $this->model()->newQuery()->whereKey($this->selectedId)->delete();
        $this->selectedId = null;
        $this->resetPage();
    }

    public function render()
    {
        $this->ensureAuthorized('logs.audit.view');
        $ready = $this->tableReady();
        $activities = null;
        $selected = null;
        $logNames = [];
        $events = [];

        if ($ready) {
            $model = $this->model();
            $query = $model->newQuery()->orderByDesc($model->getKeyName());

            if ($this->search !== '') {
                $search = '%'.$this->search.'%';
                $query->where(static function ($builder) use ($search): void {
                    $builder->where('description', 'like', $search)
                        ->orWhere('subject_type', 'like', $search)
                        ->orWhere('causer_type', 'like', $search);
                });
            }

            if ($this->logName !== '') {
                $query->where('log_name', $this->logName);
            }

            if ($this->event !== '' && $this->hasColumn('event')) {
                $query->where('event', $this->event);
            }

            $activities = $query->paginate($this->perPage);
            $activities->setCollection($activities->getCollection()->map(fn (Model $activity): array => $this->serialize($activity)));
            $logNames = $model->newQuery()->whereNotNull('log_name')->distinct()->orderBy('log_name')->pluck('log_name')->filter()->values()->all();

            if ($this->hasColumn('event')) {
                $events = $model->newQuery()->whereNotNull('event')->distinct()->orderBy('event')->pluck('event')->filter()->values()->all();
            }

            if ($this->selectedId !== null) {
                $record = $model->newQuery()->find($this->selectedId);
                $selected = $record instanceof Model ? $this->serialize($record, true) : null;
            }
        }

        return view('nuewire-logs::livewire.audit-trails', [
            'ready' => $ready,
            'activities' => $activities,
            'selected' => $selected,
            'logNames' => $logNames,
            'events' => $events,
            'localeOptions' => $this->localeOptions(),
        ]);
    }

    private function model(): Model
    {
        $class = (string) config('activitylog.activity_model', 'Spatie\\Activitylog\\Models\\Activity');

        /** @var Model $model */
        $model = app($class);

        return $model;
    }

    private function tableReady(): bool
    {
        try {
            $model = $this->model();

            return Schema::connection($model->getConnectionName())->hasTable($model->getTable());
        } catch (Throwable) {
            return false;
        }
    }

    private function hasColumn(string $column): bool
    {
        try {
            $model = $this->model();

            return Schema::connection($model->getConnectionName())->hasColumn($model->getTable(), $column);
        } catch (Throwable) {
            return false;
        }
    }

    /** @return array<string, mixed> */
    private function serialize(Model $activity, bool $detailed = false): array
    {
        $properties = $this->arrayValue($activity->getAttribute('properties'));
        $changes = $this->arrayValue($activity->getAttribute('attribute_changes'));

        if ($changes === [] && (isset($properties['attributes']) || isset($properties['old']))) {
            $changes = array_filter([
                'attributes' => $properties['attributes'] ?? null,
                'old' => $properties['old'] ?? null,
            ], static fn (mixed $value): bool => $value !== null);
        }

        return [
            'id' => (int) $activity->getKey(),
            'log_name' => (string) ($activity->getAttribute('log_name') ?? ''),
            'event' => (string) ($activity->getAttribute('event') ?? ''),
            'description' => (string) $activity->getAttribute('description'),
            'subject' => $this->relatedLabel($activity, 'subject'),
            'causer' => $this->relatedLabel($activity, 'causer'),
            'subject_type' => (string) ($activity->getAttribute('subject_type') ?? ''),
            'subject_id' => (string) ($activity->getAttribute('subject_id') ?? ''),
            'causer_type' => (string) ($activity->getAttribute('causer_type') ?? ''),
            'causer_id' => (string) ($activity->getAttribute('causer_id') ?? ''),
            'properties' => $detailed ? $properties : [],
            'changes' => $detailed ? $changes : [],
            'batch_uuid' => (string) ($activity->getAttribute('batch_uuid') ?? ''),
            'created_at' => $activity->getAttribute('created_at'),
        ];
    }

    /** @return array<string, mixed> */
    private function arrayValue(mixed $value): array
    {
        if ($value instanceof Collection) {
            return $value->all();
        }

        return is_array($value) ? $value : [];
    }

    private function relatedLabel(Model $activity, string $relation): string
    {
        try {
            $related = $activity->getRelationValue($relation);

            if (! $related instanceof Model) {
                return '—';
            }

            foreach (['name', 'email', 'title'] as $attribute) {
                $value = trim((string) $related->getAttribute($attribute));

                if ($value !== '') {
                    return $value.' · '.$related->getKey();
                }
            }

            return class_basename($related).' · '.$related->getKey();
        } catch (Throwable) {
            return '—';
        }
    }
}
