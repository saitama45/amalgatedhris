# Face Recognition Debugging Guide

## Issue Summary
Face biometrics registered in Index.vue should match and recognize faces in Kiosk.vue, but currently faces are not being detected or recognized.

## How the System Works

### 1. Registration (Index.vue)
- Captures face using MediaPipe Face Landmarker
- Generates 3D descriptor: 478 landmarks × 3 coordinates (x, y, z) = **1434 values**
- Normalizes by:
  - Centering on nose bridge (landmark 168)
  - Scaling by inter-ocular distance (between landmarks 133 and 362)
- Saves to database as JSON: `{"file": "face_123.jpg", "descriptor": [1434 numbers]}`

### 2. Recognition (Kiosk.vue)
- Loads all employees with face descriptors
- Continuously analyzes video feed
- Generates same 3D descriptor for live face
- Compares using Euclidean distance
- Threshold: **0.80 similarity** required for match
- Requires **6 consecutive matches** for stability

## Debugging Steps

### Step 1: Check Browser Console (Index.vue - Registration)
1. Open Employee Directory
2. Edit an employee
3. Go to "Face Biometrics" tab
4. Open browser console (F12)
5. Click "Capture & Register"
6. Look for log: `Face descriptor generated: { length: 1434, ... }`
7. **Expected**: length should be exactly 1434
8. **If not**: MediaPipe is not detecting face properly

### Step 2: Check Database

**For MySQL/MariaDB:**
```sql
SELECT 
    employee_code, 
    JSON_EXTRACT(face_data, '$.descriptor') IS NOT NULL as has_descriptor,
    JSON_LENGTH(JSON_EXTRACT(face_data, '$.descriptor')) as descriptor_length
FROM employees 
WHERE face_data IS NOT NULL;
```

**For SQL Server:**
```sql
SELECT 
    employee_code,
    CASE WHEN JSON_VALUE(face_data, '$.descriptor') IS NOT NULL THEN 1 ELSE 0 END as has_descriptor,
    LEN(JSON_QUERY(face_data, '$.descriptor')) as descriptor_length_chars,
    face_data
FROM employees 
WHERE face_data IS NOT NULL;
```

**Expected Results:**
- `has_descriptor` = 1
- `descriptor_length` = 1434

**If descriptor_length is NULL or 0:**
- The descriptor wasn't saved during registration
- Re-register the face

### Step 3: Check Laravel Logs (Kiosk Loading)
1. Open `storage/logs/laravel.log`
2. Look for: `Kiosk loaded with X employees with valid face descriptors`
3. Look for: `Employee XXX-XXXX descriptor check`

**Expected:**
- At least 1 employee should have valid descriptor
- descriptor_count should be 1434

**If 0 employees loaded:**
- No employees have valid face descriptors
- Re-register faces

### Step 4: Check Browser Console (Kiosk.vue - Recognition)
1. Open Attendance Kiosk page
2. Open browser console (F12)
3. Look for: `Kiosk mounted. Employees with descriptors: X`
4. Look for: `Employee XXX-XXXX: { hasDescriptor: true, descriptorLength: 1434 }`

**If descriptorLength is 0 or undefined:**
- Backend is not sending descriptors properly
- Check AttendanceKioskController

### Step 5: Test Face Recognition
1. Stand in front of camera
2. Position face in the blue circle
3. Watch console for: `Comparing against X registered employees`
4. Watch for: `Match score for XXX-XXXX: 0.XXXX`

**Expected Scores:**
- **Your own face**: 0.85 - 0.95 (should match)
- **Different person**: 0.30 - 0.70 (should not match)
- **Threshold**: 0.80

**If all scores are below 0.80:**
- Lighting conditions may be different
- Camera angle may be different
- Try re-registering in similar lighting

## Common Issues & Solutions

### Issue 1: "Face not recognized" but descriptor exists
**Cause**: Different lighting/angle between registration and recognition
**Solution**: 
- Re-register face in the same lighting as kiosk
- Ensure face is centered in circle during both registration and recognition
- Use same camera if possible

### Issue 2: No employees loaded in Kiosk
**Cause**: Descriptors not saved or invalid
**Solution**:
```sql
-- Check which employees have face_data
SELECT employee_code, 
       CASE 
           WHEN face_data IS NULL THEN 'No face data'
           WHEN JSON_VALID(face_data) = 0 THEN 'Invalid JSON'
           WHEN JSON_EXTRACT(face_data, '$.descriptor') IS NULL THEN 'No descriptor'
           ELSE 'Valid'
       END as status
FROM employees;
```

### Issue 3: Descriptor length is wrong
**Cause**: MediaPipe version mismatch or incomplete capture
**Solution**:
- Verify MediaPipe version: `@mediapipe/tasks-vision@0.10.32`
- Ensure face is fully visible during capture
- Wait for "✓ 3D Sensors Ready" before capturing

### Issue 4: Old registrations don't work
**Cause**: Database has old format without descriptor
**Solution**: Re-register all faces using the updated system

## Testing Checklist

- [ ] MediaPipe loads successfully (check console)
- [ ] Face descriptor has 1434 values during registration
- [ ] Database stores descriptor in JSON format
- [ ] Kiosk loads at least 1 employee with descriptor
- [ ] Console shows similarity scores during recognition
- [ ] Similarity score > 0.80 for registered face
- [ ] 6 consecutive matches trigger attendance log

## Technical Details

### Descriptor Format
```javascript
// Registration (Index.vue)
editForm.face_descriptor = [
    // 478 landmarks × 3 coordinates
    x1/eyeDist, y1/eyeDist, z1/eyeDist,  // Landmark 0
    x2/eyeDist, y2/eyeDist, z2/eyeDist,  // Landmark 1
    // ... 476 more landmarks
]

// Database (employees.face_data)
{
    "file": "face_123_1234567890.jpg",
    "descriptor": [1434 normalized values]
}

// Kiosk (props.employees)
[
    {
        "employee_code": "XXX-XXXX",
        "name": "John Doe",
        "descriptor": [1434 normalized values]
    }
]
```

### Similarity Calculation
```javascript
// Euclidean distance in 1434-dimensional space
distance = sqrt(sum((sig1[i] - sig2[i])^2))
similarity = max(0, 1 - (distance / 0.5))

// Threshold: 0.80
// Same person: typically 0.85-0.95
// Different person: typically 0.30-0.70
```

## Quick Fix Commands

### Re-register all faces:
1. Go to Employee Directory
2. For each employee with "FACE ID" badge:
   - Click Edit
   - Go to Biometrics tab
   - Click "Clear Biometrics"
   - Click "Start Camera"
   - Position face in circle
   - Wait for "✓ 3D Sensors Ready"
   - Click "Capture & Register"
   - Click "Save Changes"

### Clear invalid face data:
```sql
-- Backup first!
UPDATE employees 
SET face_data = NULL 
WHERE face_data IS NOT NULL 
  AND (
      JSON_VALID(face_data) = 0 
      OR JSON_EXTRACT(face_data, '$.descriptor') IS NULL
  );
```

## Support

If issues persist after following this guide:
1. Check browser console for errors
2. Check Laravel logs for errors
3. Verify MediaPipe model file exists at `/public/models/face_landmarker.task`
4. Ensure HTTPS is enabled (required for camera access)
5. Test with different browsers (Chrome recommended)
