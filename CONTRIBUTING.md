# CONTRIBUTING

Panduan kontribusi untuk **Laravel Starter** — terutama konvensi **commit & push**. Dokumen ini mengikat untuk semua kontributor, termasuk sesi Claude Code (lihat [docs/WORK_SESSIONS.md](docs/WORK_SESSIONS.md)).

Repository: `https://github.com/ramadhanrosihadi/laravel-starter`

---

## 1. Alur Kerja Branch

- Branch utama: **`main`** — selalu dalam kondisi stabil (bisa di-build & test hijau).
- **Jangan commit langsung ke `main`** untuk perubahan non-trivial. Buat branch fitur:

  ```
  feat/<ringkas>      # fitur baru        → feat/user-management
  fix/<ringkas>       # perbaikan bug     → fix/login-throttle
  chore/<ringkas>     # tooling/konfig    → chore/setup-pint
  docs/<ringkas>      # dokumentasi       → docs/api-response
  refactor/<ringkas>  # refactor          → refactor/auth-service
  ```

- Selama tahap awal starter (Sesi 1–5), boleh **satu branch per sesi**:

  ```
  git switch -c session/01-foundation
  ```

- Setelah selesai, gabungkan ke `main` lewat **Pull Request** (rekomendasi) atau merge lokal bila bekerja sendiri.

---

## 2. Konvensi Pesan Commit

Gunakan **[Conventional Commits](https://www.conventionalcommits.org/)**:

```
<type>(<scope opsional>): <subjek singkat, imperatif, ≤72 char>

<body opsional: jelaskan APA & MENGAPA, bukan bagaimana>

<footer opsional: referensi issue, BREAKING CHANGE>
```

**Type yang dipakai:**

| Type | Untuk |
|---|---|
| `feat` | Fitur baru |
| `fix` | Perbaikan bug |
| `chore` | Tooling, dependency, konfigurasi |
| `docs` | Dokumentasi saja |
| `refactor` | Perubahan kode tanpa ubah perilaku |
| `test` | Menambah/memperbaiki test |
| `style` | Formatting (Pint), tanpa ubah logika |
| `perf` | Peningkatan performa |

**Aturan:**
- Subjek dalam **imperatif** ("add", "fix", bukan "added"/"adds").
- Huruf kecil di awal subjek, tanpa titik di akhir.
- Commit **kecil & atomik** — satu commit = satu perubahan logis. Hindari satu commit besar di akhir sesi.
- Jangan commit file rahasia (`.env`, key Passport, dll.) — pastikan ada di `.gitignore`.

**Contoh:**
```
feat(auth): add passport login & refresh endpoints
fix(api): return 422 with field errors on validation failure
chore: configure pint and larastan
docs(architecture): finalize passport grant flow decision
```

---

## 3. Sebelum Commit (Quality Gate)

Jalankan dan pastikan bersih sebelum commit:

```bash
vendor/bin/pint              # auto-format (PSR-12)
vendor/bin/phpstan analyse   # static analysis (Larastan)
php artisan test             # test suite hijau
```

> Jika salah satu gagal, **perbaiki dulu** — jangan commit dalam keadaan merah.

---

## 4. Commit & Push

```bash
# Stage perubahan terkait (hindari `git add .` membabi-buta)
git add <file-file relevan>

# Commit dengan pesan konvensional
git commit -m "feat(auth): add passport login endpoint"

# Push ke remote
git push -u origin <nama-branch>     # pertama kali untuk branch baru
git push                              # selanjutnya
```

**Aturan push:**
- **Push di akhir setiap sesi kerja** (lihat WORK_SESSIONS.md) agar progres tersimpan di remote.
- Lebih baik push **beberapa kali** selama sesi, bukan menumpuk di akhir.
- **Jangan** `git push --force` ke `main` atau branch bersama. Jika perlu menulis ulang history branch pribadi, gunakan `git push --force-with-lease`.
- Jika `git push` ditolak karena ada perubahan remote, lakukan `git pull --rebase` lalu push ulang.

---

## 5. Pull Request (jika dipakai)

- Judul PR mengikuti format Conventional Commits.
- Deskripsi PR: ringkas apa yang berubah, sesi terkait, dan checklist deliverable dari WORK_SESSIONS.md.
- Pastikan quality gate (§3) hijau sebelum minta review/merge.
- Setelah merge, hapus branch fitur.

---

## 6. Checklist Cepat Akhir Sesi

- [ ] Quality gate hijau (Pint, Larastan, test).
- [ ] Perubahan ter-commit dalam beberapa commit atomik berpesan konvensional.
- [ ] Tidak ada file rahasia ter-commit.
- [ ] `.env.example` & dokumentasi terkait diperbarui bila perlu.
- [ ] **Branch sudah di-`push` ke `origin`.**
- [ ] (Jika pakai PR) PR dibuat / diperbarui.
