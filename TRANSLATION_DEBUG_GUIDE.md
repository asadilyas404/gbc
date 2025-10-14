# Translation Debug Guide - Oracle Database

## Critical Issues Found

### Issue 1: ID Assignment Conflict ⚠️
**Problem:** Both Laravel model AND Oracle trigger are managing IDs
- **Laravel:** Uses `MAX(id) + 1` in model boot method
- **Oracle Trigger:** Uses sequence `translations_id_seq`
- **Result:** Race conditions and potential primary key violations

### Issue 2: Missing UNIQUE Constraint ⚠️
**Problem:** `updateOrCreate()` cannot find existing records without unique constraint
- Needs: `(translationable_type, translationable_id, locale, key)` to be unique
- **Result:** Duplicate entries or failed updates

---

## SQL Fixes to Run in Oracle

```sql
-- 1. Add UNIQUE constraint (CRITICAL for updateOrCreate)
ALTER TABLE ROYAL_DEPLOYLOGIC.TRANSLATIONS 
ADD CONSTRAINT TRANSLATIONS_UNIQUE_TRANS 
UNIQUE (TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY);

-- 2. Ensure sequence exists
CREATE SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq
START WITH 1000
INCREMENT BY 1
NOCACHE
NOCYCLE;

-- 3. Fix trigger to ALWAYS use sequence
CREATE OR REPLACE TRIGGER ROYAL_DEPLOYLOGIC.TRANSLATIONS_ID_TRG
BEFORE INSERT ON ROYAL_DEPLOYLOGIC.TRANSLATIONS
FOR EACH ROW
BEGIN
    -- Always use sequence, ignore any provided ID
    SELECT translations_id_seq.NEXTVAL INTO :new.ID FROM dual;
END;
/

-- 4. Update sequence to be higher than existing IDs (if table has data)
DECLARE
    v_max_id NUMBER;
BEGIN
    SELECT NVL(MAX(ID), 0) INTO v_max_id FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;
    IF v_max_id > 0 THEN
        EXECUTE IMMEDIATE 'DROP SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq';
        EXECUTE IMMEDIATE 'CREATE SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq START WITH ' || (v_max_id + 1) || ' INCREMENT BY 1 NOCACHE NOCYCLE';
    END IF;
END;
/
```

---

## Diagnostic Queries

### 1. Check Current State
```sql
-- Count all translations
SELECT COUNT(*) as total_translations FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

-- Check addon translations specifically
SELECT 
    t.ID,
    t.TRANSLATIONABLE_ID as ADDON_ID,
    t.LOCALE,
    t.KEY,
    SUBSTR(t.VALUE, 1, 50) as VALUE_PREVIEW
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS t
WHERE t.TRANSLATIONABLE_TYPE = 'App\Models\AddOn'
ORDER BY t.TRANSLATIONABLE_ID, t.LOCALE;
```

### 2. Check for Duplicates
```sql
-- Find duplicate translations (shouldn't exist)
SELECT 
    TRANSLATIONABLE_TYPE,
    TRANSLATIONABLE_ID,
    LOCALE,
    KEY,
    COUNT(*) as duplicate_count
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
GROUP BY TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY
HAVING COUNT(*) > 1;
```

### 3. Check Constraints
```sql
-- List all constraints on translations table
SELECT 
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    STATUS
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'TRANSLATIONS';
```

### 4. Check Sequence
```sql
-- Get current sequence value
SELECT translations_id_seq.CURRVAL FROM DUAL;

-- Get next sequence value (will increment)
SELECT translations_id_seq.NEXTVAL FROM DUAL;
```

---

## Testing Steps

### Step 1: Clear Laravel Cache
```bash
php artisan config:clear
php artisan cache:clear
php artisan view:clear
```

### Step 2: Try Updating an Addon
1. Go to vendor addon edit page
2. Add translations in multiple languages
3. Click Update
4. **Check logs immediately**: `storage/logs/laravel.log`

### Step 3: Check Log Output
Look for these log entries:
```
=== Translation Debug Start ===
Model: App\Models\AddOn, Data ID: 123, Field: name
Languages: ["default","ar","en"]
Name values: ["اسم افتراضي","اسم عربي","English name"]
Processing index 0, lang: default
Processing index 1, lang: ar
Saving translation for ar: اسم عربي
Successfully saved translation for ar
=== Translation Debug End ===
```

**If you see errors**, they will show:
```
Translation Error at line XX: ORA-00001: unique constraint violated
```

### Step 4: Verify in Database
```sql
-- Check if translation was saved
SELECT * FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
WHERE TRANSLATIONABLE_TYPE = 'App\Models\AddOn'
  AND TRANSLATIONABLE_ID = YOUR_ADDON_ID
ORDER BY LOCALE;
```

---

## Common Oracle Errors

### Error: ORA-00001 (Duplicate Key)
**Cause:** Unique constraint violation
**Solution:** Run the UNIQUE constraint SQL above

### Error: ORA-02292 (Child Record Found)
**Cause:** Foreign key constraint blocking
**Solution:** Check if trigger is firing properly

### Error: ORA-01400 (Cannot insert NULL)
**Cause:** Required field is NULL
**Solution:** Check that all required fields are being passed

### Error: No error but data not saving
**Cause:** Silent failure in catch block
**Solution:** Check `storage/logs/laravel.log` for caught exceptions

---

## Manual Test Insert

Try this to test if Oracle can accept inserts:

```sql
-- Test manual insert (adjust addon ID as needed)
BEGIN
    INSERT INTO ROYAL_DEPLOYLOGIC.TRANSLATIONS 
    (TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY, VALUE, CREATED_AT, UPDATED_AT)
    VALUES 
    ('App\Models\AddOn', 999, 'ar', 'name', 'تست', SYSTIMESTAMP, SYSTIMESTAMP);
    
    COMMIT;
END;
/

-- Verify it worked
SELECT * FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS WHERE TRANSLATIONABLE_ID = 999;

-- Clean up test
DELETE FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS WHERE TRANSLATIONABLE_ID = 999;
COMMIT;
```

---

## Quick Check Command

Run this in your terminal to see recent errors:
```bash
tail -50 storage/logs/laravel.log | grep -i "translation\|error\|exception"
```

Or on Windows PowerShell:
```powershell
Get-Content storage\logs\laravel.log -Tail 50 | Select-String "translation|error|exception" -CaseInsensitive
```

---

## Next Steps

1. ✅ Run SQL fixes above in Oracle
2. ✅ Clear Laravel cache
3. ✅ Try updating an addon with multiple languages
4. ✅ Check `storage/logs/laravel.log` for debug output
5. ✅ Run diagnostic SQL queries
6. ✅ Report back what you find in the logs

---

## Files Modified

1. `app/Http/Controllers/Vendor/AddOnController.php` - Fixed validation
2. `app/Http/Controllers/Admin/AddOnController.php` - Fixed validation
3. `app/CentralLogics/helpers.php` - Fixed case sensitivity + added debug logging

## Need to Modify (Optional)

If trigger still causes issues, you can disable Laravel's ID management:

**File:** `app/Models/Translation.php`

Comment out the boot method temporarily to let Oracle trigger handle everything:

```php
protected static function boot()
{
    parent::boot();

    // Temporarily disabled - let Oracle trigger handle ID
    /*
    static::creating(function (self $model) {
        if (empty($model->id)) {
            $nextId = DB::table('translations')
                ->select(DB::raw('NVL(MAX(id),0) + 1 as next_id'))
                ->lockForUpdate()
                ->value('next_id');
            $model->id = $nextId;
        }
    });
    */
}
```

