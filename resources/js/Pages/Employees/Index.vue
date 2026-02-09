<script setup>
import { Head, useForm, router } from '@inertiajs/vue3';
import { ref, onMounted, watch, computed } from 'vue';
import AppLayout from '@/Layouts/AppLayout.vue';
import DataTable from '@/Components/DataTable.vue';
import Modal from '@/Components/Modal.vue';
import { usePagination } from '@/Composables/usePagination';
import { useToast } from '@/Composables/useToast';
import { usePermission } from '@/Composables/usePermission';
import { useConfirm } from '@/Composables/useConfirm';
import { Human } from '@vladmandic/human';
import {
    UserIcon,
    IdentificationIcon,
    BuildingOffice2Icon,
    PencilSquareIcon,
    DocumentDuplicateIcon,
    UserMinusIcon,
    XMarkIcon,
    CheckCircleIcon,
    ArrowUpTrayIcon,
    EyeIcon,
    MagnifyingGlassPlusIcon,
    MagnifyingGlassMinusIcon,
    ArrowPathIcon,
    BanknotesIcon,
    TrashIcon,
    PlusIcon,
    CameraIcon,
    FunnelIcon
} from '@heroicons/vue/24/outline';

const props = defineProps({
    employees: Object,
    filters: Object, // Expecting filters from backend
    options: Object, // Contains document_types, companies, departments, positions
});

const { showSuccess, showError } = useToast();
const { hasPermission } = usePermission();
const { confirm } = useConfirm();

// New: Filters
const filterForm = ref({
    company_id: props.filters.company_id || '',
    department_id: props.filters.department_id || '',
    position_id: props.filters.position_id || '',
});

const pagination = usePagination(props.employees, 'employees.index', () => ({
    ...filterForm.value
}));

// New: Apply Filters method
const applyFilters = () => {
    const params = {
        ...filterForm.value,
        search: pagination.search.value,
        per_page: pagination.perPage.value
    };
    pagination.performSearch(route('employees.index'), params);
};
// Initialize Human library with high-accuracy settings
const humanConfig = {
    debug: false,
    userConsole: false, // Strictest level of console silencing
    modelBasePath: 'https://cdn.jsdelivr.net/npm/@vladmandic/human/models/',
    filter: { enabled: true, equalization: true, flip: false },
    face: {
        enabled: true,
        detector: { return: true, rotation: true, mask: false, minConfidence: 0.4 },
        mesh: { enabled: true },
        iris: { enabled: true },
        description: { enabled: true },
        emotion: { enabled: false },
        antispoof: { enabled: true },
        liveness: { enabled: true },
    },
    body: { enabled: false },
    hand: { enabled: false },
    object: { enabled: false },
    segmentation: { enabled: false },
};

const human = new Human(humanConfig);
const modelsLoaded = ref(false);

onMounted(async () => {
    pagination.updateData(props.employees);
    try {
        await human.load();
        await human.warmup();
        modelsLoaded.value = true;
    } catch (e) {
        console.error("Critical: Face models failed to load", e);
    }
});

watch(() => props.employees, (newEmployees) => {
    pagination.updateData(newEmployees);
    
    // Update selected employee reference for modal if open
    if (salaryEmployee.value) {
        const updated = newEmployees.data.find(e => e.id === salaryEmployee.value.id);
        if (updated) {
            salaryEmployee.value = updated;
        }
    }
}, { deep: true });

// --- Salary History State & Logic ---
const showSalaryModal = ref(false);
const salaryEmployee = ref(null);
const salaryHistory = ref([]);
const isLoadingSalary = ref(false);
const isEditingSalary = ref(false);
const editingSalaryItem = ref(null);

const canShowSalaryForm = computed(() => {
    if (isEditingSalary.value) {
        return hasPermission('employees.edit_salary');
    }
    return hasPermission('employees.create_salary');
});

const salaryForm = useForm({
    basic_rate: '',
    allowance: 0,
    position_id: '',
    company_id: '',
    effective_date: new Date().toISOString().split('T')[0],
});

const openSalaryModal = (employee) => {
    salaryEmployee.value = employee;
    resetSalaryForm();
    
    // Set current position and company as default
    if (employee.active_employment_record) {
        salaryForm.position_id = employee.active_employment_record.position_id;
        salaryForm.company_id = employee.active_employment_record.company_id;
    }
    
    showSalaryModal.value = true;
    fetchSalaryHistory(employee.id);
};

const resetSalaryForm = () => {
    isEditingSalary.value = false;
    editingSalaryItem.value = null;
    salaryForm.reset();
    salaryForm.clearErrors();
};

const editSalaryItem = (item) => {
    isEditingSalary.value = true;
    editingSalaryItem.value = item;
    
    salaryForm.basic_rate = item.basic_rate;
    salaryForm.allowance = item.allowance;
    // For editing existing history, we typically don't change the position linked to that record directly here easily without complex logic, 
    // so we might disable position edit or just show it. 
    // But user asked to edit salary rate specifically.
    // The position is tied to the employment_record, not just the salary_history row directly (it's a relationship).
    // So for this "Edit Rate" feature, we will focus on rate and date.
    
    // We'll set the position_id to the one in the record for display/consistency, 
    // but we might disable changing it if it implies a different employment record logic.
    // Let's allow simple rate updates.
    salaryForm.position_id = item.employment_record?.position_id; 
    salaryForm.company_id = item.employment_record?.company_id;
    
    // Backend now returns YYYY-MM-DD, so we can use it directly
    salaryForm.effective_date = item.effective_date;
};

const fetchSalaryHistory = (employeeId) => {
    isLoadingSalary.value = true;
    axios.get(route('employees.salary.index', employeeId))
        .then(response => {
            // New structure: { history: [], current_record: {} }
            const historyData = response.data.history;
            salaryHistory.value = historyData;

            // Update the local salaryEmployee's active record to reflect any backend repairs/changes
            if (response.data.current_record && salaryEmployee.value) {
                salaryEmployee.value.active_employment_record = response.data.current_record;
            }

            // Set current rates in form as default for new record if NOT editing
            if (!isEditingSalary.value && historyData.length > 0) {
                salaryForm.basic_rate = historyData[0].basic_rate;
                salaryForm.allowance = historyData[0].allowance;
            }
        })
        .finally(() => isLoadingSalary.value = false);
};

const submitSalary = () => {
    if (isEditingSalary.value && editingSalaryItem.value) {
        // Update existing record
        salaryForm.put(route('salary-history.update', editingSalaryItem.value.id), {
            onSuccess: () => {
                resetSalaryForm();
                fetchSalaryHistory(salaryEmployee.value.id);
            },
            onError: (errors) => {
                // If it's a flash error (business logic like payroll check), it might not be in 'errors' object 
                // depending on how useErrorHandler handles it. 
                // But standard Inertia errors bag works.
                // However, we returned ->with('error', msg). We need to check page props for that or standard error bag.
                // Usually controller validation errors land here. Business logic errors via 'with' land in page.props.flash.
                // Let's assume standard error toast helper handles flash.
                showError('Failed to update salary record.');
            }
        });
    } else {
        // Add new record
        salaryForm.post(route('employees.salary.store', salaryEmployee.value.id), {
            onSuccess: () => {
                fetchSalaryHistory(salaryEmployee.value.id);
                // Keep form populated with new latest for convenience, or reset?
                // Resetting to "Add Mode" is safer.
                resetSalaryForm();
                // But re-populate position
                if (salaryEmployee.value?.active_employment_record) {
                    salaryForm.position_id = salaryEmployee.value.active_employment_record.position_id;
                }
            },
            onError: () => showError('Failed to add salary rate.')
        });
    }
};

const deleteSalaryItem = async (item) => {
    const confirmed = await confirm({
        title: 'Delete Salary Record',
        message: 'Are you sure you want to delete this salary record? This action cannot be undone.',
        confirmButtonText: 'Delete Record'
    });

    if (!confirmed) return;

    router.delete(route('salary-history.destroy', item.id), {
        onSuccess: () => {
            fetchSalaryHistory(salaryEmployee.value.id);
            if (editingSalaryItem.value?.id === item.id) {
                resetSalaryForm();
            }
        },
        onError: () => showError('Failed to delete salary record.')
    });
};

