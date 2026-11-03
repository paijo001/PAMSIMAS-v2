# Panduan instalasi PAMSIMAS

## Persiapan

- PHP 8.1 atau lebih baru dengan ekstensi `pdo_mysql`, `fileinfo`, dan `mbstring`.
- MySQL atau MariaDB.
- Salin folder aplikasi ke document root web server, misalnya `C:\xampp\htdocs\PAMSIMAS`.

## Membuat database

Impor file `schema.sql` melalui phpMyAdmin (tab **Import**) atau jalankan:

```text
mysql -u root -p < schema.sql
```

Skema membuat database `pamsimas`, tabel aplikasi, satu baris pengaturan awal, dan pilihan kondisi meter `Normal`, `Macet`, serta `Rusak`.

Jika koneksi database berbeda dari nilai bawaan, atur environment variable `DB_HOST`, `DB_USER`, `DB_PASS`, dan `DB_NAME` sebelum menjalankan PHP. Nilai bawaan adalah host `localhost`, user `root`, password kosong, dan database `pamsimas`.

## Login pertama

Buka `http://localhost/PAMSIMAS/`. Akun `admin` dengan password `admin` dibuat otomatis saat aplikasi pertama dijalankan setelah skema diimpor. Segera masuk ke menu **User** dan ubah password akun tersebut.

## Pemakaian

Antarmuka menyesuaikan lebar layar ponsel dan desktop. Di layar ponsel, menu dapat digeser ke samping; tabel memiliki area gulir horizontal dan formulir disusun vertikal agar mudah disentuh.

1. Isi tarif dan biaya admin pada **Pengaturan tarif**. Tagihan dihitung sebagai `(stand sekarang - stand sebelumnya) × tarif per meter + biaya admin`.
2. Tambah data pelanggan dan kelompok/urut baca.
3. Pilih kelompok pada **Pembacaan meter**, masukkan stand sekarang, pilih kondisi meter, dan opsional ambil/pilih foto JPG, PNG, atau WebP. Pembacaan untuk bulan berjalan dapat diedit; foto sebelumnya dipertahankan bila foto baru tidak dipilih.
4. Pilih **Invoice** untuk membuka rekening, lalu **Cetak / Print**. Untuk printer Bluetooth, pasangkan printer melalui pengaturan ponsel dan pilih printer tersebut pada dialog cetak browser.

Foto disimpan sebagai BLOB di database. Sesuaikan `upload_max_filesize`, `post_max_size`, serta `max_allowed_packet` MySQL bila konfigurasi server lebih rendah daripada ukuran foto maksimum aplikasi (5 MB).

Pelanggan dengan riwayat pembacaan tidak dapat dihapus agar data rekening historis tetap tersimpan.
