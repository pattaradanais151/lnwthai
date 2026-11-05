# PROJECT: Lnwthai — Multi-Admin Game Account Store

คุณคือ Senior Full-Stack PHP Developer, System Architect, Database Engineer, UI/UX Designer และ Application Security Engineer

จงพัฒนาเว็บไซต์ **Lnwthai** ร้านจำหน่ายบัญชีเกมออนไลน์ที่มีระบบจัดการร้านค้าแบบ Multi-Admin รองรับผู้ดูแลหลายคน สามารถกำหนดสิทธิ์ แบ่งหน้าที่ และตรวจสอบประวัติการดำเนินงานของผู้ดูแลแต่ละคนได้

---

# 1. PROJECT INFORMATION

- Project Name: Lnwthai
- Planned Domain: https://lnwthai.online/ (ยังไม่ได้จดทะเบียน)
- Hosting: InfinityFree Free Hosting
- Backend: PHP Native 8.x (ตามเวอร์ชันที่ Hosting รองรับ)
- Primary Database: MySQL / MariaDB ของ InfinityFree
- Additional Service: Supabase Storage
- Frontend: HTML5, CSS3, Vanilla JavaScript (ES6+)
- Currency: THB
- Timezone: Asia/Bangkok
- Primary Language: Thai
- Secondary Language: English

## Mandatory Tech Stack

อนุญาตเฉพาะ:
- PHP Native
- HTML5
- CSS3
- Vanilla JavaScript
- MySQL / MariaDB
- PDO
- PHP Sessions
- PHP cURL
- Supabase Storage REST API
- Custom CSS
- Lucide Icons ผ่าน CDN หรือ SVG

ห้ามใช้ React, Vue, Next.js, TypeScript, Node.js, Laravel, Composer, npm, Vite หรือระบบ Build ที่จำเป็นต้องใช้บน Hosting

Repository ต้องประกอบด้วย HTML, CSS, JavaScript และ PHP เท่านั้น พร้อมนำไป Upload ผ่าน FTP หรือ File Manager ได้โดยตรง

---

# 2. SYSTEM ARCHITECTURE

กำหนดหน้าที่แต่ละระบบให้ชัดเจน

**PHP:** Backend, Business Logic, Authentication, Authorization, API และการประมวลผลทั้งหมด

**MySQL:** Primary Database สำหรับ Users, Admins, Roles, Permissions, Games, Products, Inventory, Orders, Payments, Settings และ Audit Logs

**Supabase Storage:** จัดเก็บรูปสินค้า รูปประกอบ และหลักฐานการชำระเงิน

ข้อกำหนด:
- ไม่ใช้ Supabase PostgreSQL ซ้ำซ้อนกับ MySQL
- เชื่อม Supabase ผ่าน PHP cURL และ HTTPS
- ห้ามเปิดเผย Secret Key หรือ Service Role Key ใน Frontend
- Product Images ใช้ Public Bucket
- Payment Evidence ใช้ Private Bucket
- การเข้าถึงไฟล์ส่วนตัวต้องผ่านการตรวจสอบสิทธิ์
- หาก Hosting ไม่รองรับความสามารถใด ต้องแจ้งข้อจำกัดและออกแบบทางเลือกที่ใช้งานได้จริง

---

# PHASE 1 — FOUNDATION & DATABASE

เป้าหมาย: วางโครงสร้างโปรเจกต์ ฐานข้อมูล และระบบพื้นฐานทั้งหมด

## 1. Project Structure

ออกแบบโครงสร้างแบบ Modular แยกหน้าที่แต่ละส่วน เช่น

- assets/css/
- assets/js/
- assets/images/
- config/
- includes/
- pages/
- actions/
- admin/
- admin/includes/
- uploads/ (เฉพาะไฟล์ที่จำเป็นและไม่สามารถใช้ Supabase ได้)

มีไฟล์หลัก เช่น index.php, .htaccess และไฟล์ Configuration

ห้ามรวมโค้ดทั้งหมดไว้ในไฟล์เดียว และต้องแยก Public Pages, Backend Actions และ Admin Panel

ไฟล์ที่มี Credentials ต้องอยู่นอก Public Web Root เมื่อทำได้ หากทำไม่ได้ต้องจำกัดการเข้าถึงและตรวจสอบการป้องกันจริง

## 2. Database Design

ออกแบบ MySQL Schema พร้อม Primary Key, Foreign Key, Indexes, Unique Constraints และ Timestamps

