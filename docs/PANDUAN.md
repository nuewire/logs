# Panduan Nuewire Platform Logs

## Ruang lingkup

Package `nuewire/logs` menambahkan tiga halaman pada **Plugin → Platform** di `nuewire/platform` 2:

```text
Settings
└── Platform
    ├── System Logs
    ├── Request Logs
    └── Audit Trails
```

Pembagian tanggung jawabnya:

```text
Audit Trails   Perubahan bisnis dan tindakan pengguna yang perlu dapat ditelusuri.
Request Logs   Metadata request HTTP, status respons, durasi, dan korelasi request.
System Logs    Pembacaan file log Laravel yang dibatasi direktori dan jumlah baris.
```

Audit Trails memakai `spatie/laravel-activitylog`. Request Logs memakai tabel milik `nuewire/logs`. System Logs tidak menyalin file ke database; halaman hanya membaca bagian akhir file yang diizinkan.

## Instalasi

Melalui installer suite:

```bash
composer require --dev nuewire/installer
php artisan nuewire:install
```

Pilih **Log Platform**. Fitur ini akan menarik `nuewire/platform` sebagai dependensi fitur.

Instalasi langsung:

```bash
composer require nuewire/logs
php artisan nuewire:logs:install --migrate
php artisan optimize:clear
```

Perintah instalasi menerbitkan konfigurasi dan migrasi Spatie sesuai major version yang dipilih Composer. Migrasi request log dimuat otomatis oleh service provider Nuewire.

## Strategi pencatatan audit

Jangan menyamakan audit trail dengan debug log. Audit trail harus menjawab pertanyaan berikut:

1. Siapa yang melakukan tindakan?
2. Objek apa yang terdampak?
3. Tindakan bisnis apa yang terjadi?
4. Nilai relevan apa yang berubah?
5. Kapan tindakan terjadi?
6. Request mana yang berkaitan dengan tindakan tersebut?

Catat hasil domain yang bermakna seperti `approved`, `cancelled`, `permission.granted`, atau `settings.updated`. Hindari deskripsi generik seperti `button clicked` karena tidak cukup membantu investigasi.

## API audit yang direkomendasikan

Gunakan `Nuewire\Logs\Support\AuditLogger`. Wrapper ini mempertahankan API yang sama untuk Activitylog v4 dan v5 serta menyamarkan key sensitif sesuai `config/nuewire/logs.php`.

### Controller atau application service

```php
<?php

namespace App\Actions\Users;

use App\Models\User;
use Illuminate\Http\Request;
use Nuewire\Logs\Support\AuditLogger;

final class UpdateUser
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function handle(Request $request, User $user, array $validated): User
    {
        $old = $user->only(['name', 'email', 'status']);

        $user->fill($validated)->save();

        $this->audit->record(
            description: 'user updated',
            subject: $user,
            properties: [
                'old' => $old,
                'attributes' => $user->only(['name', 'email', 'status']),
                'request_id' => $request->header('X-Request-Id'),
            ],
            causer: $request->user(),
            event: 'updated',
            logName: 'users',
        );

        return $user;
    }
}
```

Urutannya penting: ambil snapshot lama, simpan perubahan, baru tulis audit. Jangan menulis audit sukses sebelum operasi domain benar-benar berhasil.

### Tindakan bisnis

```php
$oldStatus = $invoice->status;

$invoice->markAsPaid();

$audit->record(
    description: 'invoice marked as paid',
    subject: $invoice,
    properties: [
        'old' => ['status' => $oldStatus],
        'attributes' => [
            'status' => $invoice->status,
            'paid_at' => $invoice->paid_at?->toIso8601String(),
        ],
    ],
    causer: auth()->user(),
    event: 'paid',
    logName: 'invoices',
);
```

`event` sebaiknya stabil dan mudah difilter. `description` boleh lebih mudah dibaca manusia.

### Pengaturan tanpa subject Eloquent

