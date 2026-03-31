CREATE DATABASE IF NOT EXISTS bidding_games
CHARACTER SET utf8mb4
COLLATE utf8mb4_unicode_ci;

USE bidding_games;

SET FOREIGN_KEY_CHECKS = 0;
DROP TABLE IF EXISTS bids;
DROP TABLE IF EXISTS users;
DROP TABLE IF EXISTS rooms;
DROP TABLE IF EXISTS questions;
SET FOREIGN_KEY_CHECKS = 1;

CREATE TABLE questions (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    question TEXT NOT NULL,
    correct_answer VARCHAR(255) NOT NULL,
    answer_aliases TEXT NULL,
    category VARCHAR(50) NOT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP
) ENGINE=InnoDB;

CREATE TABLE rooms (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    code VARCHAR(12) NOT NULL UNIQUE,
    status ENUM('waiting', 'playing', 'paused', 'finished') NOT NULL DEFAULT 'waiting',
    round_phase ENUM('bidding', 'answering', 'review') NOT NULL DEFAULT 'bidding',
    current_round INT UNSIGNED NOT NULL DEFAULT 0,
    current_question_id INT UNSIGNED NULL,
    answer_deadline_at TIMESTAMP NULL DEFAULT NULL,
    answer_time_remaining_seconds SMALLINT UNSIGNED NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    CONSTRAINT fk_rooms_current_question
        FOREIGN KEY (current_question_id) REFERENCES questions(id)
        ON DELETE SET NULL ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE users (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    username VARCHAR(30) NOT NULL,
    role ENUM('moderator', 'player') NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    score INT UNSIGNED NOT NULL DEFAULT 1000,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_room_username (room_id, username),
    INDEX idx_users_room_id (room_id),
    CONSTRAINT fk_users_room
        FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

CREATE TABLE bids (
    id INT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
    user_id INT UNSIGNED NOT NULL,
    room_id INT UNSIGNED NOT NULL,
    question_id INT UNSIGNED NOT NULL,
    bid_amount INT UNSIGNED NOT NULL,
    answer_text VARCHAR(255) NULL,
    is_correct TINYINT(1) NULL,
    score_delta INT NOT NULL DEFAULT 0,
    answered_at TIMESTAMP NULL DEFAULT NULL,
    evaluated_at TIMESTAMP NULL DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    UNIQUE KEY unique_bid_per_question (user_id, room_id, question_id),
    INDEX idx_bids_room_question (room_id, question_id),
    CONSTRAINT fk_bids_user
        FOREIGN KEY (user_id) REFERENCES users(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bids_room
        FOREIGN KEY (room_id) REFERENCES rooms(id)
        ON DELETE CASCADE ON UPDATE CASCADE,
    CONSTRAINT fk_bids_question
        FOREIGN KEY (question_id) REFERENCES questions(id)
        ON DELETE CASCADE ON UPDATE CASCADE
) ENGINE=InnoDB;

INSERT INTO questions (question, correct_answer, answer_aliases, category) VALUES

-- ================= HTML =================
-- ('HTML digunakan untuk membuat struktur dari apa?', 'halaman web', 'web|website', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat paragraf teks?', 'p', '<p>|tag p', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat judul utama di halaman?', 'h1', '<h1>|heading 1', 'HTML'),
('Tag HTML apa yang digunakan untuk menampilkan gambar?', 'img', '<img>|tag img', 'HTML'),
('Atribut apa yang digunakan untuk menentukan sumber gambar?', 'src', 'source|atribut src', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat link ke halaman lain?', 'a', '<a>|anchor', 'HTML'),
('Atribut apa yang menentukan tujuan link?', 'href', 'link tujuan|atribut href', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat daftar tidak berurutan?', 'ul', '<ul>|unordered list', 'HTML'),
('Tag apa yang digunakan untuk item di dalam list?', 'li', '<li>|list item', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat form input?', 'form', '<form>|tag form', 'HTML'),
('Tag apa yang digunakan untuk memasukkan data di form?', 'input', '<input>|form input', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat tombol?', 'button', '<button>|tombol', 'HTML'),
('Tag HTML apa yang digunakan untuk membuat tabel?', 'table', '<table>|tabel', 'HTML'),
('Tag untuk membuat baris dalam tabel adalah apa?', 'tr', '<tr>|table row', 'HTML'),
('Tag untuk membuat kolom dalam tabel adalah apa?', 'td', '<td>|table data', 'HTML'),
('Tag untuk judul kolom tabel adalah apa?', 'th', '<th>|table header', 'HTML'),
('Tag HTML apa yang digunakan untuk pindah baris?', 'br', '<br>|line break', 'HTML'),
-- ('Tag HTML apa yang digunakan untuk membuat garis?', 'hr', '<hr>|horizontal line', 'HTML'),
('Atribut apa yang digunakan sebagai teks cadangan atau alternatif pada gambar?', 'alt', 'teks alternatif|atribut alt', 'HTML'),
-- ('HTML merupakan singkatan dari apa?', 'hypertext markup language', 'kepanjangan html', 'HTML'),

-- ================= CSS =================
-- ('CSS digunakan untuk mengatur bagian apa dari halaman web?', 'tampilan', 'design|style', 'CSS'),
('Property CSS apa yang digunakan untuk mengubah warna teks?', 'color', 'warna teks', 'CSS'),
('Property CSS apa yang digunakan untuk mengubah warna latar belakang?', 'background-color', 'background', 'CSS'),
('Property CSS apa yang digunakan untuk mengatur ukuran teks?', 'font-size', 'font size', 'CSS'),
('Property CSS apa yang digunakan untuk mengatur jenis huruf?', 'font-family', 'font family', 'CSS'),
('Property CSS apa yang membuat teks menjadi tebal?', 'font-weight', 'bold', 'CSS'),
('Property CSS apa yang digunakan untuk membuat teks rata menjadi rata tengah, kiri atau kanan?', 'text-align', 'text align', 'CSS'),
('Nilai apa yang digunakan agar teks menjadi rata tengah?', 'center', 'tengah', 'CSS'),
('Property CSS apa yang digunakan untuk memberi jarak di dalam elemen?', 'padding', 'jarak dalam', 'CSS'),
('Property CSS apa yang digunakan untuk memberi jarak di luar elemen?', 'margin', 'jarak luar', 'CSS'),
('Property CSS apa yang digunakan untuk mengatur lebar elemen?', 'width', 'lebar', 'CSS'),
-- ('Property CSS apa yang digunakan untuk mengatur tinggi elemen?', 'height', 'tinggi', 'CSS'),
('Property CSS apa yang digunakan untuk memberi garis pada elemen?', 'border', 'garis', 'CSS'),
('Property CSS apa yang digunakan untuk membuat sudut elemen melengkung?', 'border-radius', 'rounded', 'CSS'),
('Property CSS apa yang digunakan untuk memberi bayangan pada elemen?', 'box-shadow', 'shadow', 'CSS'),
('Nilai display apa yang digunakan untuk membuat layout fleksibel?', 'flex', 'display flex', 'CSS'),
('Property CSS apa yang digunakan untuk mengatur posisi elemen?', 'position', 'posisi', 'CSS'),
('Nilai position apa yang membuat elemen tetap di layar?', 'fixed', 'position fixed', 'CSS'),
('Nilai apa yang digunakan untuk menyembunyikan elemen?', 'none', 'display none', 'CSS'),
-- ('CSS merupakan singkatan dari apa?', 'cascading style sheets', 'kepanjangan css', 'CSS'),

-- ================= JavaScript =================
-- ('JavaScript digunakan untuk membuat halaman web menjadi apa?', 'interaktif', 'dinamis', 'JavaScript'),
('Perintah JavaScript apa yang digunakan untuk menampilkan pesan ke layar?', 'alert', 'alert()', 'JavaScript'),
-- ('Perintah JavaScript apa yang digunakan untuk meminta input dari pengguna?', 'prompt', 'prompt()', 'JavaScript'),
('Perintah JavaScript apa yang digunakan untuk konfirmasi pilihan?', 'confirm', 'confirm()', 'JavaScript'),
('Bagaimana cara menulis komentar satu baris di JavaScript?', '//', 'double slash', 'JavaScript'),
('Salah satu keyword yang digunakan untuk membuat variabel di JavaScript?', 'let', 'let|var|const', 'JavaScript'),
('Keyword apa yang digunakan untuk membuat variabel tetap atau tidak bisa direassign ulang?', 'const', 'const keyword', 'JavaScript'),
-- ('Keyword lama untuk membuat variabel adalah apa?', 'var', 'var keyword', 'JavaScript'),
('Struktur apa yang digunakan untuk percabangan kondisi?', 'if', 'if statement', 'JavaScript'),
-- ('Selain if, digunakan apa untuk kondisi lain?', 'else', 'else statement', 'JavaScript'),
-- ('Perulangan apa yang sering digunakan untuk mengulang kode?', 'for', 'for loop', 'JavaScript'),
('Perulangan apa yang sering digunakan untuk array?', 'foreach', 'forEach()', 'JavaScript'),
('Perintah apa untuk mengambil elemen berdasarkan id?', 'getelementbyid', 'getElementById', 'JavaScript'),
-- ('Perintah modern untuk memilih elemen adalah apa?', 'queryselector', 'querySelector()', 'JavaScript'),
('Perintah untuk menampilkan log di console adalah apa?', 'console.log', 'consolelog', 'JavaScript'),
('Operator apa yang digunakan untuk membandingkan nilai?', '==', 'equals', 'JavaScript'),
('Operator apa yang membandingkan nilai dan tipe data?', '===', 'strict equal', 'JavaScript'),
('Event apa yang terjadi saat tombol diklik?', 'click', 'onclick', 'JavaScript'),
-- ('Fungsi apa untuk mengubah JSON menjadi object?', 'json.parse', 'json parse', 'JavaScript'),
-- ('Fungsi apa untuk mengubah object menjadi JSON?', 'json.stringify', 'json stringify', 'JavaScript'),

-- ================= PHP =================
-- ('PHP biasanya dijalankan di bagian mana?', 'server', 'backend', 'PHP'),
('Bagaimana cara memulai penulisan kode PHP?', '<?php', '<?php?>', 'PHP'),
-- ('Bagaimana cara mengakhiri kode PHP?', '?>', 'closing php', 'PHP'),
('Variabel dalam PHP diawali dengan simbol apa?', '$', 'dollar', 'PHP'),
('Perintah apa yang digunakan untuk menampilkan teks di PHP?', 'echo', 'echo()', 'PHP'),
-- ('Fungsi apa yang digunakan untuk menghitung jumlah data dalam array?', 'count', 'count()', 'PHP'),
('Superglobal apa yang digunakan untuk mengambil data POST?', '$_post', '$_POST', 'PHP'),
('Superglobal apa yang digunakan untuk mengambil data GET?', '$_get', '$_GET', 'PHP'),
('Operator apa yang digunakan untuk menggabungkan string?', '.', 'titik', 'PHP'),
-- ('Struktur apa yang digunakan untuk percabangan di PHP?', 'if', 'if php', 'PHP'),
('Perulangan apa yang sering digunakan untuk array di PHP?', 'foreach', 'foreach loop', 'PHP'),
-- ('Perulangan apa yang berjalan selama kondisi benar?', 'while', 'while loop', 'PHP'),
-- ('Fungsi apa yang digunakan untuk mengubah data menjadi JSON?', 'json_encode', 'json encode', 'PHP'),
-- ('Fungsi apa untuk membaca JSON menjadi array?', 'json_decode', 'json decode', 'PHP'),
('Fungsi apa untuk menghapus spasi di awal dan akhir teks?', 'trim', 'trim()', 'PHP'),
('Fungsi apa untuk mengubah huruf menjadi besar semua?', 'strtoupper', 'uppercase', 'PHP'),
('Fungsi apa untuk mengubah huruf menjadi kecil semua?', 'strtolower', 'lowercase', 'PHP'),
('Fungsi apa untuk menghitung panjang teks?', 'strlen', 'strlen()', 'PHP'),
-- ('PHP merupakan singkatan dari apa?', 'hypertext preprocessor', 'kepanjangan php', 'PHP'),
-- ('File PHP biasanya memiliki ekstensi apa?', '.php', 'php', 'PHP'),

-- ================= MySQL =================
-- ('MySQL digunakan untuk menyimpan apa?', 'data', 'basis data', 'Database'),
('Perintah SQL apa yang digunakan untuk mengambil data?', 'select', 'select statement', 'Database'),
('Perintah SQL apa yang digunakan untuk menambah data?', 'insert', 'insert into', 'Database'),
('Perintah SQL apa yang digunakan untuk mengubah data?', 'update', 'update statement', 'Database'),
('Perintah SQL apa yang digunakan untuk menghapus data?', 'delete', 'delete from', 'Database'),
('Perintah SQL apa yang digunakan untuk menyaring data?', 'where', 'where clause', 'Database'),
-- ('Perintah SQL apa yang digunakan untuk mengurutkan data?', 'order by', 'orderby', 'Database'),
('Perintah SQL apa yang digunakan untuk membatasi jumlah data?', 'limit', 'limit clause', 'Database'),
-- ('Fungsi SQL apa yang digunakan untuk menghitung jumlah data?', 'count', 'count()', 'Database'),
('Perintah SQL apa yang digunakan untuk menggabungkan tabel?', 'join', 'inner join', 'Database'),
('Perintah SQL apa yang digunakan untuk mencari data dengan pola?', 'like', 'like operator', 'Database'),
-- ('Simbol apa yang digunakan sebagai wildcard di SQL?', '%', 'persen', 'Database'),
('Perintah SQL apa yang digunakan untuk mengelompokkan data?', 'group by', 'groupby', 'Database'),
-- ('Perintah SQL apa yang digunakan untuk filter setelah group?', 'having', 'having clause', 'Database'),
('Perintah SQL apa untuk membuat database baru?', 'create database', 'create db', 'Database'),
('Perintah SQL apa untuk menghapus database?', 'drop database', 'drop db', 'Database'),
('Perintah SQL apa untuk memilih atau mengaktifkan database?', 'use', 'use db', 'Database'),
('Perintah SQL apa untuk melihat daftar tabel?', 'show tables', 'show table', 'Database'),
('Perintah SQL apa untuk melihat struktur tabel?', 'describe', 'desc', 'Database');
-- ('Primary key digunakan untuk apa?', 'membedakan data', 'id unik|unique id', 'Database');