ตารางหลักที่ต้องมี:

- users
- roles
- permissions
- user_roles
- role_permissions
- games
- categories
- products
- game_accounts
- product_images
- orders
- order_items
- payment_submissions
- order_status_history
- settings
- banners
- audit_logs
- login_attempts

รองรับความสัมพันธ์ระหว่างสินค้า สต็อก สมาชิก คำสั่งซื้อ และผู้ดูแลระบบ

ต้องมี SQL Schema พร้อม Import ผ่าน phpMyAdmin

## 3. Authentication & Authorization

สร้างระบบ:
- Customer Registration / Login
- Admin Login
- Logout
- Password Hashing
- Session Management
- Role-Based Access Control (RBAC)
- Permission Checking
- CSRF Protection

กำหนด Roles ดังนี้:

| Role | สิทธิ์ |
|---|---|
| Owner | เจ้าของระบบ มีสิทธิ์สูงสุดทุกอย่าง |
| Super Admin | จัดการระบบและผู้ดูแลตามสิทธิ์ที่กำหนด |
| Admin | จัดการร้านค้าตามสิทธิ์ที่ได้รับ |
| Staff | ทำงานเฉพาะส่วนที่ได้รับอนุญาต |
| Customer | ซื้อสินค้าและจัดการบัญชีตัวเอง |

ต้องรองรับ Admin หลายบัญชีและหลายคน Login พร้อมกันได้ โดยแต่ละบัญชีมีตัวตนและ Session แยกจากกัน

ห้ามมีระบบสมัคร Admin แบบสาธารณะ การสร้าง Admin ใหม่ต้องได้รับอนุญาตจาก Owner หรือผู้มีสิทธิ์

Owner ต้องไม่สามารถถูกลบหรือถูกลดสิทธิ์โดย Admin คนอื่นได้

## 4. Supabase Integration

สร้างระบบ PHP สำหรับ:
- Upload Image
- Delete Image
- Retrieve Image
- Upload Payment Evidence
- Generate Signed URL
- Manage Public / Private Buckets

Credentials ต้องเก็บอย่างปลอดภัย ไม่ Hardcode ใน Source Code ที่เผยแพร่

## 5. Foundation Security

- PDO Prepared Statements
- Input Validation
- Output Escaping
- Session Regeneration
- Secure Cookies เมื่อใช้ HTTPS
- CSRF Tokens
- Access Control
- Error Handling
- Security Headers

**Acceptance Criteria:**
- PHP เชื่อม MySQL ได้จริง
- SQL Schema Import ได้
- ระบบ RBAC ทำงานได้
- Admin แต่ละคนมีบัญชีของตัวเอง
- Supabase Storage เชื่อมต่อได้เมื่อกำหนด Credentials
- ไม่มี Secret Key ใน Frontend

---

# PHASE 2 — STOREFRONT & CUSTOMER SYSTEM

เป้าหมาย: พัฒนาหน้าบ้านและระบบสมาชิกให้สมบูรณ์

## 1. Homepage

ประกอบด้วย:
- Navigation Bar
- Lnwthai Logo
- Hero Section
- Search
- Featured Games
- Featured Products
- Latest Products
- Special Offers
- How to Buy
- Trust & Security
- FAQ
- Footer

## 2. Shop & Product Catalog

รองรับ:
- แสดงสินค้าทั้งหมด
- ค้นหาชื่อเกมและสินค้า
- Filter ตามเกม
- Filter ตามราคา
- Filter ตามสถานะสินค้า
- Sort ราคาต่ำไปสูง
- Sort ราคาสูงไปต่ำ
- Sort สินค้าใหม่ล่าสุด
- Pagination

ข้อมูลทั้งหมดต้องดึงจาก MySQL จริง

## 3. Product Details

แสดง:
- Product Name
- Game
- Product Images
- Price
- Description
- Platform
- Server / Region
- Level / Rank
- Skins / Items / Characters
- Additional Details
- Availability
- Buy Now

ห้ามเปิดเผย Username, Password หรือข้อมูลลับของบัญชีเกมก่อนซื้อสำเร็จ

## 4. Customer System

รองรับ:
- Register / Login / Logout
- Profile Management
- Change Password
- My Orders
- Order History
- Payment Status
- Purchased Accounts
- Account Delivery Details

