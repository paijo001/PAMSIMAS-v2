CREATE DATABASE IF NOT EXISTS pamsimas
    CHARACTER SET utf8mb4
    COLLATE utf8mb4_unicode_ci;
USE pamsimas;

CREATE TABLE IF NOT EXISTS `user` (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(80) NOT NULL UNIQUE,
    password VARCHAR(255) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS setting (
    id TINYINT UNSIGNED NOT NULL PRIMARY KEY,
    biaya_admin DECIMAL(12,2) NOT NULL DEFAULT 0,
    rp_per_meter DECIMAL(12,2) NOT NULL DEFAULT 0,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
) ENGINE=InnoDB;

INSERT IGNORE INTO setting (id, biaya_admin, rp_per_meter) VALUES (1, 0, 0);

CREATE TABLE IF NOT EXISTS kondisi_meter (
    id INT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    nama VARCHAR(60) NOT NULL UNIQUE
) ENGINE=InnoDB;

INSERT IGNORE INTO kondisi_meter (id, nama) VALUES
    (1, 'Normal'), (2, 'Macet'), (3, 'Rusak');

CREATE TABLE IF NOT EXISTS data_pelanggan (
    idpelanggan VARCHAR(40) NOT NULL PRIMARY KEY,
    nama VARCHAR(150) NOT NULL,
    alamat TEXT NOT NULL,
    no_hp VARCHAR(30) NOT NULL DEFAULT '',
    kode_lokasi VARCHAR(60) NOT NULL DEFAULT '',
    kode_baca VARCHAR(60) NOT NULL,
    urut_baca INT NOT NULL DEFAULT 0,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    INDEX idx_pelanggan_baca (kode_baca, urut_baca)
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS data_pembacaan (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idpelanggan VARCHAR(40) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    alamat TEXT NOT NULL,
    no_hp VARCHAR(30) NOT NULL DEFAULT '',
    kode_lokasi VARCHAR(60) NOT NULL DEFAULT '',
    kode_baca VARCHAR(60) NOT NULL,
    urut_baca INT NOT NULL DEFAULT 0,
    standll DECIMAL(14,2) NOT NULL DEFAULT 0,
    standinill DECIMAL(14,2) NOT NULL DEFAULT 0,
    standini DECIMAL(14,2) NOT NULL,
    pemakaian DECIMAL(14,2) NOT NULL DEFAULT 0,
    kondisi_meter_id INT UNSIGNED NOT NULL,
    foto_meter LONGBLOB NULL,
    foto_mime VARCHAR(40) NULL,
    tahun SMALLINT UNSIGNED NOT NULL,
    bulan TINYINT UNSIGNED NOT NULL,
    biaya_admin DECIMAL(12,2) NOT NULL,
    rp_per_meter DECIMAL(12,2) NOT NULL,
    tagihan DECIMAL(14,2) NOT NULL,
    created_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    UNIQUE KEY uniq_pembacaan_per_bulan (idpelanggan, tahun, bulan),
    INDEX idx_pembacaan_bulan (tahun, bulan, kode_baca, urut_baca),
    CONSTRAINT fk_pembacaan_pelanggan FOREIGN KEY (idpelanggan)
        REFERENCES data_pelanggan (idpelanggan) ON UPDATE CASCADE,
    CONSTRAINT fk_pembacaan_kondisi FOREIGN KEY (kondisi_meter_id)
        REFERENCES kondisi_meter (id) ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE IF NOT EXISTS historis (
    id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
    idpelanggan VARCHAR(40) NOT NULL,
    nama VARCHAR(150) NOT NULL,
    alamat TEXT NOT NULL,
    nohp VARCHAR(30) NOT NULL DEFAULT '',
    kode_lokasi VARCHAR(60) NOT NULL DEFAULT '',
    standll DECIMAL(14,2) NOT NULL DEFAULT 0,
    standini DECIMAL(14,2) NOT NULL DEFAULT 0,
    tahun SMALLINT UNSIGNED NOT NULL,
    stand01 DECIMAL(14,2) NULL,
    stand02 DECIMAL(14,2) NULL,
    stand03 DECIMAL(14,2) NULL,
    stand04 DECIMAL(14,2) NULL,
    stand05 DECIMAL(14,2) NULL,
    stand06 DECIMAL(14,2) NULL,
    stand07 DECIMAL(14,2) NULL,
    stand08 DECIMAL(14,2) NULL,
    stand09 DECIMAL(14,2) NULL,
    stand10 DECIMAL(14,2) NULL,
    stand11 DECIMAL(14,2) NULL,
    stand12 DECIMAL(14,2) NULL,
    UNIQUE KEY uniq_historis_pelanggan_tahun (idpelanggan, tahun),
    CONSTRAINT fk_historis_pelanggan FOREIGN KEY (idpelanggan)
        REFERENCES data_pelanggan (idpelanggan) ON UPDATE CASCADE
) ENGINE=InnoDB;
