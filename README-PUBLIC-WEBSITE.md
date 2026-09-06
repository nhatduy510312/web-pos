# Website và quản trị nội dung Ghé — phiên bản 2

Đã triển khai bốn phần: quản trị nội dung có nháp/xem trước/xuất bản, thư viện ảnh, menu đồng bộ POS và giao diện khách có tiện ích/nút liên hệ nhanh. Code chưa được deploy lên hosting.

## Thông tin được chủ quán xác nhận

- Ghé — 30 Võ Trường Toản, Đà Lạt, Lâm Đồng.
- Mở cửa **07:00–18:00 hằng ngày**.
- Không gian sân vườn, không gian trong nhà, yên tĩnh; phù hợp làm việc, học tập và trò chuyện.
- **Wi-Fi 6, đường truyền 1 Gbps**, **ổ điện ở mỗi bàn**.
- Chỗ đỗ **ô tô và xe máy dọc đường**; không mô tả thành bãi đỗ xe riêng.
- Điện thoại 0705 926 614 và website ghecoffeedl.io.vn đối chiếu từ [Google Maps do chủ quán cung cấp](https://maps.app.goo.gl/ySJSyKgaPvpcgA549).
- Chưa thêm ảnh thật; chủ quán sẽ tải lên sau. Minh họa hiện tại không phải ảnh quán.
- Chưa đọc được nội dung review trong chế độ Google Maps hạn chế, nên chỉ liên kết đến đánh giá gốc; không tự tạo lời khách hoặc số sao.

## Cách sử dụng

Đăng nhập bằng tài khoản **admin** hiện có. Trong thanh điều hướng POS, chọn **Website**, hoặc truy cập `website-admin.php`.

### Nội dung & thông tin

Sửa địa chỉ, số điện thoại, Google Maps, giờ mở/đóng cửa, tiêu đề trang chủ, phần giới thiệu, lời giới thiệu menu và lưu ý trước khi đến. Các tiện ích có ba trạng thái **Có / Không / Chưa xác nhận**, kèm ô chi tiết (ví dụ tốc độ Wi-Fi). Nội dung nhập là văn bản thuần; đoạn văn cách nhau bằng một dòng trống.

Bấm **Lưu bản nháp**, sau đó **Xem trước bản nháp**. Khi đã hài lòng, bấm **Xuất bản website**. Đây là xuất bản toàn bộ thay đổi đã lưu: thông tin, ảnh, bài viết và cấu hình món. Nếu sửa thông tin trong bài giới thiệu, cần đồng thời chỉnh các đoạn văn liên quan để tránh thông tin cũ còn trong nội dung tự viết.

**Khôi phục bản nháp** đưa bản nháp về nội dung hiện đang công khai. Nếu hai cửa sổ quản trị cùng sửa, bản lưu từ phiên bản cũ bị từ chối để tránh ghi đè.

### Thư viện ảnh

- Chọn JPEG, PNG hoặc WebP; tối đa **8 MB / 20 megapixel**.
- Nhập mô tả ảnh, chú thích và chọn có hiển thị trong thư viện không gian hay không.
- Hệ thống mã hóa lại thành WebP tối đa cạnh dài 1600px và ảnh nhỏ 480px, giữ tỷ lệ, xử lý hướng EXIF khi có extension, loại metadata của ảnh gốc.
- Ảnh mặc định chỉ thuộc bản nháp. Chọn một ảnh làm ảnh đầu trang chủ hoặc gán ảnh cho món trong tab Menu.
- Sắp xếp bằng số thứ tự, số nhỏ hiển thị trước. Ảnh món và ảnh thư viện dùng ảnh responsive, có alt/width/height; ảnh dưới phần đầu trang tải chậm.
- Gỡ ảnh chỉ gỡ khỏi bản nháp, đồng thời bỏ tham chiếu ảnh ở các món và ảnh đầu trang. Sau khi xuất bản, ảnh không còn được phục vụ nếu không còn vị trí sử dụng công khai. File tối ưu vẫn được giữ trong kho riêng để có thể phục hồi; không có thao tác xóa hàng loạt.
- Ảnh gốc không được lưu lâu dài; chủ quán nên giữ bản gốc riêng.
- Thư viện không có ảnh thì phần thư viện tự ẩn, không hiện khung trống.

### Menu từ POS

Tên, giá, nhóm món và trạng thái đang bán đọc trực tiếp từ bảng `products` và `categories`, liên kết bằng **ID sản phẩm**. Đổi tên/giá ở POS có hiệu lực trên trang khách ngay ở lần tải tiếp theo; không cần xuất bản nội dung lại.

Trong tab Menu, quản trị viên sửa mô tả công khai, chọn ảnh, đánh dấu món gợi ý trang chủ và bật/tắt hiển thị. Các trường này có nháp/xuất bản. Món mới đang bán mặc định được hiển thị. Món ngừng bán hoặc bị ẩn sẽ không xuất hiện trong menu, gợi ý trang chủ, trang “Ghé uống gì?” và menu khách cũ.

Mô tả ban đầu được gợi ý từ menu biên soạn cũ khi tên khớp chính xác; quản trị viên có thể thay thế. File `site/menu-data.php` chỉ còn dùng làm nguồn gợi ý mô tả, không phải nguồn giá công khai.

Nếu POS không kết nối được, website vẫn hiển thị thông tin quán; phần menu thông báo chưa tải được và cung cấp số liên hệ. Không quay về giá cũ. Website công khai chỉ SELECT dữ liệu món, không chạy logic tự chốt ca của POS.

### Bài viết

Nhập tiêu đề, mô tả ngắn, đường dẫn không dấu và nội dung. Đường dẫn của bài đã lưu được giữ ổn định. Có thể lưu nháp, xem trước, chọn hiển thị hoặc gỡ bài. Bài chỉ công khai sau khi xuất bản website.

Khi có bài đang hiển thị, menu website tự xuất hiện **Chuyện ở Ghé**. Bài có metadata và dữ liệu `BlogPosting`, được thêm vào sitemap. Bài nháp/ẩn/gỡ không có trên sitemap; người chưa đăng nhập không xem được bản nháp.

## Đường dẫn

| URL | Chức năng |
| --- | --- |
| `/` hoặc `home.php` | Trang chủ công khai, canonical về `/` |
| `gioi-thieu.php` | Giới thiệu và thư viện không gian |
| `thuc-don.php` | Menu POS với tìm kiếm không dấu |
| `ghe-uong-gi.php` | Nhóm món và gợi ý theo menu đang hiển thị |
| `den-ghe.php` | Giờ mở cửa, tiện ích, liên hệ và FAQ |
| `chuyen-o-ghe.php` | Danh sách bài viết, xuất hiện khi có bài công khai |
| `bai-viet.php?slug=...` | Bài viết công khai |
| `website-admin.php` | Quản trị, chỉ admin |
| `website-preview.php` | Xem bản nháp, chỉ admin, noindex/no-store |
| `ghe-image.php?id=...` | Ảnh đã xuất bản; ảnh nháp yêu cầu admin |
| `menu-khach.php` | Menu khách cũ/QR tiếp tục hoạt động, cùng dữ liệu POS |
| `index.php` | POS hiện có, yêu cầu đăng nhập |
| `sitemap.xml`, `robots.txt` | Sitemap động và chỉ dẫn crawl |

## Cài đặt và nâng cấp

1. Sao lưu website đang chạy, đặc biệt `.htaccess`, `menu.php`, `menu-khach.php` và kho nội dung nếu đã sử dụng CMS.
2. Upload gói phiên bản 2 vào cùng thư mục POS. Giữ nguyên cấu hình kết nối database và những quy tắc hosting riêng khi gộp `.htaccess`. Không thay dữ liệu POS, không import database thử.
3. Cần Apache 2.4, `mod_rewrite`, `mod_headers`, AllowOverride phù hợp; PHP **7.4+** với `mysqli`, `mbstring`, `fileinfo`, **GD có WebP**. Bật `exif` để tự xoay ảnh JPEG theo hướng chụp. Đã kiểm tra với PHP 8.2. Hosting chưa bật GD sẽ nhận thông báo rõ ràng khi tải ảnh.
4. Đặt `upload_max_filesize >= 8M`, `post_max_size >= 10M`. Tài khoản PHP cần quyền ghi vào **`site/storage/`** (tự tạo khi lưu lần đầu), không cần quyền ghi các file PHP.
5. Kho dữ liệu gồm `content.json`, `content.previous.json`, `content.lock` và thư mục `media/`. Sao lưu cả kho; **không ghi đè hoặc xóa kho này khi nâng cấp code**. Gói phát hành không chứa nội dung hay ảnh thử.
6. Có thể đặt `GHE_STORAGE_DIR` tới thư mục riêng ngoài webroot. Thư mục `site/` mặc định đã chặn HTTP qua `.htaccess`. Với Nginx, cần quy tắc tương đương để chặn `site/`, `tools/` và giữ các file cấu hình riêng.
7. Kết nối menu dùng biến môi trường **POS_DB_HOST, POS_DB_USER, POS_DB_PASS, POS_DB_NAME, POS_DB_PORT**; mặc định tương ứng localhost/root/(rỗng)/cafe_pos/3306. Nếu hosting sửa trực tiếp giá trị trong `config.php` thay vì đặt biến môi trường, cần cấu hình cùng thông tin trong `site/catalog.php` hoặc chuyển sang biến môi trường. Không đưa mật khẩu vào nội dung công khai.
8. URL chuẩn đặt trong `site/config.php` hoặc biến **GHE_SITE_URL** (mặc định `https://ghecoffeedl.io.vn`). Nếu cài trong thư mục con, đưa cả đường dẫn thư mục vào URL.
9. CMS dùng tài khoản admin và kiểm tra trạng thái/session_version theo hệ thống tài khoản POS hiện có. Không tạo tài khoản admin mới, không thay mật khẩu và không thêm bảng vào database sản xuất.
10. Kiểm tra trang gốc, đăng nhập POS, CMS, tải ảnh, xem nháp/xuất bản, đổi giá POS, sitemap và robots trên hosting thực. Thư mục `tools/` không cần upload.

Gói cài đặt bổ sung website vào POS hiện có, không phải bộ POS đầy đủ. Các file nghiệp vụ/xác thực của POS vẫn cần giữ trên hosting. `DirectoryIndex home.php index.php` đổi trang gốc sang website công khai; nhân viên tiếp tục dùng `index.php`.

## SEO

Có HTML dựng phía máy chủ, metadata/canonical riêng, liên kết nội bộ, ảnh chia sẻ, sitemap bài viết động, dữ liệu `CafeOrCoffeeShop`, giờ hoạt động cả tuần, tiện ích xác nhận, menu và giá khớp nội dung, FAQ và bài viết. POS/CMS/preview có chỉ dẫn noindex; xác thực mới là cơ chế bảo vệ dữ liệu.

Sau khi deploy, gửi sitemap trong Search Console, kiểm tra URL và dữ liệu có cấu trúc, cập nhật giờ/website/menu trong Google Business Profile. Chưa thực hiện những thao tác trên các tài khoản bên ngoài.

Google không yêu cầu schema AI hoặc file AI riêng để xuất hiện trong AI Overviews/AI Mode, và không đảm bảo xếp hạng hay được nhắc tên. Tham khảo [AI features](https://developers.google.com/search/docs/appearance/ai-features), [Local business](https://developers.google.com/search/docs/appearance/structured-data/local-business), [Review guidelines](https://developers.google.com/search/docs/appearance/structured-data/review-snippet).

## Kiểm tra

- PHP lint các file mới/sửa.
- `tools/check-public-site.cjs`: năm trang ở 360/768/1440px, tìm món, menu không JavaScript, dữ liệu giá/schema, liên kết, sitemap, robots, 403/404 và POS chuyển về đăng nhập.
- `tools/check-website-cms.cjs`: cơ sở dữ liệu **thử riêng** và phiên đăng nhập admin/staff/tài khoản bị thu hồi; CSRF, lưu nháp/xem trước/xuất bản, xung đột phiên bản, XSS, thông tin không hợp lệ, ảnh JPEG→WebP và từ chối SVG, ảnh nháp, menu theo ID, đổi giá/tên/ngừng bán/ẩn món, bài viết/schema/sitemap, gỡ ảnh/bài và giao diện responsive.
- Test DB là MariaDB riêng chỉ nghe localhost:33077. Không dùng tài khoản hoặc dữ liệu kinh doanh thật.
- Chưa kiểm tra giao dịch thanh toán hoặc đăng nhập tài khoản thật trên hosting.

Font Lora tự lưu kèm giấy phép SIL OFL trong `assets/fonts/OFL-Lora.txt`. File `assets/ghe-social.svg` là nguồn đồ họa chia sẻ dự phòng, không phải ảnh mặt bằng quán.

## Bản tiếng Anh cho khách quốc tế

- Nút VI / EN trên đầu trang chuyển tới trang tương ứng, dùng được khi tắt JavaScript. Không tự chuyển ngôn ngữ theo IP hoặc trình duyệt.
- Các trang: `en.php`, `en.php?view=about`, `en.php?view=menu`, `en.php?view=story`, `en.php?view=visit`. Bài viết vẫn giữ tiếng Việt; không khai báo phiên bản tiếng Anh cho bài chưa dịch.
- Mỗi trang tiếng Anh có canonical riêng, `lang=en`, dữ liệu có cấu trúc tiếng Anh và liên kết `hreflang` qua lại với trang tiếng Việt. Cả 5 URL đã được thêm vào sitemap. Cách triển khai dựa trên [hướng dẫn ngôn ngữ của Google](https://developers.google.com/search/docs/specialty/international/localized-versions); không bảo đảm thứ hạng hay thời điểm được lập chỉ mục.
- Quản trị → Nội dung & thông tin → **Nội dung tiếng Anh dành cho khách**: sửa phần giới thiệu, lưu ý, mô tả tiện ích. Địa chỉ, điện thoại, giờ mở cửa, tiện ích có/không dùng chung với tiếng Việt. Chữ tiếng Anh được quản lý riêng; khi thay đổi ý nghĩa nội dung tiếng Việt, hãy cập nhật bản tiếng Anh tương ứng.
- Quản trị → Menu từ POS: thêm **Tên món — English**, **Mô tả — English** theo ID món. Không đổi tên món hoặc giá trong POS. Giá hiển thị `30,000 VND`; khách có thể tìm bằng tên Việt hoặc Anh. Món ngừng bán / ẩn sẽ ẩn ở cả hai bản.
- Các tên món có sẵn được dịch trong `site/menu-en.php`; mô tả gốc chỉ được dịch khi còn khớp nội dung đã duyệt. Món mới hoặc mô tả Việt tự nhập chưa có bản dịch sẽ giữ tên gốc và nhắc khách hỏi nhân viên về thành phần. Có thể ghi bản dịch riêng trong quản trị. Không tự suy đoán thành phần hay dị ứng.
- Thư viện ảnh có thêm mô tả và chú thích English. Khi chưa nhập, bản Anh dùng alt trung tính và không hiển thị chú thích Việt.
- Tất cả bản dịch vẫn theo quy trình **lưu nháp → xem trước → xuất bản**. Xem trước tiếng Anh tại `website-preview.php?page=en.php`; bản nháp không công khai và không được lập chỉ mục.
- Menu QR cũ `menu-khach.php` có liên kết **English menu** tới bản tiếng Anh.
- Bản cập nhật không cần migration SQL và không ghi đè nội dung / ảnh trong `site/storage`. Sao lưu mã nguồn và dữ liệu hiện tại trước khi cập nhật. Chép đè các file trong ZIP đúng cấu trúc, bao gồm `.htaccess` (thêm `en.php` vào các trang được index); nếu hosting có quy tắc `.htaccess` riêng mới hơn, chỉ gộp thay đổi allowlist này, không bỏ quy tắc riêng.
- Đã kèm bản sửa lưu món tại chỗ: `assets/website-admin.js` và CSS liên quan. Không chép gói sửa cũ lên sau gói tiếng Anh vì sẽ mất các ô tiếng Anh.
- Kiểm thử: `tools/check-english-site.cjs` chạy trên fixture DB riêng; kiểm tra 5 trang, responsive 360/768/1440, SEO, chuyển ngôn ngữ, giá/schema, tìm kiếm, không JavaScript, preview, lưu nháp/xuất bản và POS cập nhật. `tools/check-menu-inline-save.cjs` kiểm tra giữ vị trí lưu món.