// --- File Preview State & Logic ---
const showPreviewModal = ref(false);
const previewDoc = ref(null);
const previewUrl = ref('');
const previewType = ref('');

// Zoom & Pan State
const scale = ref(1);
const translateX = ref(0);
const translateY = ref(0);
const isDragging = ref(false);
const startX = ref(0);
const startY = ref(0);

const openPreview = (doc) => {
    if (!doc) return;
    previewDoc.value = doc;
    previewUrl.value = `/${doc.file_path}`;
    
    // Reset Zoom/Pan
    scale.value = 1;
    translateX.value = 0;
    translateY.value = 0;

    const extension = doc.file_path.split('.').pop().toLowerCase();
    if (['jpg', 'jpeg', 'png', 'gif', 'webp'].includes(extension)) {
        previewType.value = 'image';
    } else if (extension === 'pdf') {
        previewType.value = 'pdf';
    } else {
        previewType.value = 'other'; 
    }
    
    showPreviewModal.value = true;
};

const closePreview = () => {
    showPreviewModal.value = false;
    previewDoc.value = null;
    previewUrl.value = '';
    previewType.value = '';
};

// Image Controls
const zoomIn = () => {
    scale.value += 0.15;
};

const zoomOut = () => {
    if (scale.value > 0.2) scale.value -= 0.15;
};

const resetZoom = () => {
    scale.value = 1;
    translateX.value = 0;
    translateY.value = 0;
};

const onWheel = (e) => {
    if (previewType.value !== 'image') return;
    e.preventDefault();
    if (e.deltaY < 0) zoomIn();
    else zoomOut();
};

const startDrag = (e) => {
    if (previewType.value !== 'image' || scale.value <= 1) return; // Only drag if zoomed in or just generally allow
    isDragging.value = true;
    startX.value = e.clientX - translateX.value;
    startY.value = e.clientY - translateY.value;
};

const onDrag = (e) => {
    if (!isDragging.value) return;
    e.preventDefault();
    translateX.value = e.clientX - startX.value;
    translateY.value = e.clientY - startY.value;
};

const stopDrag = () => {
    isDragging.value = false;
};

// --- Edit Profile State & Logic ---
const showEditModal = ref(false);
const editingEmployee = ref(null);
const editForm = useForm({
    employee_code: '',
    civil_status: '',
    gender: '',
    birthday: '',
    address: '',
    emergency_contact: '',
    emergency_contact_relationship: '',
    emergency_contact_number: '',
    sss_no: '',
    philhealth_no: '',
    pagibig_no: '',
    tin_no: '',
    department_id: '',
    employment_status: '',
    is_sss_deducted: true,
    is_philhealth_deducted: true,
    is_pagibig_deducted: true,
    is_withholding_tax_deducted: true,
    face_data: null, // For Biometrics
    face_descriptor: null, // New field for descriptor
});

// Biometrics State
const activeTab = ref('profile'); // 'profile', 'biometrics'
const bioVideoRef = ref(null);
const bioCanvasRef = ref(null);
const bioStream = ref(null);
const isBioCameraActive = ref(false);
const bioImage = ref(null);
const isBioProcessing = ref(false); // New state for processing
const bioFeedback = ref('Ready to capture');

const startBioCamera = async () => {
    try {
        // Request higher resolution for better registration quality
        bioStream.value = await navigator.mediaDevices.getUserMedia({ video: { width: 1280, height: 720 } });
        if (bioVideoRef.value) {
            bioVideoRef.value.srcObject = bioStream.value;
            isBioCameraActive.value = true;
            bioFeedback.value = "Position face in circle";
        }
    } catch (err) {
        console.error("Camera error:", err);
        showError("Could not access camera.");
    }
};

const stopBioCamera = () => {
    if (bioStream.value) {
        bioStream.value.getTracks().forEach(track => track.stop());
        isBioCameraActive.value = false;
        bioFeedback.value = "";
    }
};

const captureBio = async () => {
    if (!bioVideoRef.value || !bioCanvasRef.value || !modelsLoaded.value) return;
    
    isBioProcessing.value = true;
    
    try {
        // 1. Perform detection directly from video stream
        const result = await human.detect(bioVideoRef.value);
        
        if (!result.face || result.face.length === 0) {
            showError("No face detected. Ensure good lighting and face the camera directly.");
            return;
        }

        // Get the primary face (closest/largest)
        const face = result.face[0];
        
        // 2. Strict Centering & Quality Constraint
        // Human returns normalized coordinates (0-1)
        const box = face.boxRaw; // [x, y, width, height]
        const faceCenterX = box[0] + (box[2] / 2);
        const faceCenterY = box[1] + (box[3] / 2);
        
        // Distance from center of frame (0.5, 0.5)
        const distFromCenter = Math.sqrt(Math.pow(faceCenterX - 0.5, 2) + Math.pow(faceCenterY - 0.5, 2));
        
        if (distFromCenter > 0.15) { // Threshold for being "centered"
            showError("Face is not centered. Please align with the circle.");
            return;
        }

        if (box[2] < 0.25) { // Face must take up at least 25% of the frame width
            showError("Face too far away. Please move closer.");
            return;
        }

        // 3. Circular Crop Logic for Preview/Display
        const videoWidth = bioVideoRef.value.videoWidth;
        const videoHeight = bioVideoRef.value.videoHeight;
        const size = 400; 
        
        const cropCanvas = document.createElement('canvas');
        cropCanvas.width = size;
        cropCanvas.height = size;
        const cropCtx = cropCanvas.getContext('2d');

        cropCtx.beginPath();
        cropCtx.arc(size/2, size/2, size/2, 0, Math.PI * 2);
        cropCtx.clip();

        // Draw centered square from video to the circle
        const sSize = Math.min(videoWidth, videoHeight);
        const sx = (videoWidth - sSize) / 2;
        const sy = (videoHeight - sSize) / 2;

        cropCtx.drawImage(bioVideoRef.value, sx, sy, sSize, sSize, 0, 0, size, size);

        // 4. Store Data
        bioImage.value = cropCanvas.toDataURL('image/jpeg', 0.9); 
        editForm.face_data = bioImage.value;
        // Human's face.embedding is the descriptor
        editForm.face_descriptor = Array.from(face.embedding || []);
        
        if (editForm.face_descriptor.length === 0) {
            throw new Error("Failed to extract facial signature. Please try again.");
        }

        showSuccess("Face registered successfully with Human AI!");
        bioFeedback.value = "Registration Complete";
        stopBioCamera();
    } catch (err) {
        console.error(err);
        showError("Registration failed: " + err.message);
    } finally {
        isBioProcessing.value = false;
    }
};

const retakeBio = () => {
    bioImage.value = null;
    editForm.face_data = null;
    editForm.face_descriptor = null;
    startBioCamera();
};

const formatTIN = (value) => {
    if (!value) return '';
    let clean = value.replace(/\D/g, '');
    if (clean.length > 12) clean = clean.slice(0, 12);
    
    let formatted = '';
    for (let i = 0; i < clean.length; i++) {
        if (i > 0 && i % 3 === 0) {
            formatted += ' ';
        }
        formatted += clean[i];
    }
    return formatted;
};

const handleTINInput = (e) => {
    const formatted = formatTIN(e.target.value);
    editForm.tin_no = formatted;
    if (e.target.value !== formatted) {
        e.target.value = formatted;
    }
};

const formatSSS = (value) => {
    if (!value) return '';
    let clean = value.replace(/\D/g, '');
    if (clean.length > 10) clean = clean.slice(0, 10);
    let formatted = '';
    if (clean.length > 0) {
        formatted = clean.substring(0, 2);
        if (clean.length > 2) {
            formatted += ' ' + clean.substring(2, 9);
            if (clean.length > 9) {
                formatted += ' ' + clean.substring(9, 10);
            }
        }
    }
    return formatted;
};

const formatPhilHealth = (value) => {
    if (!value) return '';
    let clean = value.replace(/\D/g, '');
    if (clean.length > 12) clean = clean.slice(0, 12);
    let formatted = '';
    if (clean.length > 0) {
        formatted = clean.substring(0, 2);
        if (clean.length > 2) {
            formatted += ' ' + clean.substring(2, 11);
            if (clean.length > 11) {
                formatted += ' ' + clean.substring(11, 12);
            }
        }
    }
    return formatted;
};

