# PHP OOP Real-time URL Shortener Engine (oop-020)

ระบบย่อลิงก์และติดตามสถิติจำนวนคลิกแบบ Real-time พัฒนาด้วยภาษา PHP ตามหลักการเขียนโปรแกรมเชิงวัตถุ (Object-Oriented Programming: OOP)

---

## 🌟 จุดเด่นของระบบ (Features)
- **Clean OOP Architecture**: แยกโครงสร้างการทำงานออกเป็น 3 คลาสหลักตามหลักความรับผิดชอบเดี่ยว (Single Responsibility)
  1. `UrlEncoder`: เข้ารหัสรหัสสุ่ม 6 ตัวอักษรด้วยอัลกอริทึม **Base62** (0-9, a-z, A-Z) รองรับได้มากกว่า 56,800 ล้านรูปแบบ
  2. `LinkItem`: Data Model จัดเก็บ Original URL, Short Code, จำนวนคลิก (Click Counter) และเวลาที่สร้าง
  3. `RedirectManager`: คลาสศูนย์กลางในการทำ CRUD, จัดเก็บข้อมูลลงไฟล์ JSON และดักจับคำขอเพื่อทำ HTTP 302 Redirect พร้อมนับสถิติอัตโนมัติ
- **Real-time Syncing**: อัปเดตสถิติยอดคลิกบนตารางแดชบอร์ดแบบสดๆ ทุกๆ 2 วินาทีผ่าน REST API และ Client-side Polling โดยไม่ต้องรีเฟรชหน้าเว็บ
- **Zero-Dependency Storage**: บันทึกข้อมูลลง Flat-file (`links.json`) รันได้ทันทีโดยไม่ต้องติดตั้งฐานข้อมูล MySQL ภายนอก
- **Modern UI**: ออกแบบด้วยสไตล์ Dark Mode Glassmorphism โดยใช้ Tailwind CSS

---

## 🚀 วิธีการติดตั้งและรันระบบ (Getting Started)

1. **Clone repository:**
   ```bash
   git clone https://github.com/SleepzZ22/oop-020.git
   cd oop-020
   ```

2. **รันผ่าน PHP Built-in Server:**
   ```bash
   php -S 127.0.0.1:8080
   ```
   จากนั้นเปิดเบราว์เซอร์ไปที่: `http://localhost:8080`

3. **หรือรันผ่าน XAMPP / Apache:**
   - วางโฟลเดอร์โปรเจกต์ไว้ใน `C:/xampp/htdocs/`
   - เปิด Apache ใน XAMPP Control Panel
   - เข้าใช้งานผ่าน `http://localhost/<ชื่อโฟลเดอร์>/`
