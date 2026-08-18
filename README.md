# XE DAP NINH BINH

XE DAP NINH BINH - WooCommerce AI Product Importer.

## Thành phần hiện tại

- **XE DAP NINH BINH**: quét sản phẩm nguồn, đọc giá/ảnh/thông tin, nhận diện sản phẩm biến thể và nhập vào WooCommerce.
- **Variation Fix V2.2.2**: công cụ sửa các sản phẩm biến thể đã nhập trước đó.
- **Variation Guard V2.2.3**: plugin phụ tự động bảo vệ variation mới; loại thuộc tính rỗng, giá trị `any/Bất kỳ` và tự điền giá trị khi thuộc tính chỉ có một lựa chọn thực tế.

## Cài đặt Variation Guard

1. Vào WordPress → Plugins → Add New → Upload Plugin.
2. Lấy file `xe-dap-ninh-binh-variation-guard.php` trong thư mục `xe-dap-ninh-binh` của repository.
3. Cài đặt và kích hoạt plugin.
4. Vào **XE DAP NINH BINH → Kiểm soát biến thể**.
5. Chọn các sản phẩm cũ cần sửa rồi bấm **Sửa sản phẩm đã chọn**.

Sau khi kích hoạt, Variation Guard tiếp tục chạy tự động khi WooCommerce tạo/cập nhật variation.

## Nguyên tắc biến thể

- Không tạo/giữ thuộc tính không có giá trị thực.
- Không để variation có giá trị `any`, `Bất kỳ` hoặc rỗng nếu thuộc tính cha chỉ có một giá trị.
- Không tự bịa thêm Frame Size hoặc tùy chọn mà trang nguồn không cung cấp.
- Giữ giá, SKU và ảnh của variation khi sửa thuộc tính.