const formatPagIBIG = (value) => {
    if (!value) return '';
    let clean = value.replace(/\D/g, '');
    if (clean.length > 12) clean = clean.slice(0, 12);
    let formatted = '';
    if (clean.length > 0) {
        formatted = clean.substring(0, 4);
        if (clean.length > 4) {
            formatted += ' ' + clean.substring(4, 8);
            if (clean.length > 8) {
                formatted += ' ' + clean.substring(8, 12);
            }
        }
    }
    return formatted;
};

const formatPhone = (value) => {
    if (!value) return '';
    let clean = value.replace(/\D/g, '');
    if (clean.length > 11) clean = clean.slice(0, 11);
    let formatted = '';
    if (clean.length > 0) {
        formatted = clean.substring(0, 4);
        if (clean.length > 4) {
            formatted += ' ' + clean.substring(4, 7);
            if (clean.length > 7) {
                formatted += ' ' + clean.substring(7, 11);
            }
        }
    }
    return formatted;
};

const handleSSSInput = (e) => {
    const formatted = formatSSS(e.target.value);
    editForm.sss_no = formatted;
    if (e.target.value !== formatted) e.target.value = formatted;
};

const handlePhilHealthInput = (e) => {
    const formatted = formatPhilHealth(e.target.value);
    editForm.philhealth_no = formatted;
    if (e.target.value !== formatted) e.target.value = formatted;
};

const handlePagIBIGInput = (e) => {
    const formatted = formatPagIBIG(e.target.value);
    editForm.pagibig_no = formatted;
    if (e.target.value !== formatted) e.target.value = formatted;
};

const handleEmergencyPhoneInput = (e) => {
    const formatted = formatPhone(e.target.value);
    editForm.emergency_contact_number = formatted;
    if (e.target.value !== formatted) e.target.value = formatted;
};

const onlyNumbers = (e) => {
    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End'];
    if (allowedKeys.includes(e.key)) return;
    if (!/^[0-9]$/.test(e.key)) {
        e.preventDefault();
    }
};

const onlyLetters = (e) => {
    const allowedKeys = ['Backspace', 'Delete', 'ArrowLeft', 'ArrowRight', 'Tab', 'Home', 'End', ' ', '.'];
    if (allowedKeys.includes(e.key)) return;
    
    // Block if NOT a letter (a-z, A-Z)
    if (!/^[a-zA-Z]$/.test(e.key)) {
        e.preventDefault();
    }
};

const handleAlphaInput = (field, e) => {
    // Strip anything that isn't a letter, space, or period
    editForm[field] = e.target.value.replace(/[^a-zA-Z\s.]/g, '');
};

const handleAddressInput = (e) => {
    // Remove emojis using unicode property escape
    const val = e.target.value.replace(/\p{Extended_Pictographic}/gu, '');
    editForm.address = val;
    if (e.target.value !== val) {
        e.target.value = val;
    }
};

const openEditModal = (employee) => {
    editingEmployee.value = employee;
    activeTab.value = 'profile'; // Reset tab
    editForm.employee_code = employee.employee_code || '';
    editForm.civil_status = employee.civil_status || '';
    editForm.gender = employee.gender || '';
    editForm.birthday = employee.birthday ? employee.birthday.split('T')[0] : '';
    editForm.address = employee.address || '';
    editForm.emergency_contact = employee.emergency_contact || '';
    editForm.emergency_contact_relationship = employee.emergency_contact_relationship || '';
    editForm.emergency_contact_number = formatPhone(employee.emergency_contact_number || '');
    editForm.sss_no = formatSSS(employee.sss_no || '');
    editForm.philhealth_no = formatPhilHealth(employee.philhealth_no || '');
    editForm.pagibig_no = formatPagIBIG(employee.pagibig_no || '');
    editForm.tin_no = formatTIN(employee.tin_no || '');
    editForm.department_id = employee.active_employment_record?.department_id || '';
    editForm.employment_status = employee.active_employment_record?.employment_status || '';
    editForm.is_sss_deducted = employee.active_employment_record?.is_sss_deducted ?? true;
    editForm.is_philhealth_deducted = employee.active_employment_record?.is_philhealth_deducted ?? true;
    editForm.is_pagibig_deducted = employee.active_employment_record?.is_pagibig_deducted ?? true;
    editForm.is_withholding_tax_deducted = employee.active_employment_record?.is_withholding_tax_deducted ?? true;
        
        // Handle JSON face_data
        let faceFilename = null;
        if (employee.face_data) {
            try {
                // Try parsing JSON
                const parsed = JSON.parse(employee.face_data);
                if (parsed && parsed.file) {
                    faceFilename = parsed.file;
                } else if (typeof parsed === 'string') {
                    // Fallback if it was just a string (legacy/migration)
                     faceFilename = parsed;
                }
            } catch (e) {
                // Not JSON, assume string
                faceFilename = employee.face_data;
            }
        }
        
        bioImage.value = faceFilename ? `/uploads/faces/${faceFilename}?t=${new Date().getTime()}` : null;
        
        editForm.face_data = null; // Reset staged change
        editForm.face_descriptor = null;
        
        editForm.clearErrors();    showEditModal.value = true;
};

const submitEdit = () => {
    editForm.put(route('employees.update', editingEmployee.value.id), {
        onSuccess: () => {
            closeEditModal();
        },
        onError: (errors) => {
            const firstError = Object.values(errors)[0];
            showError(firstError || 'Failed to update profile. Please check inputs.');
        }
    });
};

const closeEditModal = () => {
    stopBioCamera();
    showEditModal.value = false;
};


// --- Document Checklist Logic ---
const showDocsModal = ref(false);
const docsEmployeeId = ref(null);
const fileInputs = ref({}); // Store references to file inputs
const stagedFiles = ref({}); // Store selected but not uploaded files: { docTypeId: File }
const uploadingDocId = ref(null); // Track which doc is currently uploading
const localDocuments = ref([]); // Local copy of documents for the modal
const isLoadingDocs = ref(false); // Loading state for fetching documents

const activeDocsEmployee = computed(() => {
    if (!docsEmployeeId.value || !props.employees.data) return null;
    return props.employees.data.find(e => e.id === docsEmployeeId.value);
});

const fetchDocuments = (employeeId) => {
    isLoadingDocs.value = true;
    axios.get(route('employees.documents.list', employeeId))
        .then(response => {
            localDocuments.value = response.data;
        })
        .catch(error => {
            console.error('Failed to fetch documents', error);
            showError('Could not load current documents.');
        })
        .finally(() => {
            isLoadingDocs.value = false;
        });
};

const openDocsModal = (employee) => {
    docsEmployeeId.value = employee.id;
    stagedFiles.value = {}; 
    uploadingDocId.value = null;
    localDocuments.value = []; // Clear previous
    showDocsModal.value = true;
    
    // Fetch fresh data independently of props
    fetchDocuments(employee.id);
};

const triggerFileInput = (docTypeId) => {
    const input = fileInputs.value[docTypeId];
    if (input) input.click();
};

const handleFileSelect = (event, docTypeId) => {
    const file = event.target.files[0];
    if (!file) return;

    // Validate file extension
    const allowedExtensions = ['docx', 'pdf', 'jpg', 'jpeg', 'png'];
    const extension = file.name.split('.').pop().toLowerCase();
    if (!allowedExtensions.includes(extension)) {
        showError('Invalid file type. Only DOCX, PDF, JPG, and PNG are allowed.');
        event.target.value = '';
        return;
    }

    // 50MB Validation (50 * 1024 * 1024 bytes)
    const maxSize = 50 * 1024 * 1024;
    if (file.size > maxSize) {
        showError('File is too large. Maximum allowed size is 50MB.');
        event.target.value = ''; // Reset input
        return;
    }
    
    // Stage the file
    stagedFiles.value[docTypeId] = file;
    
    // Reset input value so selecting the same file triggers change again if needed
    event.target.value = ''; 
};

const cancelStage = (docTypeId) => {
    delete stagedFiles.value[docTypeId];
};

