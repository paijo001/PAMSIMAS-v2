# Solusi cetak rekening ke printer lokal Windows

File `cetak_rekening_5000_checkbox.php` tidak boleh diubah. Logika di file itu memakai UNC path seperti:

```php
$path = "//" . $computer_name . "/" . $printer_name;
@copy($file, $path);
```

Ini hanya berhasil bila server mempunyai akses langsung ke shared printer Windows. Saat server dipindah ke Linux, method itu tidak aman dan tidak bisa dipakai karena Linux tidak bisa mengakses printer lokal Windows seperti halnya Windows PC.

## Solusi yang benar

### 1) Server Linux hanya mengirim data rekening
Server Linux cukup menyiapkan data rekening dalam format HTML/JSON. Ketika user di PC Windows membuka halaman cetak dari browser, browser Windows yang memilih printer lokal.

### 2) Client Windows melakukan print
Buka halaman `simulasi_cetak_rekening.php` dari browser Windows yang terhubung ke printer lokal. Setelah itu klik tombol `Cetak ke printer lokal` atau `window.print()` akan membuka dialog printer default Windows.

### 3) Printer dipilih dari PC client
Printer yang tercetak adalah printer lokal yang terpasang di Windows, bukan printer yang di-share dari Linux. Tidak ada lagi akses SMB/UNC dari server ke client printer.

## Arsitektur yang disarankan

- Linux server
  - menyimpan data pelanggan dan rekening
  - menghasilkan halaman HTML/JSON
- Windows client
  - membuka halaman di browser
  - memilih printer lokal Windows
  - melakukan print lewat browser print dialog

## Contoh buka halaman simulator

```text
http://SERVER-LINUX/simulasi_cetak_rekening.php?idpel=1234567890&nama=SUPRIYANTO&alamat=Jl. Mawar&bulan=OKTOBER%202026&stand_lalu=120&stand_skrg=131&tarif=1000&biaya_admin=5000&printer=EPSON%20LQ-2190
```

Tersedia opsi `auto=1` untuk otomatis memanggil `window.print()` saat halaman dibuka.

## Jika ingin cetak otomatis tanpa klik manual

Pada Windows client bisa dibuat aplikasi kecil / script PowerShell / desktop app yang:

1. memanggil halaman HTML dari server,
2. menunggu data siap,
3. membuka browser ke halaman itu,
4. mengirim print command ke printer lokal Windows.

Ini tetap aman karena tugas cetak diproses di Windows, bukan di Linux.

## Kesimpulan

- Jangan mencoba `copy(...)` ke `//PC-NAMA/PRINTER-NAMA` dari Linux.
- Gunakan browser di Windows sebagai titik cetak.
- Halaman simulator yang tersedia di `simulasi_cetak_rekening.php` adalah cara paling sederhana dan aman.
