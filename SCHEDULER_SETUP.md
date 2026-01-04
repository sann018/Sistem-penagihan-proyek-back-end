# Setup Daily Reminder Scheduler

## Fitur
✅ **Cek proyek pending setiap hari** jam 08:00 pagi  
✅ **Update/regenerate notifikasi** dengan progress terbaru  
✅ **Kirim reminder** ke admin/super_admin sampai proyek selesai 100%  
✅ **Auto-delete notifikasi** untuk proyek yang sudah selesai

---

## Cara Setup

### 1. Test Command Manual

Sebelum setup scheduler, test dulu command-nya:

```bash
php artisan notifikasi:daily-reminder
```

Output yang diharapkan:
```
=== DAILY PROJECT REMINDER ===
Memproses notifikasi proyek harian...
Target penerima: 2 admin/super_admin
Proyek pending: 5
✅ Selesai!
  - Notifikasi baru: 3
  - Notifikasi diupdate: 5
  - Notifikasi dihapus (proyek selesai): 2
```

---

### 2. Setup Cron Job (Production - Linux/Ubuntu)

#### a. Buka crontab editor:
```bash
crontab -e
```

#### b. Tambahkan baris ini:
```cron
* * * * * cd /path/to/sistem-monitoring-penagihan-back-end && php artisan schedule:run >> /dev/null 2>&1
```

**Ganti `/path/to/` dengan path absolut ke folder backend kamu!**

Contoh:
```cron
* * * * * cd /var/www/sistem-monitoring-penagihan-back-end && php artisan schedule:run >> /dev/null 2>&1
```

#### c. Save dan keluar (Ctrl+O, Enter, Ctrl+X di nano)

#### d. Verifikasi crontab:
```bash
crontab -l
```

---

### 3. Setup Task Scheduler (Production - Windows Server)

#### a. Buka **Task Scheduler** (taskschd.msc)

#### b. Create New Task:
- **Name:** Laravel Scheduler - Notifikasi Harian
- **Trigger:** Daily at 12:00 AM (atau jam berapa saja, scheduler Laravel akan handle waktu sebenarnya)
- **Action:** Start a program
  - **Program:** `C:\laragon\bin\php\php-8.4.2-Win32-vs16-x64\php.exe`
  - **Arguments:** `artisan schedule:run`
  - **Start in:** `D:\laragon\www\sistem-monitoring-penagihan-back-end`

#### c. Set additional settings:
- ✅ Run whether user is logged on or not
- ✅ Run with highest privileges
- ✅ Configure for Windows Server 2016/2019/2022

---

### 4. Development - Manual Run (Laragon)

Untuk development di Laragon, **TIDAK PERLU** setup cron/task scheduler.

Jalankan manual kapan saja untuk testing:
```bash
cd D:\laragon\www\sistem-monitoring-penagihan-back-end
php artisan notifikasi:daily-reminder
```

Atau buka terminal Laragon dan jalankan:
```bash
php artisan schedule:work
```
Command ini akan menjalankan scheduler setiap 1 menit (good untuk testing).

---

## Cara Kerja Scheduler

### Waktu Eksekusi
- **Jam 08:00 pagi** setiap hari (Timezone: Asia/Jakarta)
- Bisa diubah di `bootstrap/app.php` bagian `->dailyAt('08:00')`

### Logic Flow

```
1. Ambil semua admin & super_admin
   ↓
2. Ambil semua proyek dengan status = "pending"
   ↓
3. Untuk setiap proyek:
   ↓
   a. Hitung progress (0-100%)
      ↓
   b. Jika progress = 100%:
      → DELETE semua notifikasi proyek ini
      ↓
   c. Jika progress < 100%:
      ↓
      i. Cek tanggal jatuh tempo:
         - Overdue → notif "Jatuh Tempo" (prioritas 4)
         - H-1     → notif "H-1" (prioritas 4)
         - H-3     → notif "H-3" (prioritas 3)
         - H-7     → notif "H-7" (prioritas 2)
         ↓
      ii. Cek prioritas proyek:
          - Ada prioritas → notif "Proyek Prioritas"
          ↓
      iii. Untuk setiap notifikasi:
           - Jika sudah ada → UPDATE (judul, isi, metadata)
           - Jika belum ada → CREATE baru
```

