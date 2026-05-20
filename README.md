# Government File Management System (Gov-FMS)
## Setup Instructions

### Requirements
- PHP 8.0+
- MySQL 5.7+ / MariaDB 10.3+
- Apache / Nginx with mod_rewrite
- `uploads/` directory writable by web server

---

### 1. Database Setup
```sql
-- Run database.sql in MySQL:
mysql -u root -p < database.sql
```
Then update the admin password hash:
```php
// Generate hash in PHP:
echo password_hash('YourPassword123', PASSWORD_BCRYPT);
// Paste into: UPDATE users SET password_hash='...' WHERE username='admin';
```

---

### 2. Configure Database
Edit `config/db.php`:
```php
define('DB_HOST', 'localhost');
define('DB_USER', 'your_db_user');
define('DB_PASS', 'your_db_password');
define('DB_NAME', 'gov_fms');
```

---

### 3. File Structure
```
gov_fms/
├── database.sql            ← Run this first
├── config/
│   ├── db.php              ← Database connection
│   └── auth.php            ← Session & auth helpers
├── includes/
│   ├── header.php          ← Sidebar + HTML head
│   └── footer.php          ← Close layout tags
├── assets/
│   └── css/style.css       ← All styles
├── uploads/
│   └── files/              ← Uploaded file attachments (auto-created)
├── login.php               ← Login page
├── logout.php              ← Logout handler
├── dashboard.php           ← Main dashboard
├── files_list.php          ← Browse & filter files
├── files_add.php           ← Register new file
├── files_view.php          ← File detail + comments
├── files_edit.php          ← Edit file
├── files_delete.php        ← Delete file (admin/manager)
├── users.php               ← User management (admin only)
└── audit_log.php           ← Audit trail (admin/manager)
```

---

### 4. Default Login
| Field    | Value   |
|----------|---------|
| Username | `admin` |
| Password | Set in step 1 above |

---

### 5. Roles
| Role    | Permissions                                        |
|---------|----------------------------------------------------|
| admin   | Full access: users, audit log, delete files        |
| manager | View audit log, delete files, manage assignments   |
| staff   | Create, view, edit files; add comments             |

---

### 6. Features
- File registration with unique reference numbers (GOV-YEAR-XXXXXX)
- File upload (PDF, DOCX, XLSX, images, up to 10 MB)
- Status tracking: open → in_review → approved/rejected → closed
- Priority levels: low, normal, high, urgent
- Department & category classification
- File movement / transfer history timeline
- Comments & notes per file
- Full audit log of all actions
- User management with role-based access
- Confidential file flag