ลูกค้าต้องเข้าถึงเฉพาะข้อมูลของตัวเอง และดูข้อมูลบัญชีเกมได้หลัง Admin ยืนยันการชำระเงินเท่านั้น

## 5. UI/UX

ออกแบบโดยอ้างอิงแนวทาง Apple.com:

- Premium Minimal Design
- Clean Layout
- White / Light Theme
- Full Dark Mode
- Responsive Design
- Smooth Animations
- Modern Product Cards
- Subtle Shadows
- Rounded Corners
- Consistent Typography

รองรับ Desktop, Laptop, Tablet และ Mobile

**Acceptance Criteria:**
- หน้าเว็บทำงานจริง
- ระบบสมาชิกใช้งานได้
- ค้นหาและกรองสินค้าได้
- ลูกค้าดูคำสั่งซื้อของตัวเองได้
- Responsive ทุกขนาดหน้าจอ

---

# PHASE 3 — E-COMMERCE & MULTI-ADMIN PANEL

เป้าหมาย: สร้างระบบซื้อขายและระบบหลังบ้านที่รองรับผู้ดูแลหลายคน

## 1. Order & Checkout

- Buy Now
- Checkout
- Order Summary
- Order Number
- Total Amount
- Order Status
- Order History

ต้องใช้ MySQL Transactions และ Row Locking เพื่อป้องกันการขายบัญชีซ้ำหรือการจอง Inventory ชนกัน

## 2. Payment System

ใช้ Manual Payment Verification ในเวอร์ชัน InfinityFree

Workflow:

1. ลูกค้าสั่งซื้อ
2. ระบบสร้าง Order
3. แสดงยอดเงินและช่องทางชำระเงิน
4. ลูกค้า Upload Payment Evidence
5. PHP ส่งไฟล์ไป Supabase Private Bucket
6. บันทึกข้อมูลลง MySQL
7. Admin ตรวจสอบหลักฐาน
8. Admin ยืนยันหรือปฏิเสธ
9. ระบบอัปเดตสถานะ
10. ลูกค้าเข้ารับสินค้าได้เมื่อชำระเงินได้รับการยืนยัน

Payment Status:
- Pending
- Submitted
- Under Review
- Verified
- Rejected
- Refunded

ห้ามยืนยันการชำระเงินจากรูปภาพเพียงอย่างเดียว และไม่พึ่ง Webhook ที่ Hosting ไม่รองรับ

## 3. Account Delivery & Inventory

- Add / Edit / Delete Game Accounts
- Available / Reserved / Sold / Disabled
- Assign Inventory ให้ Order
- ตรวจสอบการชำระเงินก่อนส่งมอบ
- ประวัติการส่งมอบ
- ป้องกันการขายซ้ำ

ข้อมูล Username / Password ของบัญชีเกมต้องเข้ารหัสด้วยวิธีที่เหมาะสม เช่น AES-256-GCM โดยแยก Encryption Key จาก Database และ Source Code

หาก Hosting ไม่สามารถจัดเก็บ Encryption Key ได้อย่างปลอดภัย ต้องแจ้งข้อจำกัดก่อนเปิดใช้ระบบจัดเก็บข้อมูลลับ

## 4. Multi-Admin Dashboard

สร้าง Admin Panel แยกจาก Customer Pages และมีระบบ Login ของ Admin

Dashboard แสดง:
- Total Sales
- Total Orders
- Total Customers
- Total Admins
- Available Inventory
- Sold Accounts
- Pending Payments
- Recent Orders
- Sales Overview

## 5. Admin Management

สร้างระบบจัดการ Admin หลายคน โดย Owner สามารถ:

- Add Admin
- Edit Admin
- Disable Admin
- Reactivate Admin
- Assign Role
- Assign Permissions
- Reset Admin Access ตามกระบวนการที่ปลอดภัย
- View Admin Activity
- View Last Login
- Revoke Sessions

Admin ที่ได้รับอนุญาตสามารถจัดการผู้ดูแลระดับที่ต่ำกว่าตาม Permission ที่กำหนดได้

ข้อบังคับ:
- ห้ามใช้บัญชี Admin ร่วมกัน
- แต่ละคนต้องมี Account และ Session ของตัวเอง
- ห้ามยกระดับสิทธิ์ตัวเอง
- ห้ามแก้ไขหรือลบ Owner
- Permission ต้องตรวจสอบจาก Backend ทุกครั้ง ไม่ใช่ซ่อนปุ่มเฉพาะ Frontend
- บันทึกผู้ดำเนินการและเวลาที่ทำรายการทุกครั้ง

