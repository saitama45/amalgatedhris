-- Face Recognition Database Diagnostic Script
-- Run this in your MySQL/MariaDB client to check face data status

-- 1. Check all employees with face data
SELECT 
    e.id,
    e.employee_code,
    u.name,
    CASE 
        WHEN e.face_data IS NULL THEN '❌ No face data'
        WHEN JSON_VALID(e.face_data) = 0 THEN '⚠️ Invalid JSON'
        WHEN JSON_EXTRACT(e.face_data, '$.descriptor') IS NULL THEN '⚠️ No descriptor'
        WHEN JSON_LENGTH(JSON_EXTRACT(e.face_data, '$.descriptor')) != 1434 THEN CONCAT('⚠️ Wrong length: ', JSON_LENGTH(JSON_EXTRACT(e.face_data, '$.descriptor')))
        ELSE '✅ Valid'
    END as status,
    JSON_LENGTH(JSON_EXTRACT(e.face_data, '$.descriptor')) as descriptor_length,
    JSON_EXTRACT(e.face_data, '$.file') as face_image_file
FROM employees e
LEFT JOIN users u ON e.user_id = u.id
WHERE e.face_data IS NOT NULL
ORDER BY e.employee_code;

-- 2. Count by status
SELECT 
    CASE 
        WHEN face_data IS NULL THEN 'No face data'
        WHEN JSON_VALID(face_data) = 0 THEN 'Invalid JSON'
        WHEN JSON_EXTRACT(face_data, '$.descriptor') IS NULL THEN 'No descriptor'
        WHEN JSON_LENGTH(JSON_EXTRACT(face_data, '$.descriptor')) != 1434 THEN 'Wrong descriptor length'
        ELSE 'Valid and ready'
    END as status,
    COUNT(*) as count
FROM employees
GROUP BY status;

-- 3. Show sample descriptor data (first employee with valid descriptor)
SELECT 
    employee_code,
    SUBSTRING(JSON_EXTRACT(face_data, '$.descriptor'), 1, 100) as descriptor_sample,
    JSON_LENGTH(JSON_EXTRACT(face_data, '$.descriptor')) as length
FROM employees
WHERE JSON_EXTRACT(face_data, '$.descriptor') IS NOT NULL
LIMIT 1;

-- 4. Find employees that need re-registration
SELECT 
    e.employee_code,
    u.name,
    'Needs re-registration' as action
FROM employees e
LEFT JOIN users u ON e.user_id = u.id
WHERE e.face_data IS NOT NULL
  AND (
      JSON_VALID(e.face_data) = 0 
      OR JSON_EXTRACT(e.face_data, '$.descriptor') IS NULL
      OR JSON_LENGTH(JSON_EXTRACT(e.face_data, '$.descriptor')) != 1434
  );

-- 5. OPTIONAL: Clear invalid face data (BACKUP FIRST!)
-- Uncomment to run:
-- UPDATE employees 
-- SET face_data = NULL 
-- WHERE face_data IS NOT NULL 
--   AND (
--       JSON_VALID(face_data) = 0 
--       OR JSON_EXTRACT(face_data, '$.descriptor') IS NULL
--       OR JSON_LENGTH(JSON_EXTRACT(face_data, '$.descriptor')) != 1434
--   );
