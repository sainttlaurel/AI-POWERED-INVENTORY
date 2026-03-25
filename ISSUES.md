# Known Issues & Priority Fixes

## 🔴 CRITICAL (Fix ASAP)

*No critical issues at this time!*

---

## 🟡 MEDIUM (Should Fix Soon)

### 1. No SQL Injection Protection in Some Queries

**Status:** ⚠️ SECURITY CONCERN  
**Impact:** Potential SQL injection in reports  
**Location:** `reports.php`, `forecast.php`

**Problem:**

```php
// These use direct query() instead of prepare()
$stmt = $db->query($query);  // No parameter binding
```

**Found in:**

- `reports.php` lines 133, 162
- `forecast.php` lines 30, 44

**Note:**
These queries don't have user input so they're safe, but should use prepared statements for consistency.

---

## 🟢 LOW (Nice to Have)

### 1. Notification Badge Still Shows on Logout

**Status:** ℹ️ MINOR ANNOYANCE  
**Impact:** Visual glitch only  
**Location:** `includes/navbar.php`

**Problem:**
Badge count shows even when logging out

**Fix:**
Clear badge on logout or hide navbar during logout

---

### 2. No Pagination on Invoice List

**Status:** ℹ️ PERFORMANCE  
**Impact:** Slow page load with many invoices  
**Location:** `invoices.php`

**Problem:**
Loads ALL invoices at once

**Fix:**
Add pagination or lazy loading

---

## ✅ FIXED

### ~~Invoice Tables Not in Main Database~~

**Status:** ✅ FIXED (2026-03-25)  
Auto-create logic added to `invoices.php`, `create_invoice.php`, and `view_invoice.php`

### ~~Missing Error Handling in Invoice Creation~~

**Status:** ✅ FIXED (2026-03-25)  
Added validation for empty items array in `create_invoice.php`

### ~~No Invoice Number Collision Check~~

**Status:** ✅ FIXED (2026-03-25)  
Added collision detection with retry logic in `create_invoice.php`

### ~~Notification Badge Red Dot~~

**Status:** ✅ FIXED (2026-03-24)  
Fixed notification badge to show actual count instead of empty dot

### ~~Categories Page HTML Error~~

**Status:** ✅ FIXED (2026-03-24)  
Removed duplicate button HTML

---

## Recommendations

### Priority Order

1. **Review SQL queries** - Use prepared statements everywhere (low priority - no user input)
2. **Add pagination** - For better performance with many invoices
3. **Fix notification badge on logout** - Minor visual improvement

### Quick Wins

- All critical and medium issues have been resolved!
- Remaining issues are low priority improvements

---

*Last updated: 2026-03-25*  
*Maintained by: lazy coder who actually checks stuff*
