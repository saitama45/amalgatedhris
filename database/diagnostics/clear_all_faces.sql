-- Clear ALL face registrations to start fresh
-- Run this when face data is mixed up or testing needs reset

UPDATE employees 
SET face_data = NULL 
WHERE employee_code IN ('EMP-2026-0004', 'EMP-2026-0006');

-- Verify all cleared
SELECT employee_code, 
       name,
       CASE WHEN face_data IS NULL THEN 'Cleared ✓' ELSE 'Still has data ✗' END as status
FROM employees 
WHERE employee_code IN ('EMP-2026-0004', 'EMP-2026-0006')
ORDER BY employee_code;
