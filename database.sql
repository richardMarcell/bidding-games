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
('Tag HTML semantik untuk konten utama unik pada sebuah halaman adalah apa?', 'main', '<main>|tag main', 'HTML'),
('Atribut HTML untuk teks alternatif pada gambar adalah apa?', 'alt', 'alt attribute', 'HTML'),
('Tag HTML yang dipakai untuk membuat hyperlink adalah apa?', 'a', '<a>|anchor', 'HTML'),
('Atribut HTML untuk tujuan URL pada hyperlink adalah apa?', 'href', 'href attribute', 'HTML'),
('Tag HTML untuk mengelompokkan kontrol input dan mengirim data ke server adalah apa?', 'form', '<form>|tag form', 'HTML'),
('Atribut HTML untuk menampilkan petunjuk singkat di dalam input adalah apa?', 'placeholder', 'placeholder attribute', 'HTML'),
('Atribut HTML yang membuat field wajib diisi adalah apa?', 'required', 'required attribute', 'HTML'),
('Tag HTML untuk menampilkan gambar adalah apa?', 'img', '<img>|image tag', 'HTML'),
('Tag HTML untuk daftar tak berurutan adalah apa?', 'ul', '<ul>|unordered list', 'HTML'),
('Tag HTML untuk judul level paling besar adalah apa?', 'h1', '<h1>|heading 1', 'HTML'),

('Property CSS untuk mengatur distribusi item pada sumbu utama flex container adalah apa?', 'justify-content', 'justify content', 'CSS'),
('Nilai CSS untuk membuat elemen menjadi flex container adalah apa?', 'flex', 'display flex|display: flex', 'CSS'),
('At-rule CSS untuk responsive breakpoint adalah apa?', '@media', 'media query|media', 'CSS'),
('Property CSS untuk jarak di luar border elemen adalah apa?', 'margin', 'margin property', 'CSS'),
('Property CSS untuk jarak di dalam border elemen adalah apa?', 'padding', 'padding property', 'CSS'),
('Property CSS untuk mengatur warna teks adalah apa?', 'color', 'color property', 'CSS'),
('Property CSS untuk menambahkan bayangan ke kotak elemen adalah apa?', 'box-shadow', 'box shadow', 'CSS'),
('Property CSS untuk membuat sudut elemen melengkung adalah apa?', 'border-radius', 'border radius', 'CSS'),
('Property CSS untuk mengatur tumpukan layer elemen adalah apa?', 'z-index', 'z index', 'CSS'),
('Property CSS untuk mengatur ukuran huruf adalah apa?', 'font-size', 'font size', 'CSS'),

('Keyword JavaScript untuk membuat variabel yang tidak bisa di-reassign adalah apa?', 'const', 'constant', 'JavaScript'),
('Method JavaScript untuk mengubah string JSON menjadi object adalah apa?', 'json.parse()', 'json.parse|json parse', 'JavaScript'),
('Method JavaScript untuk memasang event handler pada elemen adalah apa?', 'addeventlistener', 'addEventListener|add event listener', 'JavaScript'),
('API browser JavaScript modern untuk HTTP request adalah apa?', 'fetch', 'fetch api', 'JavaScript'),
('Method JavaScript array untuk membuat array baru dari hasil transformasi setiap item adalah apa?', 'map', 'array.map|map()', 'JavaScript'),
('Method JavaScript event untuk mencegah aksi default browser adalah apa?', 'preventdefault()', 'preventDefault|prevent default', 'JavaScript'),
('Function JavaScript untuk menjalankan callback berulang tiap interval waktu adalah apa?', 'setinterval()', 'setInterval|set interval', 'JavaScript'),
('Method JavaScript untuk mengubah object atau array menjadi string JSON adalah apa?', 'json.stringify()', 'json.stringify|json stringify', 'JavaScript'),
('Keyword JavaScript yang biasa dipakai untuk function asynchronous bersama await adalah apa?', 'async', 'async keyword', 'JavaScript'),
('Property DOM JavaScript untuk mengambil isi teks murni sebuah elemen adalah apa?', 'textcontent', 'textContent|text content', 'JavaScript'),

('Simbol yang selalu digunakan di depan nama variabel pada PHP adalah apa?', '$', 'dollar sign|tanda dolar', 'PHP'),
('Keyword PHP untuk menampilkan output sederhana ke browser adalah apa?', 'echo', 'echo statement', 'PHP'),
('Superglobal PHP untuk mengambil data form dengan method POST adalah apa?', '$_post', 'post|$_POST', 'PHP'),
('Ekstensi database PHP yang dipakai project ini untuk koneksi MySQL adalah apa?', 'pdo', 'php data objects', 'PHP'),
('Keyword PHP untuk mendefinisikan function adalah apa?', 'function', 'function keyword', 'PHP'),
('Struktur perulangan PHP yang cocok untuk menelusuri array per item adalah apa?', 'foreach', 'foreach loop', 'PHP'),
('Function PHP untuk mengecek apakah variabel sudah ada dan tidak bernilai null adalah apa?', 'isset()', 'isset|isset function', 'PHP'),
('Statement PHP untuk memasukkan file satu kali saja adalah apa?', 'require_once', 'require once', 'PHP'),
('Method khusus PHP OOP yang dipanggil saat object dibuat adalah apa?', '__construct', 'construct|constructor', 'PHP'),
('Function PHP untuk mengubah array atau object menjadi JSON adalah apa?', 'json_encode()', 'json_encode|json encode', 'PHP'),

('Clause MySQL untuk mengurutkan hasil query adalah apa?', 'order by', 'order by clause', 'MySQL'),
('Clause MySQL untuk menyaring baris berdasarkan kondisi adalah apa?', 'where', 'where clause', 'MySQL'),
('Perintah MySQL untuk menambahkan data baru ke tabel adalah apa?', 'insert into', 'insert', 'MySQL'),
('Perintah MySQL untuk mengubah data yang sudah ada adalah apa?', 'update', 'update statement', 'MySQL'),
('Jenis join MySQL yang mengembalikan data yang cocok di kedua tabel secara default adalah apa?', 'inner join', 'join', 'MySQL'),
('Function MySQL untuk menghitung jumlah baris adalah apa?', 'count()', 'count|count(*)', 'MySQL'),
('Constraint MySQL untuk identitas unik utama setiap baris tabel adalah apa?', 'primary key', 'primarykey', 'MySQL'),
('Clause MySQL untuk membatasi jumlah hasil query adalah apa?', 'limit', 'limit clause', 'MySQL'),
('Perintah MySQL untuk menghapus data dari tabel adalah apa?', 'delete', 'delete from|delete statement', 'MySQL'),
('Clause MySQL untuk mengelompokkan hasil agregasi berdasarkan kolom adalah apa?', 'group by', 'groupby', 'MySQL');
