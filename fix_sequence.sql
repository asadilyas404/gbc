-- ============================================================
-- FIX ORACLE SEQUENCE MISMATCH
-- This fixes the primary key violation error
-- ============================================================

SET SERVEROUTPUT ON;

-- Step 1: Diagnose the problem
DECLARE
    v_max_id NUMBER;
    v_seq_value NUMBER;
    v_table_count NUMBER;
BEGIN
    -- Get table stats
    SELECT NVL(MAX(ID), 0), COUNT(*)
    INTO v_max_id, v_table_count
    FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

    -- Get sequence value (need to call NEXTVAL first)
    SELECT translations_id_seq.NEXTVAL INTO v_seq_value FROM DUAL;

    DBMS_OUTPUT.PUT_LINE('=== DIAGNOSIS ===');
    DBMS_OUTPUT.PUT_LINE('Total records in table: ' || v_table_count);
    DBMS_OUTPUT.PUT_LINE('Max ID in table: ' || v_max_id);
    DBMS_OUTPUT.PUT_LINE('Sequence current value: ' || v_seq_value);
    DBMS_OUTPUT.PUT_LINE('');

    IF v_seq_value <= v_max_id THEN
        DBMS_OUTPUT.PUT_LINE('*** PROBLEM FOUND! ***');
        DBMS_OUTPUT.PUT_LINE('Sequence value (' || v_seq_value || ') is less than or equal to max ID (' || v_max_id || ')');
        DBMS_OUTPUT.PUT_LINE('This will cause primary key violations!');
        DBMS_OUTPUT.PUT_LINE('');
        DBMS_OUTPUT.PUT_LINE('Run the fix below...');
    ELSE
        DBMS_OUTPUT.PUT_LINE('Sequence looks OK - it is higher than max ID');
    END IF;
END;
/

-- Step 2: FIX THE SEQUENCE
-- Uncomment and run this to fix:

/*
DECLARE
    v_max_id NUMBER;
    v_new_start NUMBER;
BEGIN
    -- Get current max ID
    SELECT NVL(MAX(ID), 0) INTO v_max_id FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

    -- Set new start to be HIGHER than max (adding 100 for safety)
    v_new_start := v_max_id + 100;

    DBMS_OUTPUT.PUT_LINE('Dropping old sequence...');
    BEGIN
        EXECUTE IMMEDIATE 'DROP SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq';
        DBMS_OUTPUT.PUT_LINE('Old sequence dropped');
    EXCEPTION
        WHEN OTHERS THEN
            DBMS_OUTPUT.PUT_LINE('Note: ' || SQLERRM);
    END;

    DBMS_OUTPUT.PUT_LINE('Creating new sequence starting from ' || v_new_start || '...');
    EXECUTE IMMEDIATE 'CREATE SEQUENCE ROYAL_DEPLOYLOGIC.translations_id_seq START WITH ' || v_new_start || ' INCREMENT BY 1 NOCACHE NOCYCLE';

    DBMS_OUTPUT.PUT_LINE('');
    DBMS_OUTPUT.PUT_LINE('=== SUCCESS ===');
    DBMS_OUTPUT.PUT_LINE('Sequence recreated successfully!');
    DBMS_OUTPUT.PUT_LINE('Old max ID: ' || v_max_id);
    DBMS_OUTPUT.PUT_LINE('New sequence starts at: ' || v_new_start);
    DBMS_OUTPUT.PUT_LINE('');
    DBMS_OUTPUT.PUT_LINE('Now try running: php test_translation_save.php');
END;
/
*/

-- Step 3: Verify the fix
SELECT
    'Max ID in Table' as metric,
    TO_CHAR(NVL(MAX(ID), 0)) as value
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS
UNION ALL
SELECT
    'Sequence Starting Value',
    TO_CHAR(last_number)
FROM user_sequences
WHERE UPPER(sequence_name) = 'TRANSLATIONS_ID_SEQ'
UNION ALL
SELECT
    'Total Records',
    TO_CHAR(COUNT(*))
FROM ROYAL_DEPLOYLOGIC.TRANSLATIONS;