## 6. Permission Management

กำหนด Permission แยกตามหน้าที่ เช่น:

- Dashboard View
- Product View / Create / Edit / Delete
- Inventory View / Manage
- Order View / Manage
- Payment View / Verify / Reject
- Customer View / Manage
- Admin View / Manage
- Role Management
- Website Settings
- Reports
- Audit Logs

Owner สามารถกำหนดสิทธิ์ตาม Role หรือรายบุคคลได้ โดยสิทธิ์ระดับสูงต้องอยู่ภายใต้การควบคุมของ Owner

## 7. Product Management

- Add / Edit / Delete Products
- Manage Categories
- Manage Games
- Upload Product Images
- Manage Product Gallery
- Set Price
- Set Description
- Set Availability

## 8. Customer & Order Management

- View Customers
- Search Customers
- View Customer Orders
- Suspend / Reactivate Customers
- View All Orders
- Update Order Status
- Verify / Reject Payments
- Process Delivery
- Manage Refund Records

## 9. Admin Audit System

บันทึกกิจกรรมของ Admin ทุกคน เช่น:

- Login / Logout
- Create / Edit / Delete Product
- Inventory Changes
- Order Changes
- Payment Verification
- Customer Management
- Admin Management
- Permission Changes
- Settings Changes

Audit Logs ต้องระบุ Admin ID, Action, Target, Timestamp และรายละเอียดที่จำเป็น โดยไม่บันทึกรหัสผ่านหรือ Secret Key

## 10. Store Settings

จัดการ:
- Store Name
- Logo
- Description
- Contact Information
- Payment Instructions
- Social Media
- Announcement Banner
- Terms of Service
- Privacy Policy
- Refund Policy

**Acceptance Criteria:**
- Admin หลายคน Login พร้อมกันได้
- แต่ละคนเห็นเฉพาะเมนูและข้อมูลตามสิทธิ์
- Backend ป้องกันการเข้าถึงที่ไม่ได้รับอนุญาต
- Owner จัดการ Admin และ Permissions ได้
- ตรวจสอบย้อนหลังได้ว่าใครทำอะไร
- ระบบ Checkout, Payment และ Delivery ทำงานจริง

---

# PHASE 4 — SECURITY, TESTING & DEPLOYMENT

เป้าหมาย: เตรียมเว็บไซต์สำหรับ Production

## 1. Security Audit

ตรวจสอบ:
- SQL Injection
- XSS
- CSRF
- IDOR
- Broken Access Control
- Session Hijacking
- Authentication Bypass
- Privilege Escalation
- Unauthorized Admin Access
- File Upload Vulnerabilities
- Information Disclosure
- Sensitive Data Exposure

ตรวจสอบสิทธิ์ทุก Endpoint ทั้ง Customer และ Admin

## 2. Performance & Optimization

- Optimize CSS / JavaScript
- Image Compression
- Lazy Loading
- Database Indexing
- Query Optimization
- Pagination
- Browser Caching
- Minimize HTTP Requests

## 3. SEO

- Meta Tags
- Open Graph
- Sitemap.xml
- Robots.txt
- Canonical URLs
- Favicon
- 404 / Error Pages
- Thai / English Metadata

## 4. Legal Pages

จัดทำ:
- Terms of Service
- Privacy Policy
- Refund Policy
- Account Transfer Policy
- Cookie Policy
- Contact Us

ต้องจำหน่ายเฉพาะบัญชีที่มีสิทธิ์จำหน่ายและสามารถโอนสิทธิ์ตามข้อกำหนดของผู้ให้บริการเกม ห้ามสนับสนุนการขายบัญชีที่ถูกขโมยหรือได้มาโดยมิชอบ

## 5. Testing

ทดสอบอย่างน้อย:

**Frontend:** Homepage, Shop, Search, Filter, Product Details, Responsive

**Customer:** Register, Login, Profile, Checkout, Order History, Account Delivery

**Multi-Admin:** Admin Login, Multiple Sessions, Role Management, Permission Management, Admin Creation, Disable Admin, Audit Logs, Privilege Escalation Protection

**E-Commerce:** Order Creation, Duplicate Purchase Prevention, Payment Upload, Payment Verification, Delivery

**Security:** SQL Injection, XSS, CSRF, IDOR, Session Security, File Upload, Unauthorized Access

