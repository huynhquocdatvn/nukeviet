# **TÀI LIỆU THIẾT KẾ KỸ THUẬT: HỆ THỐNG QUEUE CHO NUKEVIET CMS**

**Phiên bản:** 1.2 (Final)

**Ngày cập nhật:** 30/01/2026

**Mục tiêu:** Xây dựng hệ thống xử lý hàng đợi (Queue) phi tập trung, hiệu năng cao, không phụ thuộc Framework, tự động tương thích môi trường Linux/Windows cho NukeViet 5.x.

## **1\. TỔNG QUAN KIẾN TRÚC**

Hệ thống được thiết kế theo mô hình **Producer-Consumer** tách biệt hoàn toàn với logic hiển thị của Website.

### **1.1. Luồng dữ liệu (Data Flow)**

1. **Producer (Module):** Các module (News, Shops...) tạo ra Job (gửi mail, resize ảnh...).  
2. **Dispatcher (Bộ điều phối):**  
   * **Chế độ Async (Redis):** Đẩy Job vào Redis List để xử lý sau.  
   * **Chế độ Sync (Fallback):** Nếu tắt Queue, sử dụng kỹ thuật fastcgi\_finish\_request để trả về phản hồi ngay lập tức cho người dùng, sau đó PHP tiếp tục chạy ngầm để xử lý Job.  
3. **Broker (Redis):** Nơi lưu trữ hàng đợi.  
4. **Consumer (Worker):** Ứng dụng CLI chạy độc lập tại thư mục /worker, tự động tải môi trường NukeViet để xử lý logic.

### **1.2. Cấu trúc thư mục chuẩn**

Lưu ý: File config.php nằm tại thư mục gốc (Root), còn composer.json nằm trong includes/.

nukeviet\_root/  
├── config.php             \<-- \[QUAN TRỌNG\] File cấu hình gốc của NukeViet  
├── includes/  
│   ├── composer.json      \<-- \[SỬA ĐỔI\] Đăng ký Namespace NukeViet\\Queue tại đây  
│   └── functions\_queue.php \<-- File chứa hàm nv\_dispatch\_job  
├── modules/  
│   └── news/  
│       └── Jobs/          \<-- Namespace: NukeViet\\Module\\news\\Jobs  
│           └── SendEmail.php  
├── worker/                \<-- Namespace: NukeViet\\Queue  
│   ├── bootstrap.php      \<-- Load NV\_ROOTDIR, config.php, mainfile.php  
│   ├── AbstractWorker.php \<-- Class cha (Xử lý DB Reconnect)  
│   ├── LinuxWorker.php    \<-- Xử lý Forking (PCNTL)  
│   ├── WindowsWorker.php  \<-- Xử lý Loop & Limit  
│   └── run.php            \<-- File khởi chạy (Entry Point)  
└── vendor/                \<-- Thư viện Predis (Cài qua Composer)

## **2\. CHIẾN LƯỢC XỬ LÝ (RUNTIME STRATEGY)**

Hệ thống tự động phát hiện hệ điều hành để chọn chiến lược tối ưu:

### **2.1. Linux (Production Mode)**

* **Cơ chế:** Sử dụng pcntl\_fork.  
* **Hoạt động:** Process cha (Master) chỉ nhận việc và quản lý. Process con (Child) được sinh ra để xử lý Job, sau đó tự hủy (exit) ngay lập tức.  
* **Ưu điểm:** Giải phóng triệt để bộ nhớ RAM, tránh memory leak khi chạy lâu dài.

### **2.2. Windows/XAMPP (Development Mode)**

* **Cơ chế:** Sử dụng Vòng lặp (Loop) với giới hạn.  
* **Hoạt động:** Worker xử lý tuần tự từng Job.  
* **Giới hạn:** Tự động tắt (exit) sau khi xử lý **50 Jobs** hoặc chạy quá **1 giờ** hoặc RAM vượt quá **100MB**.  
* **Tái khởi động:** Cần sử dụng file .bat hoặc Windows Service (NSSM) để tự động bật lại Worker sau khi nó tắt.

## **3\. XỬ LÝ NGHIỆP VỤ LÕI (CORE LOGIC)**

### **3.1. Bootstrap & Môi trường**

