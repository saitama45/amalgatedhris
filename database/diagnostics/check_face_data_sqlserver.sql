-- Face Recognition Database Diagnostic Script for SQL Server
-- Run this in SQL Server Management Studio or Azure Data Studio

-- 1. Check all employees with face data
SELECT 
    e.id,
    e.employee_code,
    u.name,
    CASE 
        WHEN e.face_data IS NULL THEN '❌ No face data'
        WHEN ISJSON(e.face_data) = 0 THEN '⚠️ Invalid JSON'
        WHEN JSON_QUERY(e.face_data, '$.descriptor') IS NULL THEN '⚠️ No descriptor'
        ELSE '✅ Has descriptor'
    END as status,
    LEN(JSON_QUERY(e.face_data, '$.descriptor')) as descriptor_json_length,
    JSON_VALUE(e.face_data, '$.file') as face_image_file
FROM employees e
LEFT JOIN users u ON e.user_id = u.id
WHERE e.face_data IS NOT NULL
ORDER BY e.employee_code;

-- 2. Count by status
SELECT 
    CASE 
        WHEN face_data IS NULL THEN 'No face data'
        WHEN ISJSON(face_data) = 0 THEN 'Invalid JSON'
        WHEN JSON_QUERY(face_data, '$.descriptor') IS NULL THEN 'No descriptor'
        ELSE 'Valid and ready'
    END as status,
    COUNT(*) as count
FROM employees
GROUP BY 
    CASE 
        WHEN face_data IS NULL THEN 'No face data'
        WHEN ISJSON(face_data) = 0 THEN 'Invalid JSON'
        WHEN JSON_QUERY(face_data, '$.descriptor') IS NULL THEN 'No descriptor'
        ELSE 'Valid and ready'
    END;

-- 3. Show sample descriptor data (first employee with valid descriptor)
SELECT TOP 1
    employee_code,
    SUBSTRING(JSON_QUERY(face_data, '$.descriptor'), 1, 100) as descriptor_sample,
    LEN(JSON_QUERY(face_data, '$.descriptor')) as descriptor_json_length
FROM employees
WHERE JSON_QUERY(face_data, '$.descriptor') IS NOT NULL;

-- 4. Find employees that need re-registration
SELECT 
    e.employee_code,
    u.name,
    'Needs re-registration' as action,
    CASE 
        WHEN ISJSON(e.face_data) = 0 THEN 'Invalid JSON'
        WHEN JSON_QUERY(e.face_data, '$.descriptor') IS NULL THEN 'No descriptor'
        ELSE 'Unknown issue'
    END as reason
FROM employees e
LEFT JOIN users u ON e.user_id = u.id
WHERE e.face_data IS NOT NULL
  AND (
      ISJSON(e.face_data) = 0 
      OR JSON_QUERY(e.face_data, '$.descriptor') IS NULL
  );

-- 5. View actual face_data content for debugging
SELECT TOP 5
    employee_code,
    face_data
FROM employees
WHERE face_data IS NOT NULL;

-- 6. OPTIONAL: Clear invalid face data (BACKUP FIRST!)
-- Uncomment to run:
/*
UPDATE employees 
SET face_data = NULL 
WHERE face_data IS NOT NULL 
  AND (
      ISJSON(face_data) = 0 
      OR JSON_QUERY(face_data, '$.descriptor') IS NULL
  );
*/
