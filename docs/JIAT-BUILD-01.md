# JIAT Monitor — Build 01 (Fase 1 fondasi, bagian pertama)

Status: DRAFT Rev. 01 · 2026-10-26 · basis: HLD/LLD v0.2 · tidak ada commit/push dilakukan.

## Isi
| Modul | Isi | Test |
|---|---|---|
| `Modules/Tenancy` | tn_companies, tn_company_user, tn_management_viewers, tn_sites, tn_contracts; `CompanyContext` (scoped), `CompanyScope` (tanpa konteks = 0 baris), `BelongsToCompany`, `SetActiveCompany` (alias `company.active`), Gate `holding-read` | `tests/Feature/Tenancy` (9) |
| `Modules/Boq` | boq_items, boq_schedules; `BoqWorkbookParser` (sheet RAB, header dideteksi), `BoqImporter` (tolak bila Σ ≠ value_dpp, tulis atomik, bobot Σ = 100) | `tests/Feature/Boq` (6, fixture SINTETIS) |
| `Modules/Intake/Support` | `MoneyParser`, `DateParser`, `Hamming`, `CorrectionParser`, `ReceiptValidator` (V1–V7) | `tests/Unit/Intake` (43) |

## Jalankan
    export PATH=$HOME/.local/php:$PATH     # PHP 8.4 statis + composer di ~/.local/php
    cd ~/apps/jiat-monitor
    php vendor/bin/phpunit tests/Feature/Tenancy tests/Feature/Boq tests/Unit/Intake
Hasil sesi ini: 58 test / 126 assertion hijau; ditambah Procurement + HealthCheck ERP asli: 248 hijau (migrasi baru tidak merusak ERP).
Rollback: hapus `Modules/{Tenancy,Boq,Intake}`, `tests/*/{Tenancy,Boq,Intake}`, `docs/JIAT-BUILD-01.md` (tidak ada file bersama yang diubah).

## Deviasi dari LLD (butuh keputusan/konfirmasi)
1. **Basis kode bukan yang LLD asumsikan.** Repo nyata: `Modules\<Nama>` (bukan `app/Modules`), PHPUnit (bukan Pest), SPA vanilla (bukan Livewire 3), dan `core_company` single-tenant (`Company::current()` = baris pertama). Modul baru mengikuti CONVENTIONS.md; layar "IntakeReview" harus ditulis di SPA, bukan Livewire.
2. **`boq_items.code` unik per kontrak tidak cukup**: RAB memakai penomoran lokal ("1","2") di bawah tiap induk. Disimpan kode jalur penuh (`II.A.1`) + `code_printed` apa adanya.
3. **MV `mv_dashboard_progress` (LLD §8) join `level = 0`** tetapi `progress_items` berada di item daun. Pada importer, induk = jumlah anak, bobot induk = Σ bobot anak, jadi MV harus join ke daun (`is_leaf`) atau progres dirollup dulu. Perlu diputuskan sebelum Fase 3.
4. `weight_pct` numeric(9,6), bukan (7,4): pembulatan 4 desimal pada ratusan item membuat Σ ≠ 100.
5. `MoneyParser`: LLD membuang semua titik sebagai ribuan ("245.50" → 24550). Di sini ambigu → `null` (sistem tidak menebak angka).
6. Pemangkasan Payroll/Hr/Tax/Commercial **belum** dilakukan: modul ERP saling bergantung; perlu analisis dependensi dulu (Fase 1 berikutnya).

## Belum dibuat / belum terbukti
- Importer belum diuji pada workbook Keerom `versi_2_RAPI` asli (file tidak ada di host). Letakkan di `tests/fixtures/boq/` dan nilai DPP kontrak yang benar.
- V8–V10 (sha256, pHash 90 hari, trigram vendor), webhook Telegram, job OCR, vendor_invoices: Fase 2.
- Test PostgreSQL: suite memakai SQLite memori; MV dan trigram butuh Postgres (di VPS).