File worker/bootstrap.php đóng vai trò giả lập môi trường Web cho CLI:

* Định nghĩa NV\_SYSTEM \= true.  
* Định nghĩa NV\_ROOTDIR trỏ về thư mục gốc.  
* Giả lập các biến $\_SERVER (REQUEST\_URI, HTTP\_HOST...) để tránh lỗi khi load mainfile.php.  
* Require includes/mainfile.php: File này sẽ tự động load config.php từ Root và khởi tạo kết nối Database ban đầu.

### **3.2. Tái kết nối Database (Database Reconnection)**

Đây là vấn đề quan trọng nhất. Kết nối $db được tạo ra bởi mainfile.php sẽ bị timeout (MySQL has gone away) nếu Worker chạy lâu.

* **Giải pháp:** Trong AbstractWorker, trước khi thực thi bất kỳ Job nào, hệ thống bắt buộc phải hủy kết nối cũ và khởi tạo lại đối tượng \\sql\_db mới hoàn toàn dựa trên thông tin từ $db\_config (biến toàn cục).

### **3.3. Kiểm tra Module**

Trước khi chạy Job của một module (ví dụ news), Worker kiểm tra biến toàn cục $site\_mods. Nếu module đó không tồn tại hoặc trạng thái không phải active, Job sẽ bị bỏ qua.

## **4\. HƯỚNG DẪN CẤU HÌNH THỦ CÔNG (PREREQUISITES)**

Trước khi chạy mã nguồn do AI sinh ra, bạn cần thực hiện 3 bước sau:

### **Bước 1: Sửa file config.php (Tại thư mục gốc)**

Mở file config.php nằm ngay thư mục gốc website, thêm đoạn sau vào cuối file (khu vực cấu hình DB):

// Cấu hình Redis cho Queue System  
$redis\_config \= \[  
    'host'     \=\> '127.0.0.1',  
    'port'     \=\> 6379,  
    'password' \=\> null, // null nếu không có pass  
    'database' \=\> 0,  
    'prefix'   \=\> 'nv\_queue\_'  
\];

// 1 \= Bật Queue (Async), 0 \= Tắt Queue (Chạy ngầm Sync)  
$global\_config\['sys\_use\_queue'\] \= 1;

### **Bước 2: Đăng ký Namespace trong includes/composer.json**

Mở file includes/composer.json. Tại phần autoload \-\> psr-4, thêm dòng định nghĩa cho worker.

*Lưu ý: Vì file json nằm trong includes/, ta dùng ../worker/ để trỏ ra thư mục worker ở root.*

"autoload": {  
    "psr-4": {  
        "NukeViet\\\\": "namespace/",  
        "NukeViet\\\\Queue\\\\": "../worker/"  
    }  
}

### **Bước 3: Cập nhật Composer**

Mở terminal tại thư mục includes/ (nơi chứa composer.json) và chạy:

composer require predis/predis  
composer dump-autoload

## **5\. PROMPT DÀNH CHO AI (GENERATION PROMPT)**

Dưới đây là Prompt (Câu lệnh) tối ưu nhất để sinh ra mã nguồn chính xác theo kiến trúc trên. Hãy copy toàn bộ nội dung trong khối code dưới đây và gửi cho AI.

Act as a Senior PHP Architect and System Administrator specialized in NukeViet CMS.

\*\*Goal:\*\*  
Generate a complete, decoupled Queue System for NukeViet 4.x that runs on native PHP. The system must integrate seamlessly with NukeViet's directory structure and configuration.

