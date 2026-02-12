-- Clear face data for EMP-2026-0004 (MARIA GONZALES)
-- Run this to remove the incorrect face registration

UPDATE employees 
SET face_data = NULL 
WHERE employee_code = 'EMP-2026-0004';

-- Verify it was cleared
SELECT employee_code, 
       CASE WHEN face_data IS NULL THEN 'Cleared' ELSE 'Still has data' END as status
FROM employees 
WHERE employee_code = 'EMP-2026-0004';
