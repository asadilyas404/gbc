# 🖨️ Order Printing System - Complete Setup Guide

## ✅ **System Updated - Now Runs Every 10 Seconds!**

The auto-print command has been updated to run every 10 seconds for real-time order printing.

---

## 🚀 **Step-by-Step Setup Instructions**

### **Step 1: Database Setup**

1. **Add the `printed` column to your orders table:**
   ```sql
   ALTER TABLE orders ADD COLUMN printed BOOLEAN DEFAULT FALSE;
   ```

2. **Configure your printers in the database:**
   ```sql
   INSERT INTO business_settings (key, value) VALUES 
   ('print_keys', '{"bill_print":"Hewlett-Packard HP LaserJet P2035n","kitchen_print":"Hewlett-Packard HP LaserJet P2035n"}');
   ```

### **Step 2: Environment Configuration**

Add these lines to your `.env` file:

```env
# Enable Auto Printing (every 10 seconds)
AUTO_PRINT_ENABLED=true
AUTO_PRINT_INTERVAL=10
AUTO_PRINT_MAX_ORDERS=10

# Use Database Printers
USE_DATABASE_PRINTERS=true

# Fallback Printer (if database printers fail)
DEFAULT_PRINTER=file:///dev/usb/lp0
```

### **Step 3: Test Your Setup**

1. **List your configured printers:**
   ```bash
   php artisan printers:list
   ```

2. **Test printer connections:**
   ```bash
   # Test bill printer
   php artisan printer:test --type=bill_print
   
   # Test kitchen printer
   php artisan printer:test --type=kitchen_print
   ```

3. **Test with a specific order:**
   ```bash
   php artisan order:print 1 --printer-type=bill_print --mark-printed
   ```

### **Step 4: Enable the Scheduler**

**For Linux/Unix systems:**

1. **Add to crontab:**
   ```bash
   crontab -e
   ```

2. **Add this line:**
   ```bash
   * * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
   ```

**For Windows systems:**

1. **Use Task Scheduler** to run this command every minute:
   ```cmd
   cd C:\xampp7.4\htdocs7\gbc-project && php artisan schedule:run
   ```

**For XAMPP (Windows):**

1. **Create a batch file** (`start_scheduler.bat`):
   ```batch
   @echo off
   cd /d C:\xampp7.4\htdocs7\gbc-project
   :loop
   php artisan schedule:run
   timeout /t 60 /nobreak
   goto loop
   ```

2. **Run the batch file** or add it to Windows startup.

### **Step 5: Verify Everything Works**

1. **Check if scheduler is running:**
   ```bash
   php artisan schedule:list
   ```

2. **Monitor the auto-print process:**
   ```bash
   # Run manually to see output
   php artisan orders:auto-print --printer-type=bill_print
   ```

3. **Check logs:**
   ```bash
   tail -f storage/logs/laravel.log
   ```

---

## 🔧 **Printer Configuration Options**

### **Option 1: Database Printers (Recommended)**
- Uses your existing `business_settings` table
- Automatically maps printer names to connection strings
- Easy to manage through your admin panel

### **Option 2: Direct Connection Strings**
- Use specific connection strings in commands
- Good for testing or one-off printing

### **Option 3: Environment Variables**
- Set default printers in `.env` file
- Used as fallback when database printers fail

---

## 📋 **Available Commands**

| Command | Description | Example |
|---------|-------------|---------|
| `printers:list` | List all configured printers | `php artisan printers:list --test` |
| `printer:test` | Test printer connection | `php artisan printer:test --type=bill_print` |
| `order:print` | Print specific order | `php artisan order:print 123 --mark-printed` |
| `orders:auto-print` | Auto print unprinted orders | `php artisan orders:auto-print --printer-type=kitchen_print` |

---

## 🎯 **What Happens Next**

1. **Every 10 seconds**, the system will:
   - Look for unprinted orders from the last 10 seconds
   - Print them using your configured printers
   - Mark them as printed in the database

2. **Orders are printed when:**
   - `printed = false` in database
   - Order is from the last 10 seconds
   - Order status is pending, confirmed, or processing

3. **Printer selection:**
   - Uses `bill_print` for customer receipts
   - Uses `kitchen_print` for kitchen orders
   - Falls back to default printer if database printers fail

---

## 🚨 **Troubleshooting**

### **Common Issues:**

1. **"No printers configured"**
   - Check your `business_settings` table
   - Ensure `print_keys` has valid JSON

2. **"Printer connection failed"**
   - Check printer is connected and powered on
   - Verify connection string mapping
   - Test with `php artisan printer:test`

3. **"Scheduler not running"**
   - Check crontab is set up correctly
   - Verify `php artisan schedule:run` works manually

4. **"Orders not printing"**
   - Check `AUTO_PRINT_ENABLED=true` in `.env`
   - Verify orders have `printed=false`
   - Check order status is correct

### **Debug Commands:**

```bash
# Check scheduler status
php artisan schedule:list

# Test printer manually
php artisan printer:test --type=bill_print

# Print specific order
php artisan order:print 1 --printer-type=bill_print

# Check recent orders
php artisan tinker
>>> App\Models\Order::where('printed', false)->latest()->take(5)->get();
```

---

## ✅ **Success Indicators**

You'll know everything is working when:

1. ✅ `php artisan printers:list` shows your printers
2. ✅ `php artisan printer:test` returns "✓ Printer connection successful!"
3. ✅ `php artisan orders:auto-print` finds and prints orders
4. ✅ Orders are automatically marked as `printed=true`
5. ✅ Receipts are printed on your thermal printer

---

## 🎉 **You're All Set!**

Your order printing system is now configured to:
- ✅ Run every 10 seconds automatically
- ✅ Use your database-configured printers
- ✅ Print both bill and kitchen receipts
- ✅ Handle errors gracefully
- ✅ Mark orders as printed

**Next time you place an order through the waiter system, it should automatically print within 10 seconds!**
