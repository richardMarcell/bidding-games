# Programming Quiz Bidding Game

Web aplikasi multiplayer quiz programming berbasis room dengan moderator dan player. Backend memakai PHP murni + PDO, database MySQL, dan update realtime sederhana lewat AJAX polling.

## Fitur

- Moderator membuat room dengan kode unik
- Player join memakai username sederhana dan kode room
- Lobby realtime untuk melihat daftar player
- Game berjalan sinkron per ronde dengan fase `bidding -> answering -> review`
- Semua player wajib bid dulu, baru soal teks terbuka bersamaan
- Jawaban diproses serentak saat semua player selesai menjawab
- Jawaban benar menambah skor sesuai bid
- Jawaban salah mengurangi skor sesuai bid
- Moderator bisa memulai game dan lanjut ke soal berikutnya
- Moderator bisa menghentikan game sementara lalu melanjutkannya lagi
- Spectator publik bisa menonton room tanpa join sebagai player
- Leaderboard final saat game selesai

## Struktur

- `public/` halaman aplikasi
- `api/` endpoint AJAX
- `config/` koneksi database dan helper
- `assets/` CSS dan JavaScript
- `database.sql` schema + seed bank soal programming
  Catatan: sekarang file ini berisi schema baru berbasis jawaban teks dan bank soal lebih banyak.

## Cara Menjalankan

1. Buat database MySQL atau gunakan nama `bidding_games`.
2. Import ulang file `database.sql`.
3. Ubah kredensial database di `config/database.php` bila perlu.
4. Simpan project di web root hosting atau localhost.
5. Buka `index.php` dari browser, nanti otomatis diarahkan ke halaman utama.

## Catatan

- Untuk testing multiplayer di satu komputer, gunakan browser berbeda atau mode incognito.
- Endpoint memakai session PHP sederhana, jadi setiap browser menyimpan identitas user masing-masing.
- Jika ingin keluar dari room dan kembali ke awal, buka `public/index.php?reset=1`.
- Spectator dapat membuka daftar room publik langsung dari halaman awal lalu klik `Spectate Room`.
