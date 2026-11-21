# Sách 3D đọc thử — hướng dẫn

Cuốn sách 3D lật trang (Three.js r160, chạy offline, không cần build).

## Cấu trúc

```
book_3d/
├── index.html            Trang hiển thị sách (dùng làm src của iframe)
├── editor.html           ✏️ Trình soạn: nhập ảnh bìa/gáy, nội dung trang, xem trước, xuất book.json
├── book.json             ⚙️ Cấu hình: ảnh bìa, các trang, kích thước, nút mua sách
├── assets/               Ảnh bìa + trang (hiện là placeholder SVG)
│   ├── cover-front.svg   Bìa trước   (tỉ lệ 14 × 20,5)
│   ├── cover-back.svg    Bìa sau
│   ├── cover-spine.svg   Gáy sách    (ảnh dọc, rộng = độ dày sách)
│   ├── background.svg    Nền phía sau sách
│   └── pages/            Ảnh minh hoạ mẫu (dùng trong nội dung qua [ảnh: …])
├── css/ js/ vendor/      Mã nguồn + Three.js
├── embed-snippet.html    Đoạn mã dán vào WordPress
├── embed-demo.html       Trang demo nhúng (máy tính + điện thoại)
└── tools/make-placeholders.mjs   Script tạo lại ảnh placeholder
```

## Xem thử trên máy

```bash
cd book_3d
python3 -m http.server 8765
# mở http://localhost:8765/  hoặc  http://localhost:8765/embed-demo.html
```
(Phải chạy qua server; mở file trực tiếp bằng `file://` sẽ không tải được.)

## Soạn sách bằng trình soạn (cách dễ nhất)

1. Mở `http://localhost:8765/editor.html` (hoặc `…/book3d/editor.html` trên host).
2. **Ảnh bìa**: bấm *Chọn ảnh* hoặc kéo thả ảnh bìa trước, bìa sau, gáy sách. Ảnh được tự nén (JPEG, cao tối đa 2048px).
   Nếu tỉ lệ ảnh khác khổ sách, trình soạn sẽ cảnh báo.
3. **Nội dung sách**: dán toàn bộ bản thảo vào một ô duy nhất — sách **tự chia trang**, không giới hạn số trang.
   - Mỗi lần xuống dòng = một đoạn mới (dòng trống bỏ qua). Chữ tự căn đều, thụt đầu dòng, đoạn dài tự sang trang sau.
   - `# Chương I | Người lạ cuối làng` → mở chương mới ở trang mới (viết `# Chương II: Tên chương` cũng được).
   - `---` → ngắt trang. `[ảnh: anh1]` → một trang ảnh (dùng nút *+ Chèn ảnh*).
   - *Cỡ chữ* Nhỏ / Vừa / Lớn — số trang tự tính lại.
4. Mục **Tất cả các trang** hiện toàn bộ trang đã chia; bấm vào trang nào, hoặc đặt con trỏ trong ô nội dung,
   thì khung xem trước mở tới trang đó.
5. Xong thì bấm **Tải book.json** → tải file đó lên host, ghi đè `book.json` cũ. Ảnh đã được nhúng trong file
   nên không cần tải ảnh riêng.

Bản nháp chỉ lưu trong trình duyệt đang dùng (IndexedDB). Muốn soạn tiếp trên máy khác: *Nhập book.json*.
Trình soạn không ghi gì lên server, nên có thể giữ hoặc không tải `editor.html` lên host tuỳ ý.

## Thay ảnh thủ công (không dùng trình soạn)

1. Xuất bìa và trang ra ảnh **JPG/WebP/PNG** (hoặc SVG). Kích thước gợi ý: cao 2048px, rộng theo tỉ lệ sách
   (14 × 20,5 cm → 1400 × 2050px). Gáy: cao 2050px, rộng = độ dày × 100 (2,4 cm → 240px).
2. Từ PDF có thể xuất nhanh bằng poppler:
   `pdftoppm -f 1 -l 5 -r 150 -jpeg sach.pdf assets/pages/page`
3. Sửa `book.json`: đường dẫn `cover.front/back/spine`, nội dung `content`, `size` (cm),
   `cta.url` (link mua sách). Viền bìa và gáy tự lấy màu theo dải sát mép của từng ảnh, như bìa in liền
   (ảnh nhiều màu thì viền đổi màu đúng vị trí). `cover.color` chỉ là màu dự phòng khi thiếu ảnh.

Nội dung nằm trong `content` (một chuỗi dài, cú pháp như trên); ảnh chèn vào nội dung nằm trong `media`:
```json
"fontScale": 1,
"content": "# Chương I | Tên chương\nĐoạn 1\nĐoạn 2\n---\n[ảnh: anh1]",
"media": { "anh1": { "src": "assets/hinh.jpg", "fit": "full", "caption": "" } }
```
`fit`: `full` = phủ kín trang, `framed` = ảnh minh hoạ trên nền giấy kèm `caption`.
Trang lẻ nằm bên phải, trang chẵn bên trái. (File kiểu cũ dùng `pages` vẫn đọc được; mở trong trình soạn sẽ tự chuyển sang `content`.)

**Độ dày gáy tự tính theo số trang**: số tờ (= số trang ÷ 2) × độ dày một tờ giấy + 2 bìa (mỗi bìa 0,3 mm).
Trong `book.json`: `"autoThickness": true` và `"paperThickness": 0.1` (mm/tờ; 0,08 mỏng · 0,1 thường · 0,13 dày).
Đặt `"autoThickness": false` để dùng `size.thickness` nhập tay. Ví dụ giấy thường: 100 trang ≈ 0,6 cm, 300 trang ≈ 1,6 cm.

Sách dài bao nhiêu trang cũng được: chỉ vài tờ quanh trang đang mở được vẽ, các tờ ở xa tự giải phóng bộ nhớ.
Với sách trên 6 trang, thanh công cụ có thêm thanh kéo để nhảy nhanh tới trang bất kỳ.

## Nhúng vào WordPress

1. Tải **cả thư mục** `book_3d` lên host (FTP / File Manager của hosting / plugin *WP File Manager*),
   ví dụ vào `wp-content/uploads/book3d/`. (Thư viện Media của WordPress không nhận file .js/.json.)
2. Mở thử `https://ten-mien.vn/wp-content/uploads/book3d/index.html` để chắc chắn chạy.
3. Trong bài viết/trang: thêm khối **HTML tuỳ chỉnh**, dán nội dung `embed-snippet.html`, sửa `src`.
   Với Elementor: dùng widget **HTML**.

Nhiều sách: tạo thêm file cấu hình, ví dụ `sach-2.json`, rồi dùng `index.html?config=sach-2.json`.

## Thao tác người xem

| Thao tác | Máy tính | Điện thoại |
|---|---|---|
| Mở sách | Bấm vào sách / Enter | Chạm vào sách |
| Lật trang | Kéo mép trang, bấm nửa trái/phải, phím ← → | Vuốt trái/phải, chạm nửa màn hình |
| Nhảy tới trang | Thanh kéo trên thanh công cụ | Thanh kéo (màn hình ≥ 380px) |
| Phóng to | Nút kính lúp / nhấp đúp, kéo để di chuyển | Nút kính lúp |
| Gập sách | Nút ✕ / Esc | Nút ✕ |
| Xoay sách khi gập | Kéo ngang | Kéo ngang |

Trình duyệt không có WebGL sẽ tự chuyển sang chế độ xem ảnh 2D đơn giản.