```php
$audit->record(
    description: 'default mailer changed',
    properties: [
        'old' => ['mailer' => $oldMailer],
        'attributes' => ['mailer' => $newMailer],
    ],
    causer: auth()->user(),
    event: 'settings.updated',
    logName: 'mail-settings',
);
```

### Livewire action

```php
use Illuminate\Support\Facades\Auth;
use Nuewire\Logs\Support\AuditLogger;

public function save(AuditLogger $audit): void
{
    $validated = $this->validate();
    $old = $this->project->only(['name', 'status']);

    $this->project->update($validated);

    $audit->record(
        description: 'project updated',
        subject: $this->project,
        properties: [
            'old' => $old,
            'attributes' => $this->project->only(['name', 'status']),
        ],
        causer: Auth::user(),
        event: 'updated',
        logName: 'projects',
    );
}
```

Laravel container menyuntikkan `AuditLogger` ke public action Livewire. Bila aksi memakai transaksi database, tulis audit setelah perubahan di dalam transaksi yang sama atau setelah commit, sesuai kebutuhan konsistensi aplikasi.

### Listener untuk event domain

Pola event/listener menghindari duplikasi audit ketika tindakan yang sama dapat dipanggil dari UI, API, atau job.

```php
final class RecordOrderApprovedAudit
{
    public function __construct(
        private readonly AuditLogger $audit,
    ) {
    }

    public function handle(OrderApproved $event): void
    {
        $this->audit->record(
            description: 'order approved',
            subject: $event->order,
            properties: [
                'old' => ['status' => $event->previousStatus],
                'attributes' => ['status' => $event->order->status],
                'request_id' => $event->requestId,
            ],
            causer: $event->actor,
            event: 'approved',
            logName: 'orders',
        );
    }
}
```

Pastikan listener tidak dieksekusi dua kali untuk satu outcome bila event dapat di-retry. Untuk proses queue kritis, gunakan idempotency key atau identitas event pada properties.

## Logging otomatis pada model

Logging otomatis cocok untuk CRUD Eloquent sederhana. Ia kurang tepat bila perubahan database bukan batas domain yang sesungguhnya.

### Activitylog v4

```php
use Spatie\Activitylog\LogOptions;
use Spatie\Activitylog\Traits\LogsActivity;

class Product extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logOnly(['name', 'price', 'status'])
            ->logOnlyDirty()
            ->dontSubmitEmptyLogs();
    }
}
```

### Activitylog v5

```php
use Spatie\Activitylog\Models\Concerns\LogsActivity;
use Spatie\Activitylog\Support\LogOptions;

class Product extends Model
{
    use LogsActivity;

    public function getActivitylogOptions(): LogOptions
    {
        return LogOptions::defaults()
            ->useLogName('products')
            ->logOnly(['name', 'price', 'status'])
            ->logOnlyDirty()
            ->dontLogEmptyChanges();
    }
}
```

Gunakan allowlist atribut. `logAll()` dapat tanpa sengaja merekam password, token, encrypted payload, JSON besar, atau field internal yang tidak relevan.

## API Spatie langsung

```php
activity('orders')
    ->performedOn($order)
    ->causedBy(auth()->user())
    ->event('approved')
    ->withProperties([
        'old' => ['status' => 'pending'],
        'attributes' => ['status' => 'approved'],
    ])
    ->log('order approved');
```

Panggilan langsung tidak melewati sanitizer `AuditLogger`. Tanggung jawab penyaringan data berada pada pemanggil.

## Data yang tidak boleh dicatat

Jangan masukkan data berikut ke audit, request, maupun system log:

- password dan password confirmation;
- access token, refresh token, API key, secret, cookie, dan authorization header;
- CVV atau data autentikasi pembayaran;
- dokumen identitas lengkap bila tidak benar-benar dibutuhkan;
- payload besar yang dapat disimpan sebagai referensi ID saja;
- data pribadi yang tidak memiliki tujuan investigasi yang jelas.

Konfigurasi default menyamarkan nama key sensitif yang umum, tetapi sanitizer bukan pengganti desain data yang baik. Nilai rahasia yang diberi nama key tidak lazim masih dapat lolos.

