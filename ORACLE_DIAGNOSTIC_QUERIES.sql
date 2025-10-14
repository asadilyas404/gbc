-- ============================================================
-- ORACLE TRANSLATIONS TABLE DIAGNOSTIC QUERIES
-- Run these step-by-step to diagnose translation issues
-- ============================================================

-- ============================================================
-- STEP 1: CHECK IF SEQUENCE EXISTS
-- ============================================================

-- Check if sequence exists and get its properties
SELECT
    sequence_name,
    min_value,
    max_value,
    increment_by,
    last_number,
    cache_size,
    cycle_flag
FROM user_sequences
WHERE UPPER(sequence_name) = 'TRANSLATIONS_ID_SEQ';

-- If no results, sequence doesn't exist. Create it:
/*
CREATE SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq
START WITH 1000
INCREMENT BY 1
NOCACHE
NOCYCLE;
*/


-- ============================================================
-- STEP 2: CHECK CURRENT STATE OF TRANSLATIONS TABLE
-- ============================================================

-- Get count of translations
SELECT COUNT(*) as total_translations
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

-- Get max ID currently in table
SELECT
    NVL(MAX(ID), 0) as max_id,
    NVL(MIN(ID), 0) as min_id,
    COUNT(*) as total_records
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

-- Check addon translations specifically
SELECT
    t.ID,
    t.TRANSLATIONABLE_ID as ADDON_ID,
    t.LOCALE,
    t.KEY,
    SUBSTR(DBMS_LOB.SUBSTR(t.VALUE, 100, 1), 1, 100) as VALUE_PREVIEW,
    t.CREATED_AT,
    t.UPDATED_AT
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS t
WHERE t.TRANSLATIONABLE_TYPE = 'App\Models\AddOn'
ORDER BY t.TRANSLATIONABLE_ID DESC, t.LOCALE;


-- ============================================================
-- STEP 3: CHECK FOR DUPLICATES
-- ============================================================

-- Find duplicate translations (there should be NONE)
SELECT
    TRANSLATIONABLE_TYPE,
    TRANSLATIONABLE_ID,
    LOCALE,
    KEY,
    COUNT(*) as duplicate_count
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
GROUP BY TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY
HAVING COUNT(*) > 1;


-- ============================================================
-- STEP 4: CHECK CONSTRAINTS
-- ============================================================

-- List all constraints on translations table
SELECT
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    STATUS,
    VALIDATED,
    DEFERRABLE,
    DEFERRED
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'TRANSLATIONS'
ORDER BY CONSTRAINT_TYPE;

-- Check if UNIQUE constraint exists
SELECT
    CONSTRAINT_NAME,
    CONSTRAINT_TYPE,
    STATUS
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'TRANSLATIONS'
  AND CONSTRAINT_TYPE = 'U';  -- U = Unique constraint

-- Get details of constraint columns
SELECT
    c.CONSTRAINT_NAME,
    c.COLUMN_NAME,
    c.POSITION
FROM USER_CONS_COLUMNS c
JOIN USER_CONSTRAINTS uc ON c.CONSTRAINT_NAME = uc.CONSTRAINT_NAME
WHERE uc.TABLE_NAME = 'TRANSLATIONS'
ORDER BY c.CONSTRAINT_NAME, c.POSITION;


-- ============================================================
-- STEP 5: CHECK TRIGGER
-- ============================================================

-- Check if trigger exists and is enabled
SELECT
    trigger_name,
    trigger_type,
    triggering_event,
    table_name,
    status,
    action_type
FROM user_triggers
WHERE UPPER(table_name) = 'TRANSLATIONS';

-- Get trigger source code
SELECT
    trigger_name,
    trigger_body
FROM user_triggers
WHERE UPPER(table_name) = 'TRANSLATIONS';


-- ============================================================
-- STEP 6: CHECK INDEXES
-- ============================================================

-- List all indexes on translations table
SELECT
    index_name,
    index_type,
    uniqueness,
    status
FROM user_indexes
WHERE table_name = 'TRANSLATIONS';

-- Get index columns
SELECT
    i.index_name,
    ic.column_name,
    ic.column_position
FROM user_indexes i
JOIN user_ind_columns ic ON i.index_name = ic.index_name
WHERE i.table_name = 'TRANSLATIONS'
ORDER BY i.index_name, ic.column_position;


-- ============================================================
-- STEP 7: TEST SEQUENCE (SAFE - WILL INCREMENT)
-- ============================================================

-- Get NEXT value from sequence (this will increment it)
-- Only run if you want to test the sequence
/*
SELECT translations_id_seq.NEXTVAL as next_id FROM DUAL;
*/

-- After running NEXTVAL, you can check CURRVAL
/*
SELECT translations_id_seq.CURRVAL as current_id FROM DUAL;
*/


-- ============================================================
-- STEP 8: FIX - CREATE UNIQUE CONSTRAINT (IF MISSING)
-- ============================================================

-- ** CRITICAL FIX ** - Run this if unique constraint doesn't exist
/*
ALTER TABLE ROYAL_DEPLOYLOGIC.TRANSLATIONS
ADD CONSTRAINT TRANSLATIONS_UNIQUE_TRANS
UNIQUE (TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY);
*/