\*\*Context & Directory Structure:\*\*  
\* \*\*Root Config:\*\* The main configuration file \`config.php\` is located at the \*\*ROOT\*\* of the application (\`NV\_ROOTDIR . '/config.php'\`), NOT in \`includes/\`.  
\* \*\*Worker Directory:\*\* The worker logic resides in \`worker/\` folder at the root.  
\* \*\*Bootstrap Logic:\*\* The \`worker/bootstrap.php\` must define \`NV\_ROOTDIR\` correctly (dirname of worker dir) so that when \`includes/mainfile.php\` is included, it can correctly locate and load the root \`config.php\`.

\*\*Technical Requirements:\*\*

1\.  \*\*Configuration Management:\*\*  
    \* Do NOT hardcode Redis credentials.  
    \* Access the Redis configuration from the global array \`$redis\_config\` which is defined in the root \`config.php\`.  
    \* Handle cases where \`$redis\_config\` might be missing (throw meaningful error).

2\.  \*\*Namespace & Autoloading:\*\*  
    \* Assume \`worker/\` maps to namespace \`NukeViet\\Queue\\\` (configured in \`includes/composer.json\`).  
    \* All classes in \`worker/\` must use this namespace.  
    \* Example: \`worker/AbstractWorker.php\` \-\> \`namespace NukeViet\\Queue;\`

3\.  \*\*Environment Agnostic Strategy:\*\*  
    \* \*\*LinuxWorker:\*\* Use \`pcntl\_fork\` (Master/Worker pattern) for memory efficiency.  
    \* \*\*WindowsWorker:\*\* Use a "Loop & Limit" strategy (run 50 jobs OR 1 hour OR 100MB RAM then exit) to prevent memory leaks on XAMPP.  
    \* \*\*run.php:\*\* Detects OS/Extensions and instantiates the correct worker strategy class.

4\.  \*\*NukeViet Integration Details:\*\*  
    \* \*\*\`worker/bootstrap.php\`\*\*:  
        \* Define \`NV\_SYSTEM\` \= true.  
        \* Define \`NV\_ROOTDIR\` \= \`dirname(\_\_DIR\_\_)\` (Parent of worker directory).  
        \* Simulate \`$\_SERVER\['SERVER\_NAME'\]\`, \`$\_SERVER\['REQUEST\_URI'\]\`, \`$\_SERVER\['HTTP\_HOST'\]\` (required by NukeViet core).  
        \* Include \`includes/vendor/autoload.php\` (standard NV autoload).  
        \* Include \`includes/mainfile.php\` (This will load the root \`config.php\`).  
    \* \*\*DB Reconnection (\`AbstractWorker\`):\*\* Explicitly re-instantiate \`\\sql\_db\` using \`$db\_config\` (from global scope) before processing ANY job. This is mandatory to prevent "MySQL has gone away".  
    \* \*\*Module Check:\*\* Check global \`$site\_mods\` to ensure the target module is active.

5\.  \*\*Dispatcher Logic (Hybrid Mode):\*\*  
    \* File: \`includes/functions\_queue.php\`.  
    \* Function: \`nv\_dispatch\_job($module, $handler, $data)\`.  
    \* Logic: Check \`$global\_config\['sys\_use\_queue'\]\`.  
        \* \*\*If 1 (Redis Async):\*\* Push JSON payload to Redis using \`$redis\_config\`.  
        \* \*\*If 0 (Sync Fallback):\*\* Use the "Fire and Forget" technique:  
            1\. Clean output buffer.  
            2\. Send HTTP Headers (\`Connection: close\`, \`Content-Length\`, \`Content-Type: application/json\`).  
            3\. Use \`fastcgi\_finish\_request()\` (if available) or \`flush()\` to send response to user immediately.  
            4\. Keep the script running (\`ignore\_user\_abort(true)\`, \`set\_time\_limit(0)\`).  
            5\. Close Session (\`session\_write\_close()\`) to unlock the user interface.  
            6\. Run the job logic in the background.

\*\*Deliverables:\*\*

Please generate source code for the following files with strict adherence to the requirements above:

1\.  \`worker/bootstrap.php\` (Crucial: Correct NV\_ROOTDIR definition)  
2\.  \`worker/AbstractWorker.php\` (Namespace: \`NukeViet\\Queue\`. Base class with Redis & DB logic)  
3\.  \`worker/LinuxWorker.php\` (Namespace: \`NukeViet\\Queue\`. Extends AbstractWorker)  
4\.  \`worker/WindowsWorker.php\` (Namespace: \`NukeViet\\Queue\`. Extends AbstractWorker)  
5\.  \`worker/run.php\` (The CLI entry point)  
6\.  \`includes/functions\_queue.php\` (The dispatcher function)  
7\.  \`modules/news/Jobs/ExampleJob.php\` (An example job to demonstrate structure)

\*\*Style:\*\* Modern PHP 8.4+, PSR-12 coding standard, Clear Comments explaining the NukeViet integration parts.  
