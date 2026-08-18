# XE DAP NINH BINH

XE DAP NINH BINH - WooCommerce AI Product Importer.

## Thành phần hiện tại

- **XE DAP NINH BINH V2.2.1**: quét sản phẩm nguồn, đọc giá/ảnh/thông tin, nhận diện sản phẩm biến thể và nhập vào WooCommerce.
- **Variation Fix V2.2.2**: công cụ sửa các sản phẩm biến thể đã nhập trước đó.
- **Variation Guard V2.3.0**: plugin phụ bảo vệ variation mới và sửa hàng loạt variation cũ của sản phẩm do importer tạo.

## V2.3.0 – Variation Guard

Mục tiêu của V2.3 là xử lý lỗi nhìn thấy trong WooCommerce khi sản phẩm có các dòng như `Bất kỳ Màu xe...` hoặc `Bất kỳ Frame Size...`.

V2.3 chỉ tác động đến sản phẩm có meta `_xdn_source_url`, tức sản phẩm được tạo bởi XE DAP NINH BINH. Sản phẩm WooCommerce khác không bị can thiệp.

Tự động:

- Loại thuộc tính rỗng khỏi sản phẩm XDN.
- Không giữ giá trị `any`, `Bất kỳ` hoặc rỗng trong variation.
- Nếu thuộc tính cha chỉ có đúng 1 giá trị thực tế, tự gán giá trị đó cho variation.
- Loại thuộc tính của variation không còn tồn tại trên sản phẩm cha.
- Đồng bộ lại dữ liệu variable product sau khi sửa.
- Không thay đổi giá, SKU hoặc ảnh của variation.

WooCommerce khuyến nghị xác định đầy đủ thuộc tính cho từng variation khi có thể; việc dùng `Any` có thể tạo các variation trùng/khó dự đoán nếu sắp xếp không đúng. Vì vậy V2.3 dùng chế độ chặt cho sản phẩm nhập từ nguồn.

## Cài đặt V2.3

1. Vào **WordPress → Plugins → Add New → Upload Plugin**.
2. Lấy file `xe-dap-ninh-binh-v2.3-variation-guard.php` trong thư mục `xe-dap-ninh-binh`.
3. Cài đặt và **Kích hoạt** plugin.
4. Vào **XE DAP NINH BINH → Kiểm soát biến thể**.
5. Chọn sản phẩm cũ cần sửa → **Sửa các sản phẩm đã chọn**.
6. Sau đó khi importer tạo variation mới, guard sẽ tiếp tục kiểm tra tự động.

## Nguyên tắc nhập biến thể

- Chỉ tạo thuộc tính khi trang nguồn thực sự cung cấp giá trị.
- Không tự bịa `Frame Size`, màu hoặc lựa chọn mới.
- Một thuộc tính có một giá trị thực tế → variation dùng chính giá trị đó, không để `Any`.
- Nhiều giá trị thực tế → variation phải nhận đúng giá trị nguồn tương ứng.
- Giữ nguyên giá, SKU và ảnh của variation.