-- ============================================================
-- STEP 9: FIX - SYNC SEQUENCE WITH EXISTING DATA
-- ============================================================

-- Run this if sequence is lower than max ID in table
/*
DECLARE
    v_max_id NUMBER;
    v_new_start NUMBER;
BEGIN
    -- Get current max ID
    SELECT NVL(MAX(ID), 0) INTO v_max_id FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

    -- Set new start to be higher than max
    v_new_start := v_max_id + 1;

    -- Drop and recreate sequence
    BEGIN
        EXECUTE IMMEDIATE 'DROP SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq';
    EXCEPTION
        WHEN OTHERS THEN NULL; -- Ignore if doesn't exist
    END;

    EXECUTE IMMEDIATE 'CREATE SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq START WITH ' || v_new_start || ' INCREMENT BY 1 NOCACHE NOCYCLE';

    DBMS_OUTPUT.PUT_LINE('Sequence recreated starting from: ' || v_new_start);
END;
/
*/


-- ============================================================
-- STEP 10: FIX - UPDATE TRIGGER TO ALWAYS USE SEQUENCE
-- ============================================================

/*
CREATE OR REPLACE TRIGGER ROYAL_DEPLOYLOGIC.TRANSLATIONS_ID_TRG
BEFORE INSERT ON ROYAL_DEPLOYLOGIC.TRANSLATIONS
FOR EACH ROW
BEGIN
    -- Always use sequence, even if ID is provided by Laravel
    SELECT ROYAL_DEPLOYLOGIC.translations_id_seq.NEXTVAL INTO :new.ID FROM dual;
END;
/
*/


-- ============================================================
-- STEP 11: TEST INSERT (MANUAL TEST)
-- ============================================================

-- Test if you can insert a translation manually
/*
BEGIN
    INSERT INTO ROYAL_DEPLOYLOGIC.TRANSLATIONS
    (TRANSLATIONABLE_TYPE, TRANSLATIONABLE_ID, LOCALE, KEY, VALUE, CREATED_AT, UPDATED_AT)
    VALUES
    ('App\Models\AddOn', 99999, 'test', 'name', 'Test Value', SYSTIMESTAMP, SYSTIMESTAMP);

    DBMS_OUTPUT.PUT_LINE('Test insert successful');
    ROLLBACK; -- Don't actually save the test data
END;
/
*/


-- ============================================================
-- STEP 12: CLEANUP TEST DATA (IF NEEDED)
-- ============================================================

-- Remove test translations if you created any
/*
DELETE FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
WHERE TRANSLATIONABLE_ID = 99999 OR LOCALE = 'test';
COMMIT;
*/


-- ============================================================
-- STEP 13: CHECK RECENT TRANSLATIONS
-- ============================================================

-- Get most recently created/updated translations
SELECT
    t.ID,
    t.TRANSLATIONABLE_TYPE,
    t.TRANSLATIONABLE_ID,
    t.LOCALE,
    t.KEY,
    SUBSTR(DBMS_LOB.SUBSTR(t.VALUE, 50, 1), 1, 50) as VALUE_PREVIEW,
    t.CREATED_AT,
    t.UPDATED_AT
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS t
ORDER BY t.UPDATED_AT DESC NULLS LAST
FETCH FIRST 20 ROWS ONLY;


-- ============================================================
-- STEP 14: CHECK FOR SPECIFIC ADDON
-- ============================================================

-- Replace 123 with your actual addon ID
/*
SELECT
    t.ID,
    t.LOCALE,
    t.KEY,
    DBMS_LOB.SUBSTR(t.VALUE, 200, 1) as VALUE,
    t.CREATED_AT,
    t.UPDATED_AT
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS t
WHERE t.TRANSLATIONABLE_TYPE = 'App\Models\AddOn'
  AND t.TRANSLATIONABLE_ID = 123
ORDER BY t.LOCALE;
*/


-- ============================================================
-- QUICK DIAGNOSIS SUMMARY
-- ============================================================

-- Run this to get a quick overview
SELECT
    'Translations Count' as metric,
    TO_CHAR(COUNT(*)) as value
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
UNION ALL
SELECT
    'Addon Translations',
    TO_CHAR(COUNT(*))
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
WHERE TRANSLATIONABLE_TYPE = 'App\Models\AddOn'
UNION ALL
SELECT
    'Max ID in Table',
    TO_CHAR(NVL(MAX(ID), 0))
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
UNION ALL
SELECT
    'Unique Constraint Exists',
    CASE WHEN COUNT(*) > 0 THEN 'YES' ELSE 'NO - ADD IT!' END
FROM USER_CONSTRAINTS
WHERE TABLE_NAME = 'TRANSLATIONS' AND CONSTRAINT_TYPE = 'U'
UNION ALL
SELECT
    'Trigger Exists',
    CASE WHEN COUNT(*) > 0 THEN 'YES' ELSE 'NO' END
FROM USER_TRIGGERS
WHERE TABLE_NAME = 'TRANSLATIONS'
UNION ALL
SELECT
    'Sequence Exists',
    CASE WHEN COUNT(*) > 0 THEN 'YES' ELSE 'NO - CREATE IT!' END
FROM USER_SEQUENCES
WHERE UPPER(SEQUENCE_NAME) = 'TRANSLATIONS_ID_SEQ';

