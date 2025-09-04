# Order Printing Setup Guide

This guide explains how to set up and use the automatic order printing functionality using the `mike42/escpos-php` package.

## Installation

The `mike42/escpos-php` package has been installed via Composer. No additional setup is required.

## Configuration

### 1. Database Printer Configuration

The system can use printer names stored in your database. Configure printers in the `business_settings` table:

```sql
INSERT INTO business_settings (key, value) VALUES 
('print_keys', '{"bill_print":"Hewlett-Packard HP LaserJet P2035n","kitchen_print":"Hewlett-Packard HP LaserJet P2035n"}');
```

### 2. Environment Variables

Add these variables to your `.env` file:

```env
# Printer Configuration
DEFAULT_PRINTER=file:///dev/usb/lp0
USE_DATABASE_PRINTERS=true
AUTO_PRINT_ENABLED=true
AUTO_PRINT_INTERVAL=10
AUTO_PRINT_MAX_ORDERS=10

# Specific Printer Connections (fallback)
KITCHEN_PRINTER=file:///dev/usb/lp0
RECEIPT_PRINTER=file:///dev/usb/lp1
TEST_PRINTER=file://C:\temp\receipt.txt
```

### 2. Printer Connection Strings

#### File Printers (USB/Serial)
- Linux USB: `file:///dev/usb/lp0`
- Linux Serial: `file:///dev/ttyUSB0`
- Windows Test: `file://C:\temp\receipt.txt`

#### Network Printers
- IP Address: `network://192.168.1.100:9100`
- Hostname: `network://printer.local:9100`

## Database Setup

Make sure you have added the `printed` column to your orders table:

```sql
ALTER TABLE orders ADD COLUMN printed BOOLEAN DEFAULT FALSE;
```

## Commands

### 1. List Database Printers

```bash
# List all configured printers
php artisan printers:list

# List and test all printers
php artisan printers:list --test
```

### 2. Test Printer Connection

```bash
# Test database printer (bill_print)
php artisan printer:test

# Test specific database printer type
php artisan printer:test --type=kitchen_print

# Test direct connection
php artisan printer:test "file:///dev/usb/lp0"
```

### 3. Print Specific Order

```bash
# Print using database printer (bill_print)
php artisan order:print 123 --mark-printed

# Print using specific database printer type
php artisan order:print 123 --printer-type=kitchen_print --mark-printed

# Print using direct connection
php artisan order:print 123 --printer="file:///dev/usb/lp0" --mark-printed
```

### 4. Auto Print Orders

```bash
# Print orders using database printer (bill_print)
php artisan orders:auto-print

# Print orders using kitchen printer
php artisan orders:auto-print --printer-type=kitchen_print

# Print orders from last 30 seconds, max 5 orders
php artisan orders:auto-print --seconds=30 --limit=5

# Use specific printer connection
php artisan orders:auto-print --printer="network://192.168.1.100:9100"
```

## Automatic Scheduling

The auto print command is scheduled to run every 10 seconds when `AUTO_PRINT_ENABLED=true` in your `.env` file.

To enable the scheduler, add this to your crontab:

```bash
* * * * * cd /path/to/your/project && php artisan schedule:run >> /dev/null 2>&1
```

## Printer Setup

### USB/Serial Printers

1. Connect your thermal printer via USB
2. Check the device path: `ls /dev/usb/` or `ls /dev/ttyUSB*`
3. Set appropriate permissions: `sudo chmod 666 /dev/usb/lp0`
4. Test with: `echo "test" > /dev/usb/lp0`

### Network Printers

1. Configure your printer with a static IP
2. Enable raw printing (port 9100)
3. Test connectivity: `telnet 192.168.1.100 9100`

## Troubleshooting

### Common Issues

1. **Permission Denied**
   ```bash
   sudo chmod 666 /dev/usb/lp0
   sudo usermod -a -G lp $USER
   ```

2. **Printer Not Found**
   - Check device path: `ls /dev/usb/`
   - Verify printer is connected and powered on
   - Try different USB ports

3. **Network Printer Issues**
   - Verify IP address and port
   - Check firewall settings
   - Test with telnet: `telnet IP_ADDRESS 9100`

### Testing

1. Test printer connection:
   ```bash
   php artisan printer:test "file:///dev/usb/lp0"
   ```

2. Test with a specific order:
   ```bash
   php artisan order:print 1 --printer="file:///dev/usb/lp0"
   ```

3. Check logs:
   ```bash
   tail -f storage/logs/laravel.log
   ```

## Receipt Format

The printed receipts include:
- Restaurant header
- Order details (ID, serial, date, type, table)
- Customer information (if available)
- Itemized order with variations and addons
- Subtotal, discounts, delivery, tax
- Total amount
- Order status and footer

## Customization

To customize the receipt format, modify the `OrderPrintService` class in `app/Services/OrderPrintService.php`.

## Support

For issues with the escpos-php package, refer to:
- [mike42/escpos-php GitHub](https://github.com/mike42/escpos-php)
- [ESC/POS Documentation](https://reference.epson-biz.com/modules/ref_escpos/index.php)