## Request Logs

Middleware request log aktif global secara default. Data yang dicatat:

```text
request_id
method
path
route_name
status_code
duration_ms
ip_address
user_agent
user_type dan user_id
query yang sudah disanitasi
exception_class
created_at
```

Payload dan header nonaktif secara default. Ini keputusan sengaja untuk menekan risiko privasi, kebocoran secret, dan pertumbuhan database.

Konfigurasi utama:

```php
// config/nuewire/logs.php
'request' => [
    'enabled' => true,
    'auto_register_middleware' => true,
    'capture_query' => true,
    'capture_payload' => false,
    'capture_headers' => false,
    'retention_days' => 30,
    'slow_threshold_ms' => 1000,
],
```

Bila middleware didaftarkan manual pada `bootstrap/app.php`, nonaktifkan `auto_register_middleware` agar tidak terjadi pencatatan ganda.

## Korelasi request dan audit

Middleware menghasilkan UUID `X-Request-Id`. Sisipkan header itu ke properties audit untuk menghubungkan tindakan bisnis dengan metadata request:

```php
'request_id' => request()->header('X-Request-Id'),
```

Untuk job queue, teruskan request ID sebagai data job. Jangan mengandalkan objek request pada worker.

## System Logs

Reader hanya menemukan file dengan extension yang diizinkan di bawah root yang dikonfigurasi:

```php
'system' => [
    'paths' => [storage_path('logs')],
    'extensions' => ['log'],
    'tail_lines' => 500,
    'max_lines' => 2000,
],
```

Path dari browser tidak dipakai secara langsung. UI mengirim hash identitas file; server mencocokkannya kembali dengan hasil discovery. Symlink yang keluar dari root ditolak.

Hak `logs.system.delete` diperlukan untuk mengosongkan file. Berikan permission tersebut hanya kepada operator yang memang membutuhkannya.

## Permission ACL

```text
logs.audit.view
logs.audit.delete
logs.requests.view
logs.requests.delete
logs.system.view
logs.system.delete
```

Tanpa `nuewire/acl`, komponen tetap meminta pengguna terautentikasi. Gate tambahan dapat diatur:

```env
NUEWIRE_LOGS_GATE=manage-platform-logs
NUEWIRE_LOGS_GUARD=web
```

## Retensi

```bash
php artisan nuewire:logs:prune
php artisan nuewire:logs:prune --audit-days=365 --request-days=30
```

Jadwalkan dari `routes/console.php`:

```php
use Illuminate\Support\Facades\Schedule;

Schedule::command('nuewire:logs:prune')->dailyAt('02:30');
```

Retensi bukan sekadar keputusan teknis. Sesuaikan dengan kebutuhan incident response, privasi, kontrak, dan regulasi. Menyimpan semua log selamanya bukan default yang aman.

## Checklist integrasi pada package Nuewire lain

Saat menambahkan audit pada `nuewire/users`, `nuewire/acl`, `nuewire/mail`, atau package lain:

1. Tetapkan event domain yang stabil.
2. Ambil snapshot field yang diizinkan sebelum perubahan.
3. Jalankan perubahan dan pastikan berhasil.
4. Catat actor, subject, `old`, `attributes`, log name, dan request ID bila tersedia.
5. Jangan mencatat secret atau seluruh payload secara membabi buta.
6. Tambahkan test yang membuktikan audit dibuat hanya pada operasi sukses.
7. Pastikan retry job atau event tidak menghasilkan duplikasi tanpa kendali.

Contoh minimum:

```php
$before = $model->only(['status']);
$model->update(['status' => 'active']);

$audit->record(
    description: 'resource activated',
    subject: $model,
    properties: [
        'old' => $before,
        'attributes' => $model->only(['status']),
        'request_id' => request()->header('X-Request-Id'),
    ],
    causer: auth()->user(),
    event: 'activated',
    logName: 'resources',
);
```