const saveDocument = (docTypeId) => {
    const file = stagedFiles.value[docTypeId];
    if (!file) return;

    uploadingDocId.value = docTypeId;

    const formData = new FormData();
    formData.append('document_type_id', docTypeId);
    formData.append('file', file);

    router.post(route('employees.upload-document', docsEmployeeId.value), formData, {
        onSuccess: () => {
            delete stagedFiles.value[docTypeId]; 
            // Fetch fresh documents immediately
            fetchDocuments(docsEmployeeId.value);
        },
        onError: (errors) => {
             const errorMessage = Object.values(errors).flat().join(', ') || 'Failed to upload document.';
             showError(errorMessage);
        },
        onFinish: () => {
            uploadingDocId.value = null;
        },
        forceFormData: true,
        preserveScroll: true,
        preserveState: true, // Keep modal state intact
    });
};

const getEmployeeDoc = (docTypeId) => {
    if (!localDocuments.value) return null;
    // Ensure strict type comparison safety, although usually IDs are numbers
    return localDocuments.value.find(d => d.document_type_id == docTypeId);
};

// --- Resignation State & Logic ---
const showResignModal = ref(false);
const resigningEmployee = ref(null);
const resignForm = useForm({
    end_date: '',
    reason: '',
});

const openResignModal = (employee) => {
    resigningEmployee.value = employee;
    resignForm.reset();
    resignForm.clearErrors();
    showResignModal.value = true;
};

const submitResign = async () => {
    const confirmed = await confirm({
        title: 'Confirm Resignation',
        message: 'Are you sure you want to mark this employee as Resigned? This action usually cannot be undone easily.',
        confirmButtonText: 'Confirm Resignation'
    });
    
    if (!confirmed) return;
    
    resignForm.put(route('employees.resign', resigningEmployee.value.id), {
        onSuccess: () => {
            showResignModal.value = false;
        },
        onError: () => showError('Failed to update status.')
    });
};
</script>

