# shifting_dsc

**ShiftTrack DSC** is a specialized PHP inventory management web application built for office relocation and asset tracking. It allows teams to pack items at the old office, capture photos with an in-built live camera, scan barcode/QR serial numbers, track transit, and verify arrival at the new office via an interactive checklist with photo proof of condition.

---

## 🌟 Key Features

### 1. Old Office Packing & Registration
- **Product Identification**: Record **Brand**, **Model**, **Serial Number (S/N)**, **Category**, **Source Location**, and **Destination Location**.
- **In-Built Camera Photo Capture**: Capture multiple photos of items and packaging directly from your webcam or mobile back-camera without leaving the software.
- **Barcode / QR Code Scanner**: In-browser scanner with Web Audio beep feedback to scan serial numbers directly into the form.
- **Multi-Photo Upload**: Supports both direct camera snaps and traditional image file uploads with instant preview thumbnails.

### 2. Product Reached Checklist (New Office)
- **Interactive Arrival Checklist**: List of all assets with real-time status indicators (Pending Arrival vs Reached).
- **1-Click Arrival Verification**: Check an item to open the arrival modal.
- **In-Built Arrival Camera**: Capture fresh photos upon unpacking at the new office as indisputable proof of arrival condition.
- **Condition Assessment**: Record condition status (*Intact / Perfect*, *Minor Scratches*, *Damaged in Transit*, *Missing Accessories*).
- **Instant Barcode Checkoff**: Scan the barcode on the moving box or device to immediately bring up and tick off the item in the checklist.

### 3. Comparison & Item Detail Section
- **Side-by-Side Visual Comparison**: View photos taken at the old office alongside photos taken on arrival at the new office.
- **Printable Relocation Tag**: Displays auto-generated Code-128 barcode, item specs, and destination desk/bay for sticking on moving boxes.
- **Audit History**: Log of packing timestamp, mover notes, arrival timestamp, and receiver name.

---

## 🌐 Hostinger / cPanel Deployment & Database Setup

### Step 1: Upload Files
Upload or git clone the project to `public_html` (or your subdomain directory) in Hostinger File Manager or Git deployment.

### Step 2: Configure `.env`
1. In your project root on Hostinger, copy `.env.example` to `.env` (or create a new `.env` file):
   ```env
   DB_DRIVER=mysql
   DB_HOST=localhost
   DB_PORT=3306
   DB_NAME=u123456789_shifting_dsc
   DB_USER=u123456789_dsc_user
   DB_PASS=YourStrongHostingerPassword
   ```
2. **Where to find these in Hostinger hPanel**:
   - Go to **Databases** -> **MySQL Databases**.
   - Note the **MySQL Database Name** and **MySQL Username** (they always include your account prefix, e.g., `u123456789_...`).
   - The host is usually `localhost`.
   - Make sure your database user has **All Privileges** assigned.

### Step 3: Automatic Table Initialization
- On first visit, `config.php` will **automatically connect to your MySQL database and create all tables** (`items`, `item_images`, `activity_logs`) and populate the initial seed records!
- Alternatively, you can also import `database.sql` into phpMyAdmin.

> **Security Note:** The `.env` file is excluded in `.gitignore` so your private database credentials will never be committed or exposed to GitHub.

---

## 📁 Database Schema

The database consists of three relational tables:

```sql
-- 1. Main Items Table
CREATE TABLE items (
    id INT AUTO_INCREMENT PRIMARY KEY,
    brand VARCHAR(100) NOT NULL,
    model VARCHAR(100) NOT NULL,
    serial_number VARCHAR(150) NOT NULL UNIQUE,
    category VARCHAR(100) DEFAULT 'General',
    old_location VARCHAR(150) DEFAULT '',
    new_location VARCHAR(150) DEFAULT '',
    status ENUM('packed','in_transit','reached','damaged') DEFAULT 'packed',
    old_condition TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    reached_at DATETIME NULL,
    reached_by VARCHAR(100) NULL,
    reached_condition ENUM('perfect','minor_damage','damaged','missing_parts') NULL,
    reached_notes TEXT
);

-- 2. Item Photos Table (Stores multiple photos per stage)
CREATE TABLE item_images (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NOT NULL,
    stage ENUM('old_office','reached_office') NOT NULL,
    image_path VARCHAR(255) NOT NULL,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
    FOREIGN KEY (item_id) REFERENCES items(id) ON DELETE CASCADE
);

-- 3. Relocation Activity Log Table
CREATE TABLE activity_logs (
    id INT AUTO_INCREMENT PRIMARY KEY,
    item_id INT NULL,
    action VARCHAR(100) NOT NULL,
    details TEXT,
    created_at DATETIME DEFAULT CURRENT_TIMESTAMP
);
```

---

## 🚀 How to Run Locally

```bash
php -S localhost:8000
```
Open **`http://localhost:8000`** in your browser.

---

## 📄 License
MIT License. Created for DSC Office Relocation.