### Update Metadata
Setiap hari, notifikasi akan diupdate dengan:
- **progress_persen**: Progress terbaru (0-100%)
- **days_to_deadline**: Sisa hari sampai jatuh tempo
- **tanggal_jatuh_tempo**: Tanggal deadline

---

## Testing & Monitoring

### 1. Check Scheduler Running
```bash
php artisan schedule:list
```

Output:
```
0 8 * * * notifikasi:daily-reminder .................. Next Due: 16 hours from now
```

### 2. Run Scheduler Once (Manual)
```bash
php artisan schedule:run
```

### 3. Monitor Log
```bash
tail -f storage/logs/laravel.log
```

Cari log:
```
[SCHEDULER] Daily reminder berhasil dijalankan
```

### 4. Test Notifikasi Updated
1. Jalankan `php artisan notifikasi:daily-reminder`
2. Buka web → halaman Notifikasi
3. Cek progress bar harus sesuai status proyek terbaru
4. Update progress proyek → jalankan lagi command → progress di notifikasi harus berubah

---

## Troubleshooting

### Command tidak ditemukan
```bash
php artisan list | grep notifikasi
```
Harus muncul:
```
notifikasi:daily-reminder  Reminder harian untuk proyek pending
notifikasi:demo            Buat demo notifikasi
```

### Scheduler tidak jalan
1. Cek crontab sudah benar
2. Cek path PHP benar
3. Cek permission folder storage/logs
4. Cek log: `storage/logs/laravel.log`

### Notifikasi tidak update
1. Cek proyek masih status "pending"
2. Cek progress proyek belum 100%
3. Jalankan manual: `php artisan notifikasi:daily-reminder`
4. Cek output command

---

## Customization

### Ubah Waktu Scheduler
Edit `bootstrap/app.php`:
```php
// Dari jam 08:00
->dailyAt('08:00')

// Ke jam 09:30
->dailyAt('09:30')

// Atau setiap 6 jam
->everySixHours()

// Atau setiap jam
->hourly()
```

### Ubah Timezone
```php
->timezone('Asia/Jakarta')  // Indonesia
->timezone('Asia/Singapore') // Singapore
->timezone('UTC')            // UTC
```

### Disable Scheduler
Comment out di `bootstrap/app.php`:
```php
->withSchedule(function (Schedule $schedule): void {
    // $schedule->command('notifikasi:daily-reminder')...
})
```

---

## Production Checklist

- [ ] Test command manual berhasil
- [ ] Setup cron job/task scheduler
- [ ] Verifikasi scheduler list
- [ ] Monitor log 24 jam pertama
- [ ] Cek notifikasi terupdate setiap hari
- [ ] Cek proyek selesai = notifikasi terhapus
- [ ] Setup alert jika scheduler gagal (opsional)

---

## FAQ

**Q: Apakah notifikasi akan spam user setiap hari?**  
A: Tidak. Sistem UPDATE notifikasi yang sudah ada, bukan buat baru. Jadi user tetap lihat 1 notifikasi per proyek, tapi isi & progress selalu terupdate.

**Q: Kapan notifikasi dihapus otomatis?**  
A: Saat proyek progress mencapai 100% (semua status hijau).

**Q: Bagaimana kalau server mati saat scheduler harus jalan?**  
A: Scheduler akan jalan lagi di waktu berikutnya (besok jam 8). Notifikasi akan tetap terupdate.

**Q: Bisa kirim email/SMS juga?**  
A: Bisa, tapi harus tambah logic di command untuk kirim email/SMS. Sekarang hanya notifikasi in-app.

**Q: Bagaimana kalau proyek tidak ada tanggal jatuh tempo?**  
A: Notifikasi deadline tidak dibuat. Hanya notifikasi prioritas (jika ada) yang dibuat.