รายงานผลเป็น Passed / Failed / Not Tested ห้ามอ้างว่าผ่านหากยังไม่ได้ทดสอบจริง

## 6. InfinityFree Deployment

จัดทำคู่มือติดตั้ง:

1. สร้าง Hosting Account
2. ตรวจสอบ PHP Version และ Extensions
3. สร้าง MySQL Database
4. Import SQL ผ่าน phpMyAdmin
5. ตั้งค่า Configuration
6. ตั้งค่า Supabase
7. Upload Files ผ่าน FTP
8. ตั้งค่า Domain เมื่อจดทะเบียนแล้ว
9. ตั้งค่า DNS และ SSL
10. ทดสอบเว็บไซต์ทุกระบบ
11. สร้าง Owner Account อย่างปลอดภัย
12. ตรวจสอบสิทธิ์ Admin และระบบ Audit Logs

ห้ามเปิดระบบสมัคร Admin สาธารณะ และห้ามทิ้ง Installation Script ที่สามารถสร้าง Owner ซ้ำได้หลังติดตั้งเสร็จ

## 7. Final Deliverables

ต้องส่งมอบ:

- Complete Source Code
- Full Project Structure
- MySQL SQL Schema
- Supabase Setup Guide
- Multi-Admin & RBAC Documentation
- Installation Guide
- InfinityFree Deployment Guide
- Security Checklist
- Testing Report
- Maintenance Guide
- Known Limitations

---

# GLOBAL DEVELOPMENT RULES

1. พัฒนาเพียง Phase ที่ได้รับมอบหมาย ห้ามข้าม Phase
2. เริ่มต้น Phase 1 ก่อนเสมอ
3. ตรวจสอบไฟล์เดิมก่อนแก้ไขทุกครั้ง
4. รักษาความสามารถเดิม ห้ามลบโค้ดโดยไม่มีเหตุผล
5. ใช้โค้ดที่สามารถใช้งานจริง ไม่ใช่ Mockup
6. ห้ามสร้างปุ่มหรือฟังก์ชันที่ยังไม่มีการทำงาน
7. ห้าม Hardcode Credentials
8. ต้องแสดงชื่อและ Path ของทุกไฟล์ที่สร้างหรือแก้ไข
9. เมื่อส่งมอบไฟล์ ต้องให้โค้ดฉบับสมบูรณ์ ไม่ตัดส่วนสำคัญ
10. แยก SQL Schema ออกจาก PHP Source Code
11. ต้องคำนึงถึงข้อจำกัดของ InfinityFree ตลอดการพัฒนา
12. ต้องทดสอบระบบก่อนสรุปว่าเสร็จสมบูรณ์
13. หากพบข้อจำกัดหรือความขัดแย้งของ Tech Stack ให้แจ้งและเสนอทางเลือกที่เหมาะสม
14. รักษารูปแบบ Design System เดียวกันทั้งเว็บไซต์
15. ต้องรองรับการเพิ่ม Admin และขยายระบบในอนาคต

เมื่อจบแต่ละ Phase ให้รายงาน:
- สิ่งที่พัฒนาเสร็จ
- ไฟล์ที่สร้าง
- ไฟล์ที่แก้ไข
- Database Changes
- ผลการทดสอบ
- ปัญหาที่ยังเหลือ
- ขั้นตอนที่ต้องทำต่อ

# START DEVELOPMENT

เริ่มต้นที่ **PHASE 1 — FOUNDATION & DATABASE** เท่านั้น

เริ่มจากวิเคราะห์ Architecture, Project Structure, Database Relationships และ RBAC ก่อนลงมือเขียนโค้ด

อย่าเริ่ม Phase 2–4 จนกว่า Phase ปัจจุบันจะเสร็จและผ่าน Acceptance Criteria

เป้าหมายคือ Lnwthai ที่เป็นระบบร้านค้าออนไลน์จริง รองรับลูกค้าและผู้ดูแลหลายคน มีระบบรักษาความปลอดภัยที่เหมาะสม ใช้งานบน InfinityFree ได้ และสามารถย้าย Hosting ในอนาคตได้โดยง่าย

**Lnwthai — Premium Digital Game Account Store**

**Planned Domain:** https://lnwthai.online/
**Backend:** PHP Native  
**Database:** MySQL  
**Storage:** Supabase  
**Hosting:** InfinityFree  
**Architecture:** Multi-Admin + RBAC