<template>
    <Head title="Employee Directory - HRIS" />
    <AppLayout>
        <template #header>
            <div class="flex flex-col md:flex-row md:items-center md:justify-between">
                <div>
                    <h2 class="font-bold text-2xl text-slate-800 leading-tight">Employee Directory</h2>
                    <p class="text-sm text-slate-500 mt-1">Official list of active employees and their designations.</p>
                </div>
            </div>
        </template>
        
        <div class="py-6">
            <div class="max-w-7xl mx-auto sm:px-6 lg:px-8">

                <!-- Filters -->
                <div class="bg-white rounded-xl shadow-sm border border-slate-100 p-4 mb-6">
                    <div class="grid grid-cols-1 md:grid-cols-2 xl:grid-cols-4 gap-4 items-end">
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Company</label>
                            <select v-model="filterForm.company_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">All Companies</option>
                                <option v-for="comp in options.companies" :key="comp.id" :value="comp.id">{{ comp.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Department</label>
                            <select v-model="filterForm.department_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">All Departments</option>
                                <option v-for="dept in options.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <label class="block text-xs font-bold text-slate-500 uppercase mb-1">Position</label>
                            <select v-model="filterForm.position_id" class="w-full rounded-lg border-slate-200 text-sm">
                                <option value="">All Positions</option>
                                <option v-for="pos in options.positions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
                            </select>
                        </div>
                        <div class="w-full">
                            <button @click="applyFilters" class="w-full bg-slate-800 hover:bg-slate-700 text-white font-bold py-2 px-4 rounded-lg transition-colors flex items-center justify-center gap-2">
                                <FunnelIcon class="w-4 h-4" /> Filter Employees
                            </button>
                        </div>
                    </div>
                </div>

                <div class="bg-white rounded-2xl shadow-sm border border-slate-100 overflow-hidden">
                    <DataTable
                        title="Workforce 201"
                        subtitle="Complete employee roster"
                        search-placeholder="Search name, code, or email..."
                        empty-message="No employees found."
                        :search="pagination.search.value"
                        :data="pagination.data.value"
                        :current-page="pagination.currentPage.value"
                        :last-page="pagination.lastPage.value"
                        :per-page="pagination.perPage.value"
                        :showing-text="pagination.showingText.value"
                        :is-loading="pagination.isLoading.value"
                        @update:search="pagination.search.value = $event"
                        @go-to-page="pagination.goToPage"
                        @change-per-page="pagination.changePerPage"
                    >
                        <template #header>
                            <tr class="bg-slate-50">
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Employee</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">ID & Contact</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Position</th>
                                <th class="px-6 py-4 text-left text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Department</th>
                                <th class="px-6 py-4 text-right text-xs font-bold text-slate-500 uppercase tracking-widest border-b border-slate-100">Actions</th>
                            </tr>
                        </template>
                        
                        <template #body="{ data }">
                            <tr v-for="employee in data" :key="employee.id" class="hover:bg-slate-50/50 transition-colors border-b border-slate-50 last:border-0">
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex items-center">
                                        <div class="h-10 w-10 bg-slate-100 rounded-full flex items-center justify-center text-slate-500 border border-slate-200">
                                            <UserIcon class="w-5 h-5" />
                                        </div>
                                        <div class="ml-4">
                                            <div class="flex items-center">
                                                <div class="text-sm font-bold text-slate-900">{{ employee.user?.name || 'N/A' }}</div>
                                                <div v-if="employee.face_data" class="ml-2 px-1.5 py-0.5 bg-emerald-100 text-[10px] text-emerald-700 font-bold rounded flex items-center" title="Face Biometrics Registered">
                                                    <CheckCircleIcon class="w-3 h-3 mr-1" />
                                                    FACE ID
                                                </div>
                                            </div>
                                            <div class="text-xs text-slate-500">Joined: {{ new Date(employee.created_at).toLocaleDateString() }}</div>
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col space-y-1">
                                        <div class="flex items-center text-sm text-slate-600 font-mono">
                                            <IdentificationIcon class="w-4 h-4 mr-2 text-slate-400" />
                                            {{ employee.employee_code }}
                                        </div>
                                        <div class="text-xs text-slate-500 ml-6">{{ employee.user?.email }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <span class="text-sm font-medium text-slate-700">
                                        {{ employee.active_employment_record?.position?.name || 'No Position' }}
                                    </span>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap">
                                    <div class="flex flex-col">
                                        <div class="flex items-center text-sm text-slate-600">
                                            <BuildingOffice2Icon class="w-4 h-4 mr-2 text-slate-400" />
                                            {{ employee.active_employment_record?.department?.name || 'Unassigned' }}
                                        </div>
                                        <div class="text-xs text-slate-500 ml-6">{{ employee.active_employment_record?.company?.name || '-' }}</div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium">
                                    <div class="flex justify-end space-x-1">
                                        <button 
                                            v-if="hasPermission('employees.edit')"
                                            @click="openEditModal(employee)"
                                            class="p-2 text-slate-400 hover:text-blue-600 hover:bg-blue-50 rounded-lg transition-all"
                                            title="Edit Profile"
                                        >
                                            <PencilSquareIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="hasPermission('employees.edit_documents')"
                                            @click="openDocsModal(employee)"
                                            class="p-2 text-slate-400 hover:text-amber-600 hover:bg-amber-50 rounded-lg transition-all"
                                            title="Manage Documents"
                                        >
                                            <DocumentDuplicateIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="hasPermission('employees.view_salary')"
                                            @click="openSalaryModal(employee)"
                                            class="p-2 text-slate-400 hover:text-emerald-600 hover:bg-emerald-50 rounded-lg transition-all"
                                            title="Salary History"
                                        >
                                            <BanknotesIcon class="w-5 h-5" />
                                        </button>
                                        <button 
                                            v-if="employee.active_employment_record?.is_active && hasPermission('employees.edit')"
                                            @click="openResignModal(employee)"
                                            class="p-2 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded-lg transition-all"
                                            title="Mark as Resigned"
                                        >
                                            <UserMinusIcon class="w-5 h-5" />
                                        </button>
                                    </div>
                                </td>
                            </tr>
                        </template>
                    </DataTable>
                </div>
            </div>
        </div>

        <!-- Edit Profile Modal -->
        <Modal :show="showEditModal" @close="closeEditModal" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Edit Employee Profile</h3>
                    <p class="text-sm text-slate-500">Update personal information for {{ editingEmployee?.user?.name }}</p>
                </div>
                <button @click="closeEditModal" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>

            <!-- Tabs -->
            <div class="px-6 pt-4 border-b border-slate-100 flex space-x-6">
                <button 
                    @click="activeTab = 'profile'" 
                    class="pb-2 text-sm font-bold border-b-2 transition-all"
                    :class="activeTab === 'profile' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                >
                    Profile Information
                </button>
                <button 
                    @click="activeTab = 'biometrics'" 
                    class="pb-2 text-sm font-bold border-b-2 transition-all"
                    :class="activeTab === 'biometrics' ? 'border-blue-600 text-blue-600' : 'border-transparent text-slate-500 hover:text-slate-700'"
                >
                    Face Biometrics
                </button>
            </div>
            
            <form @submit.prevent="submitEdit" class="p-6">
                <div v-show="activeTab === 'profile'">
                    <div class="mb-6">
                        <label class="block text-sm font-bold text-slate-700 mb-1">Employee ID / Code</label>
                        <input v-model="editForm.employee_code" type="text" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono">
                    </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Civil Status</label>
                        <select v-model="editForm.civil_status" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            <option value="" disabled>Select Status</option>
                            <option value="Single">Single</option>
                            <option value="Married">Married</option>
                            <option value="Widowed">Widowed</option>
                            <option value="Separated">Separated</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Gender</label>
                        <select v-model="editForm.gender" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                            <option value="" disabled>Select Gender</option>
                            <option value="Male">Male</option>
                            <option value="Female">Female</option>
                            <option value="Other">Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Department</label>
                        <select v-model="editForm.department_id" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold">
                            <option value="" disabled>Select Department</option>
                            <option v-for="dept in options.departments" :key="dept.id" :value="dept.id">{{ dept.name }}</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Employment Status</label>
                        <select v-model="editForm.employment_status" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-bold text-blue-600">
                            <option value="" disabled>Select Status</option>
                            <option value="Probationary">Probationary</option>
                            <option value="Regular">Regular</option>
                            <option value="Consultant">Consultant</option>
                            <option value="Project-Based">Project-Based</option>
                            <option value="Casual">Casual</option>
                        </select>
                    </div>
                </div>

                <div class="grid grid-cols-1 md:grid-cols-2 gap-6 mb-6">
                    <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Birthday</label>
                        <input v-model="editForm.birthday" type="date" class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all">
                    </div>
                     <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">TIN</label>
                        <input 
                            :value="editForm.tin_no" 
                            @input="handleTINInput"
                            @keydown="onlyNumbers"
                            type="text" 
                            placeholder="XXX XXX XXX XXX"
                            maxlength="15"
                            inputmode="numeric"
                            class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                        >
                    </div>
                </div>

                 <div class="mb-6">
                    <label class="block text-sm font-bold text-slate-700 mb-1">Complete Address</label>
                    <textarea 
                        :value="editForm.address" 
                        @input="handleAddressInput" 
                        rows="2" 
                        class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                    ></textarea>
                </div>

                <div class="border-t border-slate-100 pt-6 mb-6">
                    <h4 class="font-bold text-slate-800 mb-4">Emergency Contact</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                        <div class="md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Contact Person</label>
                            <input 
                                v-model="editForm.emergency_contact" 
                                @keydown="onlyLetters"
                                @input="handleAlphaInput('emergency_contact', $event)"
                                type="text" 
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                            >
                        </div>
                         <div class="md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Relationship</label>
                            <input 
                                v-model="editForm.emergency_contact_relationship" 
                                @keydown="onlyLetters"
                                @input="handleAlphaInput('emergency_contact_relationship', $event)"
                                type="text" 
                                placeholder="e.g. Spouse, Parent" 
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all"
                            >
                        </div>
                        <div class="md:col-span-1">
                            <label class="block text-sm font-bold text-slate-700 mb-1">Contact Number</label>
                            <input 
                                :value="editForm.emergency_contact_number" 
                                @input="handleEmergencyPhoneInput"
                                @keydown="onlyNumbers"
                                type="text" 
                                placeholder="09XX XXX XXXX"
                                maxlength="13"
                                inputmode="numeric"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                    </div>
                </div>

                <div class="border-t border-slate-100 pt-6 mt-6">
                    <h4 class="font-bold text-slate-800 mb-4">Government Contribution Flags</h4>
                    <div class="grid grid-cols-2 md:grid-cols-4 gap-4">
                        <label class="flex items-center p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-all" :class="{'border-blue-500 bg-blue-50': editForm.is_sss_deducted}">
                            <input type="checkbox" v-model="editForm.is_sss_deducted" class="w-4 h-4 text-blue-600 rounded border-slate-300 focus:ring-blue-500">
                            <span class="ml-3 text-sm font-bold text-slate-700">SSS</span>
                        </label>
                        <label class="flex items-center p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-all" :class="{'border-emerald-500 bg-emerald-50': editForm.is_philhealth_deducted}">
                            <input type="checkbox" v-model="editForm.is_philhealth_deducted" class="w-4 h-4 text-emerald-600 rounded border-slate-300 focus:ring-emerald-500">
                            <span class="ml-3 text-sm font-bold text-slate-700">PhilHealth</span>
                        </label>
                        <label class="flex items-center p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-all" :class="{'border-indigo-500 bg-indigo-50': editForm.is_pagibig_deducted}">
                            <input type="checkbox" v-model="editForm.is_pagibig_deducted" class="w-4 h-4 text-indigo-600 rounded border-slate-300 focus:ring-indigo-500">
                            <span class="ml-3 text-sm font-bold text-slate-700">Pag-IBIG</span>
                        </label>
                        <label class="flex items-center p-3 border border-slate-200 rounded-xl cursor-pointer hover:bg-slate-50 transition-all" :class="{'border-orange-500 bg-orange-50': editForm.is_withholding_tax_deducted}">
                            <input type="checkbox" v-model="editForm.is_withholding_tax_deducted" class="w-4 h-4 text-orange-600 rounded border-slate-300 focus:ring-orange-500">
                            <span class="ml-3 text-sm font-bold text-slate-700">Tax</span>
                        </label>
                    </div>
                    <p class="text-[10px] text-slate-500 mt-3 italic">Toggle whether this employee is subject to these mandatory government deductions. Payout cycles are managed at the company level.</p>
                </div>

                <div class="border-t border-slate-100 pt-6 mt-6">
                    <h4 class="font-bold text-slate-800 mb-4">Government Numbers</h4>
                    <div class="grid grid-cols-1 md:grid-cols-3 gap-6">
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">SSS No.</label>
                            <input 
                                :value="editForm.sss_no" 
                                @input="handleSSSInput"
                                @keydown="onlyNumbers"
                                type="text" 
                                placeholder="XX XXXXXXX X"
                                maxlength="12"
                                inputmode="numeric"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">PhilHealth No.</label>
                            <input 
                                :value="editForm.philhealth_no" 
                                @input="handlePhilHealthInput"
                                @keydown="onlyNumbers"
                                type="text" 
                                placeholder="XX XXXXXXXXX X"
                                maxlength="14"
                                inputmode="numeric"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                         <div>
                            <label class="block text-sm font-bold text-slate-700 mb-1">Pag-IBIG No.</label>
                            <input 
                                :value="editForm.pagibig_no" 
                                @input="handlePagIBIGInput"
                                @keydown="onlyNumbers"
                                type="text" 
                                placeholder="XXXX XXXX XXXX"
                                maxlength="14"
                                inputmode="numeric"
                                class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-blue-500/20 focus:border-blue-500 transition-all font-mono"
                            >
                        </div>
                    </div>
                </div>

                </div>

                <!-- Biometrics Section -->
                <div v-show="activeTab === 'biometrics'" class="space-y-6">
                    <div class="bg-blue-50 border border-blue-100 rounded-xl p-4 flex gap-3">
                        <div class="p-2 bg-blue-100 rounded-full h-fit text-blue-600">
                            <IdentificationIcon class="w-6 h-6" />
                        </div>
                        <div>
                            <h4 class="font-bold text-blue-900 text-sm">Face Registration</h4>
                            <p class="text-xs text-blue-700 mt-1">Capture a clear photo of the employee for Kiosk face recognition.</p>
                        </div>
                    </div>

                    <div class="relative bg-black rounded-2xl overflow-hidden aspect-video flex items-center justify-center group">
                        <video ref="bioVideoRef" autoplay playsinline muted class="absolute inset-0 w-full h-full object-cover" :class="{'opacity-50': bioImage}"></video>
                        
                        <!-- Circle Guide Overlay -->
                        <div v-if="isBioCameraActive && !bioImage" class="absolute inset-0 flex items-center justify-center pointer-events-none z-10">
                            <div class="w-64 h-64 border-4 border-blue-500/50 rounded-full shadow-[0_0_0_100vw_rgba(0,0,0,0.6)]"></div>
                            <div class="absolute bottom-10 bg-black/60 backdrop-blur-md px-4 py-1.5 rounded-full border border-white/10 text-white text-[10px] font-bold uppercase tracking-widest">
                                {{ bioFeedback }}
                            </div>
                        </div>

                        <img v-if="bioImage" :src="bioImage" class="absolute inset-0 w-full h-full object-cover z-20">
                        <canvas ref="bioCanvasRef" class="hidden"></canvas>

                        <!-- Controls -->
                        <div class="absolute bottom-4 z-30 flex gap-4">
                            <button v-if="!bioImage && !isBioCameraActive" type="button" @click="startBioCamera" class="bg-white text-slate-900 px-4 py-2 rounded-full font-bold text-xs shadow-lg hover:bg-slate-100 transition-all flex items-center">
                                <CameraIcon class="w-4 h-4 mr-2" /> Start Camera
                            </button>
                            <button v-if="isBioCameraActive" :disabled="isBioProcessing" type="button" @click="captureBio" class="bg-white text-slate-900 px-4 py-2 rounded-full font-bold text-xs shadow-lg hover:bg-slate-100 transition-all flex items-center disabled:opacity-50">
                                <div v-if="isBioProcessing" class="animate-spin rounded-full h-3 w-3 border-b-2 border-slate-900 mr-2"></div>
                                <div v-else class="w-3 h-3 bg-red-500 rounded-full animate-pulse mr-2"></div> 
                                {{ isBioProcessing ? 'Analyzing...' : 'Capture & Register' }}
                            </button>
                            <button v-if="bioImage" type="button" @click="retakeBio" class="bg-white/20 backdrop-blur text-white px-4 py-2 rounded-full font-bold text-xs shadow-lg hover:bg-white/30 transition-all flex items-center border border-white/30">
                                <ArrowPathIcon class="w-4 h-4 mr-2" /> Retake
                            </button>
                            <button v-if="bioImage && !isBioCameraActive" type="button" @click="() => { bioImage = null; editForm.face_data = 'CLEAR'; editForm.face_descriptor = null; }" class="bg-red-500/20 backdrop-blur text-red-200 px-4 py-2 rounded-full font-bold text-xs shadow-lg hover:bg-red-500/30 transition-all flex items-center border border-red-500/30">
                                <TrashIcon class="w-4 h-4 mr-2" /> Clear Biometrics
                            </button>
                        </div>
                        
                        <div v-if="!isBioCameraActive && !bioImage" class="absolute inset-0 flex flex-col items-center justify-center text-slate-500">
                            <CameraIcon class="w-12 h-12 mb-2 opacity-50" />
                            <span class="text-xs font-bold uppercase tracking-wider">Camera Off</span>
                        </div>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 border-t border-slate-100 mt-6">
                    <button type="button" @click="closeEditModal" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="editForm.processing" class="px-6 py-2.5 bg-blue-600 text-white font-bold rounded-xl hover:bg-blue-700 shadow-lg shadow-blue-600/20 disabled:opacity-50 transition-all">
                        Save Changes
                    </button>
                </div>
            </form>
        </Modal>

        <!-- Documents Modal -->
        <Modal :show="showDocsModal" @close="showDocsModal = false" maxWidth="3xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                 <div>
                    <h3 class="text-lg font-bold text-slate-900">Requirements Checklist</h3>
                    <p class="text-sm text-slate-500">Manage 201 file documents for {{ activeDocsEmployee?.user?.name }}</p>
                </div>
                <button @click="showDocsModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <!-- Loading State -->
                <div v-if="isLoadingDocs" class="flex justify-center items-center py-12">
                     <svg class="animate-spin h-8 w-8 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>

                <div v-else class="overflow-hidden bg-white border border-slate-200 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-200">
                        <thead class="bg-slate-50">
                            <tr>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider w-10">Status</th>
                                <th scope="col" class="px-6 py-3 text-left text-xs font-bold text-slate-500 uppercase tracking-wider">Requirement</th>
                                <th scope="col" class="px-6 py-3 text-right text-xs font-bold text-slate-500 uppercase tracking-wider">Actions</th>
                            </tr>
                        </thead>
                        <tbody class="divide-y divide-slate-200 bg-white">
                            <tr v-for="docType in options?.document_types" :key="docType.id" class="hover:bg-slate-50 transition-colors group">
                                <td class="px-6 py-4 whitespace-nowrap align-top w-12">
                                    <div v-if="getEmployeeDoc(docType.id)" class="text-emerald-500 bg-emerald-50 rounded-full p-1 w-fit">
                                        <CheckCircleIcon class="w-5 h-5" />
                                    </div>
                                    <div v-else class="w-7 h-7 border-2 border-slate-200 rounded-full mx-auto bg-slate-50"></div>
                                </td>
                                <td class="px-6 py-4 whitespace-normal align-top">
                                    <div class="flex flex-col">
                                        <div class="flex items-center mb-1">
                                            <span class="text-sm font-bold text-slate-800">{{ docType.name }}</span>
                                            <span v-if="docType.is_required" class="ml-2 px-2 py-0.5 text-[10px] font-bold bg-rose-50 text-rose-600 rounded-full border border-rose-100 uppercase tracking-wide">Required</span>
                                        </div>
                                        
                                        <!-- Staged File Info -->
                                        <div v-if="stagedFiles[docType.id]" class="text-xs text-blue-600 font-semibold bg-blue-50 px-2 py-1.5 rounded-md border border-blue-100 flex items-center w-fit animate-in fade-in zoom-in duration-200">
                                            <DocumentDuplicateIcon class="w-4 h-4 mr-1.5" />
                                            <span class="truncate max-w-[200px]">{{ stagedFiles[docType.id].name }}</span>
                                        </div>

                                        <!-- Uploaded File Info -->
                                        <div v-else-if="getEmployeeDoc(docType.id)" class="flex items-center space-x-2 text-xs text-slate-500">
                                            <span class="bg-slate-100 px-2 py-0.5 rounded text-slate-600 font-mono">
                                                {{ getEmployeeDoc(docType.id).file_path.split('.').pop().toUpperCase() }}
                                            </span>
                                            <span>• Uploaded {{ new Date(getEmployeeDoc(docType.id).created_at).toLocaleDateString() }}</span>
                                        </div>
                                        
                                        <div v-else class="text-xs text-slate-400 italic">
                                            Not uploaded yet.
                                        </div>
                                    </div>
                                </td>
                                <td class="px-6 py-4 whitespace-nowrap text-right text-sm font-medium align-middle">
                                    <!-- Saving State -->
                                    <div v-if="uploadingDocId === docType.id" class="flex items-center justify-end text-blue-600 bg-blue-50 px-3 py-1.5 rounded-lg border border-blue-100">
                                        <svg class="animate-spin -ml-1 mr-2 h-4 w-4 text-blue-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                                            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                                            <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                                        </svg>
                                        <span class="text-xs font-bold">Uploading...</span>
                                    </div>

                                    <!-- Action Buttons: Save / Cancel -->
                                    <div v-else-if="stagedFiles[docType.id]" class="flex items-center justify-end space-x-2">
                                        <button @click="cancelStage(docType.id)" class="text-slate-500 hover:text-slate-700 text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-slate-100 transition-colors">
                                            Cancel
                                        </button>
                                        <button @click="saveDocument(docType.id)" class="bg-blue-600 text-white text-xs font-bold px-3 py-1.5 rounded-lg hover:bg-blue-700 transition-all shadow-sm shadow-blue-500/30 flex items-center">
                                            <ArrowUpTrayIcon class="w-4 h-4 mr-1" />
                                            Save File
                                        </button>
                                    </div>

                                    <!-- Action Buttons: Preview / Replace -->
                                    <div v-else-if="getEmployeeDoc(docType.id)" class="flex items-center justify-end space-x-3">
                                        <button 
                                            @click="triggerFileInput(docType.id)" 
                                            class="text-slate-400 hover:text-blue-600 text-xs font-semibold underline decoration-slate-300 hover:decoration-blue-400 underline-offset-2 transition-all"
                                        >
                                            Replace
                                        </button>
                                        <button 
                                            @click="openPreview(getEmployeeDoc(docType.id))" 
                                            class="flex items-center text-slate-700 hover:text-blue-600 font-bold text-xs bg-white border border-slate-200 px-3 py-1.5 rounded-lg hover:bg-blue-50 hover:border-blue-200 transition-all shadow-sm group-hover:shadow-md"
                                        >
                                            <EyeIcon class="w-4 h-4 mr-1.5" />
                                            Preview
                                        </button>
                                    </div>

                                    <!-- Action Button: Select File -->
                                    <div v-else>
                                        <button @click="triggerFileInput(docType.id)" class="flex items-center ml-auto text-slate-600 hover:text-blue-600 font-bold text-xs bg-slate-50 px-3 py-1.5 rounded-lg hover:bg-blue-50 hover:text-blue-700 transition-all border border-slate-200 hover:border-blue-200 shadow-sm">
                                            <ArrowUpTrayIcon class="w-4 h-4 mr-1.5" />
                                            Select File
                                        </button>
                                    </div>

                                    <!-- Hidden File Input -->
                                    <input 
                                        type="file" 
                                        :ref="el => { if (el) fileInputs[docType.id] = el }" 
                                        class="hidden" 
                                        accept=".docx,.pdf,.jpg,.jpeg,.png"
                                        @change="handleFileSelect($event, docType.id)"
                                    >
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- Resignation Modal -->
         <Modal :show="showResignModal" @close="showResignModal = false" maxWidth="md">
            <div class="px-6 py-4 border-b border-red-100 bg-red-50 flex justify-between items-center">
                 <div>
                    <h3 class="text-lg font-bold text-red-800">Process Resignation</h3>
                </div>
                <button @click="showResignModal = false" class="text-red-400 hover:text-red-600 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <form @submit.prevent="submitResign" class="p-6">
                <p class="text-sm text-slate-600 mb-6">You are marking <strong>{{ resigningEmployee?.user?.name }}</strong> as resigned. This will deactivate their current employment record.</p>
                
                <div class="space-y-4">
                     <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Last Day of Work (Effectivity)</label>
                        <input v-model="resignForm.end_date" type="date" required class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all">
                    </div>
                     <div>
                        <label class="block text-sm font-bold text-slate-700 mb-1">Reason for Leaving</label>
                        <textarea v-model="resignForm.reason" rows="3" required placeholder="State reason..." class="block w-full px-4 py-2.5 bg-slate-50 border border-slate-200 rounded-xl focus:ring-2 focus:ring-red-500/20 focus:border-red-500 transition-all"></textarea>
                    </div>
                </div>

                <div class="flex justify-end space-x-3 pt-6 mt-6">
                    <button type="button" @click="showResignModal = false" class="px-4 py-2 text-slate-600 font-bold bg-slate-100 rounded-lg hover:bg-slate-200 transition-colors">Cancel</button>
                    <button type="submit" :disabled="resignForm.processing" class="px-4 py-2 bg-red-600 text-white font-bold rounded-lg hover:bg-red-700 shadow-lg shadow-red-600/20 disabled:opacity-50 transition-all">
                        Confirm Resignation
                    </button>
                </div>
            </form>
         </Modal>

        <!-- Salary History Modal -->
        <Modal :show="showSalaryModal" @close="showSalaryModal = false" maxWidth="2xl">
            <div class="px-6 py-4 border-b border-slate-100 bg-slate-50 flex justify-between items-center">
                <div>
                    <h3 class="text-lg font-bold text-slate-900">Salary & Compensation History</h3>
                    <p class="text-sm text-slate-500">Manage rates for {{ salaryEmployee?.user?.name }}</p>
                </div>
                <button @click="showSalaryModal = false" class="text-slate-400 hover:text-slate-500 transition-colors">
                    <XMarkIcon class="w-6 h-6" />
                </button>
            </div>
            
            <div class="p-6">
                <!-- Current Status Summary -->
                <div class="mb-6 p-4 bg-slate-50 border border-slate-200 rounded-2xl flex flex-col md:flex-row md:items-center justify-between gap-4">
                    <div>
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Current Assignment</p>
                        <div class="text-sm font-bold text-slate-900">{{ salaryEmployee?.active_employment_record?.position?.name || 'No Position' }}</div>
                        <div class="text-xs text-slate-500">{{ salaryEmployee?.active_employment_record?.department?.name }}</div>
                    </div>
                    <div class="text-right">
                        <p class="text-[10px] font-bold text-slate-400 uppercase tracking-widest mb-1">Current Total Rate</p>
                        <div class="text-lg font-mono font-bold text-emerald-600">
                            {{ salaryHistory.length > 0 ? (parseFloat(salaryHistory[0].basic_rate) + parseFloat(salaryHistory[0].allowance)).toLocaleString(undefined, {minimumFractionDigits: 2}) : '0.00' }}
                        </div>
                    </div>
                </div>

                <!-- Add New Entry Form -->
                <form v-if="canShowSalaryForm" @submit.prevent="submitSalary" class="mb-8 bg-white p-6 rounded-2xl border-2 border-emerald-100 shadow-sm transition-all" :class="{'ring-2 ring-amber-200 border-amber-300': isEditingSalary}">
                    <h4 class="font-bold mb-4 text-sm flex items-center justify-between" :class="isEditingSalary ? 'text-amber-800' : 'text-slate-900'">
                        <div class="flex items-center">
                            <div class="p-1.5 rounded-lg mr-3" :class="isEditingSalary ? 'bg-amber-100 text-amber-600' : 'bg-emerald-100 text-emerald-600'">
                                <component :is="isEditingSalary ? PencilSquareIcon : PlusIcon" class="w-4 h-4" />
                            </div>
                            {{ isEditingSalary ? 'Edit Salary Record' : 'Add New Rate / Job Movement' }}
                        </div>
                        <button v-if="isEditingSalary" @click.prevent="resetSalaryForm" class="text-xs text-slate-400 hover:text-slate-600 underline">Cancel Edit</button>
                    </h4>
                    <div class="grid grid-cols-1 md:grid-cols-2 gap-4 mb-4">
                        <div class="md:col-span-2">
                            <div class="grid grid-cols-2 gap-4">
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Company (Entity)</label>
                                    <select v-model="salaryForm.company_id" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 bg-slate-50 font-bold">
                                        <option value="" disabled>Select Company</option>
                                        <option v-for="co in options?.companies" :key="co.id" :value="co.id">{{ co.name }}</option>
                                    </select>
                                </div>
                                <div>
                                    <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">New Position (Assignment)</label>
                                    <select v-model="salaryForm.position_id" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500 bg-slate-50 font-bold">
                                        <option value="" disabled>Select Position</option>
                                        <option v-for="pos in options?.positions" :key="pos.id" :value="pos.id">{{ pos.name }}</option>
                                    </select>
                                </div>
                            </div>
                            <p v-if="isEditingSalary" class="text-[10px] text-amber-600 mt-1">Changing Position or Company here updates the linked historical record.</p>
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Basic Monthly Rate</label>
                            <input v-model="salaryForm.basic_rate" type="number" step="0.01" min="0.01" @keypress="(e) => { if(e.key === '-') e.preventDefault(); }" required placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500">
                        </div>
                        <div>
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Allowance</label>
                            <input v-model="salaryForm.allowance" type="number" step="0.01" min="0" @keypress="(e) => { if(e.key === '-') e.preventDefault(); }" placeholder="0.00" class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500">
                        </div>
                        <div class="md:col-span-2">
                            <label class="block text-[10px] font-bold text-slate-500 uppercase mb-1">Effectivity Date</label>
                            <input v-model="salaryForm.effective_date" type="date" required class="w-full rounded-xl border-slate-200 text-sm focus:ring-emerald-500">
                        </div>
                    </div>
                    <div class="flex justify-end space-x-3">
                        <button v-if="isEditingSalary" type="button" @click="resetSalaryForm" class="px-6 py-2.5 text-slate-600 font-bold bg-slate-100 rounded-xl hover:bg-slate-200 transition-colors">
                            Cancel
                        </button>
                        <button type="submit" :disabled="salaryForm.processing" class="text-white text-xs font-bold px-8 py-2.5 rounded-xl transition-all shadow-lg" :class="isEditingSalary ? 'bg-amber-500 hover:bg-amber-600 shadow-amber-500/20' : 'bg-emerald-600 hover:bg-emerald-700 shadow-emerald-600/20'">
                            {{ isEditingSalary ? 'Update Record' : 'Add to History' }}
                        </button>
                    </div>
                </form>

                <!-- History List -->
                <div v-if="isLoadingSalary" class="flex justify-center py-8">
                    <svg class="animate-spin h-8 w-8 text-emerald-600" xmlns="http://www.w3.org/2000/svg" fill="none" viewBox="0 0 24 24">
                        <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle>
                        <path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4zm2 5.291A7.962 7.962 0 014 12H0c0 3.042 1.135 5.824 3 7.938l3-2.647z"></path>
                    </svg>
                </div>
                <div v-else class="overflow-hidden border border-slate-100 rounded-xl shadow-sm">
                    <table class="min-w-full divide-y divide-slate-100">
                        <thead class="bg-slate-50">
                            <tr>
                                <th class="px-4 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Effectivity</th>
                                <th class="px-4 py-2 text-left text-[10px] font-bold text-slate-500 uppercase">Company / Position</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Basic Rate</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Allowance</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase">Total</th>
                                <th class="px-4 py-2 text-right text-[10px] font-bold text-slate-500 uppercase w-10"></th>
                            </tr>
                        </thead>
                        <tbody class="bg-white divide-y divide-slate-50">
                            <tr v-for="item in salaryHistory" :key="item.id" class="hover:bg-slate-50 group">
                                <td class="px-4 py-3 text-sm font-bold text-slate-700">
                                    {{ item.effective_date.substring(0, 10) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-slate-600">
                                    <div class="font-bold text-slate-800">{{ item.employment_record?.company?.name || 'Unknown Company' }}</div>
                                    <div class="text-xs">{{ item.employment_record?.position?.name || 'Unknown Position' }}</div>
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-mono">
                                    {{ parseFloat(item.basic_rate).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-mono text-slate-500">
                                    {{ parseFloat(item.allowance).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </td>
                                <td class="px-4 py-3 text-sm text-right font-bold text-emerald-600 font-mono">
                                    {{ (parseFloat(item.basic_rate) + parseFloat(item.allowance)).toLocaleString(undefined, {minimumFractionDigits: 2}) }}
                                </td>
                                <td class="px-4 py-3 text-right">
                                    <button 
                                        v-if="hasPermission('employees.edit_salary')"
                                        @click="editSalaryItem(item)" 
                                        class="text-blue-600 hover:text-blue-800 p-1.5 rounded-lg bg-blue-50 hover:bg-blue-100 transition-all border border-blue-200"
                                        title="Edit Record"
                                    >
                                        <PencilSquareIcon class="w-4 h-4" />
                                    </button>
                                    <button 
                                        v-if="hasPermission('employees.delete_salary')"
                                        @click="deleteSalaryItem(item)" 
                                        class="ml-1 text-red-600 hover:text-red-800 p-1.5 rounded-lg bg-red-50 hover:bg-red-100 transition-all border border-red-200"
                                        title="Delete Record"
                                    >
                                        <TrashIcon class="w-4 h-4" />
                                    </button>
                                </td>
                            </tr>
                            <tr v-if="salaryHistory.length === 0">
                                <td colspan="6" class="px-4 py-8 text-center text-slate-400 text-sm italic">No salary history recorded.</td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </Modal>

        <!-- Preview Modal -->
        <Modal :show="showPreviewModal" @close="closePreview" maxWidth="full" zIndexClass="z-[60]">
            <div class="relative bg-slate-900 rounded-lg overflow-hidden h-[90vh] flex flex-col">
                <!-- Header -->
                <div class="flex items-center justify-between px-4 py-3 bg-slate-800 border-b border-slate-700 z-10 shrink-0">
                    <h3 class="text-white font-bold text-sm truncate flex items-center">
                        <DocumentDuplicateIcon class="w-4 h-4 mr-2 text-slate-400" />
                        File Preview
                    </h3>
                    
                    <!-- Zoom Controls (Visible only for Images) -->
                    <div v-if="previewType === 'image'" class="flex items-center space-x-1 bg-slate-700 rounded-lg p-1 mx-4">
                        <button @click="zoomOut" class="p-1.5 text-slate-300 hover:text-white hover:bg-slate-600 rounded transition-colors" title="Zoom Out">
                            <MagnifyingGlassMinusIcon class="w-4 h-4" />
                        </button>
                        <span class="text-xs text-slate-400 font-mono w-12 text-center">{{ Math.round(scale * 100) }}%</span>
                        <button @click="zoomIn" class="p-1.5 text-slate-300 hover:text-white hover:bg-slate-600 rounded transition-colors" title="Zoom In">
                            <MagnifyingGlassPlusIcon class="w-4 h-4" />
                        </button>
                        <div class="w-px h-4 bg-slate-600 mx-1"></div>
                        <button @click="resetZoom" class="p-1.5 text-slate-300 hover:text-white hover:bg-slate-600 rounded transition-colors" title="Reset">
                            <ArrowPathIcon class="w-4 h-4" />
                        </button>
                    </div>

                    <div class="flex items-center space-x-2">
                        <a 
                            v-if="previewDoc" 
                            :href="previewUrl" 
                            download 
                            class="text-slate-400 hover:text-white text-xs font-bold px-3 py-1.5 rounded hover:bg-slate-700 transition-colors"
                        >
                            Download
                        </a>
                        <button @click="closePreview" class="text-slate-400 hover:text-white p-1 rounded hover:bg-slate-700 transition-colors">
                            <XMarkIcon class="w-5 h-5" />
                        </button>
                    </div>
                </div>
                
                <!-- Content -->
                <div class="flex-1 overflow-hidden flex items-center justify-center bg-slate-900/95 relative p-4 select-none">
                    <div v-if="!previewDoc" class="text-slate-500">Loading...</div>
                    
                    <!-- Image Preview -->
                    <div 
                        v-else-if="previewType === 'image'"
                        class="w-full h-full flex items-center justify-center overflow-hidden cursor-move"
                        @wheel="onWheel"
                        @mousedown="startDrag"
                        @mousemove="onDrag"
                        @mouseup="stopDrag"
                        @mouseleave="stopDrag"
                    >
                        <img 
                            :src="previewUrl" 
                            class="max-w-none transition-transform duration-75 ease-out origin-center rounded shadow-2xl" 
                            :style="{ 
                                transform: `translate(${translateX}px, ${translateY}px) scale(${scale})`,
                                cursor: isDragging ? 'grabbing' : 'grab'
                            }"
                            draggable="false"
                            alt="Document Preview"
                        />
                    </div>
                    
                    <!-- PDF Preview -->
                    <iframe 
                        v-else-if="previewType === 'pdf'" 
                        :src="previewUrl" 
                        class="w-full h-full rounded bg-white"
                        frameborder="0"
                    ></iframe>
                    
                    <!-- Fallback -->
                    <div v-else class="text-center">
                        <div class="bg-slate-800 p-6 rounded-2xl inline-block mb-4">
                            <DocumentDuplicateIcon class="w-16 h-16 text-slate-600 mx-auto" />
                        </div>
                        <p class="text-slate-300 font-bold text-lg mb-2">Preview not available</p>
                        <p class="text-slate-500 text-sm mb-6">This file type cannot be displayed directly.</p>
                        <a 
                            :href="previewUrl" 
                            target="_blank" 
                            class="bg-blue-600 text-white px-6 py-2.5 rounded-xl font-bold hover:bg-blue-700 transition-colors"
                        >
                            Download File
                        </a>
                    </div>
                </div>
            </div>
        </Modal>

    </AppLayout>
</template>
