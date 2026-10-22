<?php
declare(strict_types=1);

/**
 * OVPRE I-Record System Demo Data & Files Seeder
 * Populates realistic sample data across all 9 colleges:
 * - Research Proposals, Publications, and Reports
 * - Extension Initiatives, Community Outreach, and Accomplishments
 * - Central Repository Documents with downloadable sample PDF files
 * - Campus Announcements and University Calendar Activities
 */

require_once __DIR__ . '/includes/bootstrap.php';

// Allow Super Admin, OVPRE Admin, or direct local command line access
$currentUser = null;
if (php_sapi_name() !== 'cli') {
    if (session_status() === PHP_SESSION_NONE) {
        session_start();
    }
    $currentUser = current_user();
    if (!$currentUser || !in_array($currentUser['role'], [ROLE_SUPER_ADMIN, ROLE_OVPRE_ADMIN], true)) {
        // If not logged in as admin in browser, provide a login prompt or run check
        if (empty($_SESSION['user_id'])) {
            header('Location: index.php');
            exit;
        }
    }
}

function create_sample_pdf(string $filePath, string $title, string $subtitle, string $content): void {
    $dir = dirname($filePath);
    if (!is_dir($dir)) {
        mkdir($dir, 0777, true);
    }
    
    $cleanTitle = substr(preg_replace('/[^a-zA-Z0-9 ,.\-:]/', '', $title), 0, 65);
    $cleanSub = substr(preg_replace('/[^a-zA-Z0-9 ,.\-:]/', '', $subtitle), 0, 75);
    $cleanContent = substr(preg_replace('/[^a-zA-Z0-9 ,.\-:]/', '', $content), 0, 180);

    $stream = "BT /F1 18 Tf 50 720 Td (" . $cleanTitle . ") Tj ET\n" .
              "BT /F2 12 Tf 50 690 Td (" . $cleanSub . ") Tj ET\n" .
              "BT /F2 10 Tf 50 650 Td (" . $cleanContent . ") Tj ET\n" .
              "BT /F2 9 Tf 50 100 Td (Official Sample Document - OVPRE I-Record Repository) Tj ET";
    $len = strlen($stream);

    $pdf = "%PDF-1.4\n" .
           "1 0 obj <</Type /Catalog /Pages 2 0 R>> endobj\n" .
           "2 0 obj <</Type /Pages /Kids [3 0 R] /Count 1>> endobj\n" .
           "3 0 obj <</Type /Page /Parent 2 0 R /Resources << /Font << /F1 4 0 R /F2 5 0 R >> >> /MediaBox [0 0 612 792] /Contents 6 0 R>> endobj\n" .
           "4 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica-Bold>> endobj\n" .
           "5 0 obj <</Type /Font /Subtype /Type1 /BaseFont /Helvetica>> endobj\n" .
           "6 0 obj <</Length {$len}>> stream\n{$stream}\nendstream\nendobj\n" .
           "xref\n0 7\n" .
           "0000000000 65535 f \n" .
           "0000000009 00000 n \n" .
           "0000000056 00000 n \n" .
           "0000000111 00000 n \n" .
           "0000000244 00000 n \n" .
           "0000000320 00000 n \n" .
           "0000000391 00000 n \n" .
           "trailer <</Size 7 /Root 1 0 R>>\n" .
           "startxref\n" . (450 + $len) . "\n%%EOF\n";

    file_put_contents($filePath, $pdf);
}

$results = [
    'users_created' => 0,
    'files_created' => 0,
    'documents_created' => 0,
    'research_created' => 0,
    'extension_created' => 0,
    'accomplishments_created' => 0,
    'announcements_created' => 0,
    'activities_created' => 0,
];

try {
    $pdo = db();

    // 1. Ensure Categories Exist
    $categoryMap = [];
    $existingCats = $pdo->query("SELECT id, category_name FROM categories")->fetchAll(PDO::FETCH_KEY_PAIR);
    $defaultCategories = [
        'Research Proposal',
        'Research Report',
        'Extension Project',
        'Accomplishment Report',
        'Publication',
        'Memorandum',
        'Administrative Record'
    ];
    foreach ($defaultCategories as $catName) {
        if (!in_array($catName, $existingCats, true)) {
            $stmt = $pdo->prepare("INSERT INTO categories (category_name) VALUES (?)");
            $stmt->execute([$catName]);
            $categoryMap[$catName] = (int) $pdo->lastInsertId();
        } else {
            $catId = array_search($catName, $existingCats, true);
            $categoryMap[$catName] = (int) $catId;
        }
    }

    // 2. Ensure Academic Departments Exist
    $deptMap = [];
    $existingDepts = $pdo->query("SELECT id, department_name FROM departments")->fetchAll(PDO::FETCH_KEY_PAIR);
    $defaultDepartments = [
        1 => 'College of Computer Studies and Technology',
        2 => 'College of Engineering',
        3 => 'College of Nursing and Allied Health Sciences',
        4 => 'College of Arts and Sciences',
        5 => 'College of Business Administration',
        6 => 'College of Human Kinetics',
        7 => 'College of Teacher Education',
        8 => 'College of Tourism and Hospitality Management',
        9 => 'College of Accountancy'
    ];
    foreach ($defaultDepartments as $dId => $dName) {
        $foundId = array_search($dName, $existingDepts, true);
        if ($foundId === false) {
            $stmt = $pdo->prepare("INSERT INTO departments (id, department_name, created_at) VALUES (?, ?, NOW())");
            $stmt->execute([$dId, $dName]);
            $deptMap[$dName] = $dId;
        } else {
            $deptMap[$dName] = (int) $foundId;
        }
    }

    // 3. Ensure a Proponent Faculty User exists for each Department
    $defaultPasswordHash = password_hash('password123', PASSWORD_BCRYPT);
    $facultyUsers = [
        1 => ['fullname' => 'Dr. Aris Thorne', 'email' => 'thorne.ccst@plsp.edu.ph', 'username' => 'athorne', 'role' => ROLE_RESEARCHER],
        2 => ['fullname' => 'Engr. Katrina Valdez', 'email' => 'valdez.coe@plsp.edu.ph', 'username' => 'kvaldez', 'role' => ROLE_RESEARCHER],
        3 => ['fullname' => 'Prof. Lorna Bautista', 'email' => 'bautista.conahs@plsp.edu.ph', 'username' => 'lbautista', 'role' => ROLE_FACULTY_EXTENSIONIST],
        4 => ['fullname' => 'Dr. Gabriel Mendoza', 'email' => 'mendoza.cas@plsp.edu.ph', 'username' => 'gmendoza', 'role' => ROLE_RESEARCHER],
        5 => ['fullname' => 'Prof. Raymond Cruz', 'email' => 'cruz.cba@plsp.edu.ph', 'username' => 'rcruz', 'role' => ROLE_FACULTY_EXTENSIONIST],
        6 => ['fullname' => 'Coach Daniel Flores', 'email' => 'flores.chk@plsp.edu.ph', 'username' => 'dflores', 'role' => ROLE_RESEARCHER],
        7 => ['fullname' => 'Dr. Maricel Delos Reyes', 'email' => 'delosreyes.cte@plsp.edu.ph', 'username' => 'mdelosreyes', 'role' => ROLE_RESEARCH_COORDINATOR],
        8 => ['fullname' => 'Prof. Janice Aquino', 'email' => 'aquino.cthm@plsp.edu.ph', 'username' => 'jaquino', 'role' => ROLE_FACULTY_EXTENSIONIST],
        9 => ['fullname' => 'Atty. Victor Ramos, CPA', 'email' => 'ramos.coa@plsp.edu.ph', 'username' => 'vramos', 'role' => ROLE_EXTENSION_COORDINATOR],
    ];

    $userDeptMap = [];
    foreach ($facultyUsers as $deptId => $u) {
        $stmt = $pdo->prepare("SELECT id FROM users WHERE username = ? OR email = ? LIMIT 1");
        $stmt->execute([$u['username'], $u['email']]);
        $existingId = $stmt->fetchColumn();

        if ($existingId) {
            $userDeptMap[$deptId] = (int) $existingId;
        } else {
            $insertStmt = $pdo->prepare("INSERT INTO users (fullname, email, username, password, role, department_id, status, created_at) VALUES (?, ?, ?, ?, ?, ?, 'Active', NOW())");
            $insertStmt->execute([$u['fullname'], $u['email'], $u['username'], $defaultPasswordHash, $u['role'], $deptId]);
            $userDeptMap[$deptId] = (int) $pdo->lastInsertId();
            $results['users_created']++;
        }
    }

    $adminId = 1; // Default System Admin

    // 4. Sample Documents & Files Creation
    $uploadRecordsDir = __DIR__ . '/uploads/records';
    if (!is_dir($uploadRecordsDir)) {
        mkdir($uploadRecordsDir, 0777, true);
    }

    $sampleDataset = [
        // CCST (Dept 1)
        [
            'dept_id' => 1,
            'cat' => 'Research Proposal',
            'title' => 'AI-Driven Crop Disease Detection for Calabarzon Agrarian Farmers',
            'abstract' => 'An edge-computing mobile application using Convolutional Neural Networks (CNN) to diagnose common plant pathologies in coconut and rice crops in real-time.',
            'res_status' => 'Pending Executive Approval',
            'grant' => 150000.00,
            'funding' => 'Institutional Research Seed Fund',
            'author' => 'Dr. Aris Thorne',
            'ext_data' => null
        ],
        [
            'dept_id' => 1,
            'cat' => 'Publication',
            'title' => 'A Benchmark on Lightweight Cryptographic Ciphers for IoT Microgrids',
            'abstract' => 'Empirical analysis of AES-128, ChaCha20, and Ascon lightweight encryption primitives running on resource-constrained ESP32 nodes.',
            'res_status' => 'Approved',
            'grant' => 75000.00,
            'funding' => 'CHED Institutional Development Fund',
            'author' => 'Dr. Aris Thorne',
            'ext_data' => null
        ],
        [
            'dept_id' => 1,
            'cat' => 'Extension Project',
            'title' => 'Digital Literacy and Cybersecurity Bootcamp for San Pablo Senior Citizens',
            'abstract' => 'Community-driven extension program providing hands-on mobile banking and fraud awareness seminars across 10 barangays in San Pablo City.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Dr. Aris Thorne',
            'ext_data' => [
                'coordinator' => 'Dr. Aris Thorne',
                'partner' => 'Office of Senior Citizens Affairs (OSCA) San Pablo',
                'budget' => 65000.00,
                'start' => date('Y-m-d', strtotime('-2 months')),
                'end' => date('Y-m-d', strtotime('+4 months')),
                'progress' => 'Ongoing',
                'status' => 'Approved'
            ]
        ],

        // Engineering (Dept 2)
        [
            'dept_id' => 2,
            'cat' => 'Research Proposal',
            'title' => 'Solar-Powered Automated Rainwater Harvesting and Potability Monitoring Station',
            'abstract' => 'Design and deployment of smart multiparameter sensors (turbidity, pH, TDS) integrated with solar micro-controllers for local flood-prone barangays.',
            'res_status' => 'Under Review',
            'grant' => 220000.00,
            'funding' => 'DOST-SEI Grant-in-Aid',
            'author' => 'Engr. Katrina Valdez',
            'ext_data' => null
        ],
        [
            'dept_id' => 2,
            'cat' => 'Publication',
            'title' => 'Thermal Performance Evaluation of Bio-Composite Bricks Using Rice Husk Ash',
            'abstract' => 'Investigation into compressive strength and thermal dissipation properties of eco-friendly masonry units produced from agricultural by-products.',
            'res_status' => 'Approved',
            'grant' => 90000.00,
            'funding' => 'University Creative Works Fund',
            'author' => 'Engr. Katrina Valdez',
            'ext_data' => null
        ],
        [
            'dept_id' => 2,
            'cat' => 'Extension Project',
            'title' => 'Community Water Filtration System Installation in Barangay San Lucas',
            'abstract' => 'Engineering extension project installing low-cost gravity-fed multi-stage filtration units for underserved households in Sitio Ilaya.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Engr. Katrina Valdez',
            'ext_data' => [
                'coordinator' => 'Engr. Katrina Valdez',
                'partner' => 'Barangay Council of San Lucas II',
                'budget' => 110000.00,
                'start' => date('Y-m-d', strtotime('-5 months')),
                'end' => date('Y-m-d', strtotime('-1 month')),
                'progress' => 'Completed',
                'status' => 'Approved'
            ]
        ],

        // CONAHS (Dept 3)
        [
            'dept_id' => 3,
            'cat' => 'Research Proposal',
            'title' => 'Pediatric Immunization Hesitancy Dynamics in Post-Pandemic Rural Households',
            'abstract' => 'A cross-sectional epidemiologic study investigating behavioral predictors and health literacy factors among mothers in rural Laguna communities.',
            'res_status' => 'Pending Coordinator Review',
            'grant' => 125000.00,
            'funding' => 'Department of Health Health Systems Fund',
            'author' => 'Prof. Lorna Bautista',
            'ext_data' => null
        ],
        [
            'dept_id' => 3,
            'cat' => 'Extension Project',
            'title' => 'Oplan Kalusugan: Barangay Maternal and Neonatal Care Mentorship',
            'abstract' => 'Comprehensive healthcare outreach training barangay health workers in pre-natal monitoring, gestational nutrition, and infant care.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Prof. Lorna Bautista',
            'ext_data' => [
                'coordinator' => 'Prof. Lorna Bautista',
                'partner' => 'City Health Office - San Pablo',
                'budget' => 85000.00,
                'start' => date('Y-m-d', strtotime('-1 month')),
                'end' => date('Y-m-d', strtotime('+5 months')),
                'progress' => 'Ongoing',
                'status' => 'Approved'
            ]
        ],
        [
            'dept_id' => 3,
            'cat' => 'Accomplishment Report',
            'title' => 'Terminal Accomplishment Report: Oplan Kalusugan Barangay Health Mission Phase 1',
            'abstract' => 'Summary documentation and beneficiary impact assessment for healthcare support provided to 420 indigent families in District 3.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Prof. Lorna Bautista',
            'ext_data' => null
        ],

        // CAS (Dept 4)
        [
            'dept_id' => 4,
            'cat' => 'Research Proposal',
            'title' => 'Preservation of San Pablo Indigenous Folk Legends and Oral Literature',
            'abstract' => 'Cultural and linguistic documentation of Seven Lakes folklore through dialectological mapping and video ethnography.',
            'res_status' => 'Approved',
            'grant' => 80000.00,
            'funding' => 'National Commission for Culture and the Arts (NCCA)',
            'author' => 'Dr. Gabriel Mendoza',
            'ext_data' => null
        ],
        [
            'dept_id' => 4,
            'cat' => 'Publication',
            'title' => 'Limnological Assessment and Biodiversity Indices of Lake Pandin and Lake Yambo',
            'abstract' => 'A two-year comparative limnological study examining microflora, dissolved oxygen variations, and anthropogenic impacts on Laguna volcanic crater lakes.',
            'res_status' => 'Approved',
            'grant' => 130000.00,
            'funding' => 'OVPRE Research Grant',
            'author' => 'Dr. Gabriel Mendoza',
            'ext_data' => null
        ],

        // CBA (Dept 5)
        [
            'dept_id' => 5,
            'cat' => 'Research Proposal',
            'title' => 'Micro-Enterprise E-Commerce Adoption and Supply Chain Resiliency in Laguna',
            'abstract' => 'Empirical survey examining financial technology uptake, online logistics barriers, and inventory replenishment patterns among 250 local MSMEs.',
            'res_status' => 'Pending Executive Approval',
            'grant' => 95000.00,
            'funding' => 'DTI-University Partnership Grant',
            'author' => 'Prof. Raymond Cruz',
            'ext_data' => null
        ],
        [
            'dept_id' => 5,
            'cat' => 'Extension Project',
            'title' => 'MSME Financial Management and Digital Bookkeeping Mentorship Clinic',
            'abstract' => 'Series of weekend workshops training women cooperative entrepreneurs on basic QuickBooks, cloud invoicing, and tax compliance.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Prof. Raymond Cruz',
            'ext_data' => [
                'coordinator' => 'Prof. Raymond Cruz',
                'partner' => 'San Pablo City Chamber of Commerce',
                'budget' => 70000.00,
                'start' => date('Y-m-d', strtotime('-3 months')),
                'end' => date('Y-m-d', strtotime('+3 months')),
                'progress' => 'Ongoing',
                'status' => 'Approved'
            ]
        ],

        // CHK (Dept 6)
        [
            'dept_id' => 6,
            'cat' => 'Research Proposal',
            'title' => 'High-Intensity Tactical Conditioning Protocol for Collegiate Athletes',
            'abstract' => 'Comparative biomechanical analysis of periodized plyometrics versus continuous resistance regimens on agility and aerobic capacity.',
            'res_status' => 'Under Review',
            'grant' => 50000.00,
            'funding' => 'Institutional Sports Science Fund',
            'author' => 'Coach Daniel Flores',
            'ext_data' => null
        ],
        [
            'dept_id' => 6,
            'cat' => 'Extension Project',
            'title' => 'Community Grassroots Sports Clinic and Youth Wellness Initiative',
            'abstract' => 'Physical education workshops, basic motor development clinics, and equipment donations for out-of-school youth in Barangay Del Remedio.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Coach Daniel Flores',
            'ext_data' => [
                'coordinator' => 'Coach Daniel Flores',
                'partner' => 'Sangguniang Kabataan Federation',
                'budget' => 45000.00,
                'start' => date('Y-m-d', strtotime('-4 months')),
                'end' => date('Y-m-d', strtotime('-1 month')),
                'progress' => 'Completed',
                'status' => 'Approved'
            ]
        ],

        // CTE (Dept 7)
        [
            'dept_id' => 7,
            'cat' => 'Research Proposal',
            'title' => 'Adaptive Blended Learning Efficacy in Secondary Public School Mathematics',
            'abstract' => 'Action research evaluating gamified modular learning packets and peer-assisted tutoring to bridge pandemic-induced numeracy gaps.',
            'res_status' => 'Approved',
            'grant' => 110000.00,
            'funding' => 'DepEd-Higher Education Research Fellowship',
            'author' => 'Dr. Maricel Delos Reyes',
            'ext_data' => null
        ],
        [
            'dept_id' => 7,
            'cat' => 'Publication',
            'title' => 'Pedagogical Framework for Multilingual Mother-Tongue Classroom Instruction',
            'abstract' => 'Reframing early grade literacy assessments using contextualized Tagalog learning materials in CALABARZON schools.',
            'res_status' => 'Approved',
            'grant' => 60000.00,
            'funding' => 'OVPRE Publication Incentive Grant',
            'author' => 'Dr. Maricel Delos Reyes',
            'ext_data' => null
        ],

        // CTHM (Dept 8)
        [
            'dept_id' => 8,
            'cat' => 'Research Proposal',
            'title' => 'Agri-Tourism Destination Competitiveness and Heritage Culinary Mapping',
            'abstract' => 'Assessment of sustainable farm tourism destinations in San Pablo City with economic impact projections for culinary heritage preservation.',
            'res_status' => 'Pending Coordinator Review',
            'grant' => 75000.00,
            'funding' => 'DOT Tourism Development Fund',
            'author' => 'Prof. Janice Aquino',
            'ext_data' => null
        ],
        [
            'dept_id' => 8,
            'cat' => 'Extension Project',
            'title' => 'Barangay Homestay Hospitality and Sanitation Standards Workshop',
            'abstract' => 'Capacity building seminars for local ecotourism homestay operators along Sampaloc Lake and Pandin Lake.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Prof. Janice Aquino',
            'ext_data' => [
                'coordinator' => 'Prof. Janice Aquino',
                'partner' => 'City Tourism Office - San Pablo',
                'budget' => 55000.00,
                'start' => date('Y-m-d', strtotime('-2 months')),
                'end' => date('Y-m-d', strtotime('+2 months')),
                'progress' => 'Ongoing',
                'status' => 'Approved'
            ]
        ],

        // COA (Dept 9)
        [
            'dept_id' => 9,
            'cat' => 'Research Proposal',
            'title' => 'Internal Audit Compliance and Public Procurement Governance in Local SUCs',
            'abstract' => 'Evaluating adherence to RA 9184 standards, value-for-money audits, and internal control effectiveness in Southern Luzon state universities.',
            'res_status' => 'Approved',
            'grant' => 100000.00,
            'funding' => 'Institutional Governance Seed Fund',
            'author' => 'Atty. Victor Ramos, CPA',
            'ext_data' => null
        ],
        [
            'dept_id' => 9,
            'cat' => 'Extension Project',
            'title' => 'Tax Literacy, Barangay Financial Bookkeeping and COA Audit Preparedness',
            'abstract' => 'Outreach service training 40 barangay treasurers on automated cash disbursement registers, withholding taxes, and audit trails.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Atty. Victor Ramos, CPA',
            'ext_data' => [
                'coordinator' => 'Atty. Victor Ramos, CPA',
                'partner' => 'Liga ng mga Barangay - San Pablo Chapter',
                'budget' => 90000.00,
                'start' => date('Y-m-d', strtotime('-6 months')),
                'end' => date('Y-m-d', strtotime('-2 months')),
                'progress' => 'Completed',
                'status' => 'Approved'
            ]
        ],
        [
            'dept_id' => 9,
            'cat' => 'Accomplishment Report',
            'title' => 'Terminal Accomplishment Report: Tax Literacy & Barangay Bookkeeping Program',
            'abstract' => 'Comprehensive completion documentation, attendance records, and post-training financial audit scorecards for participating treasurers.',
            'res_status' => null,
            'grant' => null,
            'funding' => null,
            'author' => 'Atty. Victor Ramos, CPA',
            'ext_data' => null
        ],
    ];

    foreach ($sampleDataset as $item) {
        $deptId = $item['dept_id'];
        $catName = $item['cat'];
        $categoryId = $categoryMap[$catName] ?? 1;
        $userId = $userDeptMap[$deptId] ?? $adminId;

        // Generate a distinct readable PDF file in uploads/records/
        $fileSlug = strtolower(preg_replace('/[^a-zA-Z0-9]+/', '-', substr($item['title'], 0, 40)));
        $fileName = 'sample-' . $deptId . '-' . $fileSlug . '.pdf';
        $fullFilePath = $uploadRecordsDir . '/' . $fileName;

        create_sample_pdf(
            $fullFilePath,
            $item['title'],
            'Department: ' . ($defaultDepartments[$deptId] ?? 'OVPRE') . ' | Category: ' . $catName,
            $item['abstract']
        );
        $results['files_created']++;

        // 1. Check if Document already exists
        $docStmt = $pdo->prepare("SELECT id FROM documents WHERE title = ? LIMIT 1");
        $docStmt->execute([$item['title']]);
        $documentId = $docStmt->fetchColumn();

        if (!$documentId) {
            $insertDoc = $pdo->prepare("
                INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at)
                VALUES (?, ?, ?, ?, ?, ?, ?, ?, 'Approved', CURRENT_DATE, CURRENT_TIMESTAMP - INTERVAL '15 days')
            ");
            $insertDoc->execute([
                $item['title'],
                $categoryId,
                $item['abstract'],
                $item['author'],
                $deptId,
                $fileName,
                basename($fileName),
                $userId
            ]);
            $documentId = (int) $pdo->lastInsertId();
            $results['documents_created']++;

            // Create Document Version
            $vStmt = $pdo->prepare("INSERT INTO document_versions (document_id, version_label, filename, modified_by, created_at) VALUES (?, 'Original Version', ?, ?, CURRENT_TIMESTAMP)");
            $vStmt->execute([$documentId, $fileName, $userId]);
        } else {
            $documentId = (int) $documentId;
        }

        // 2. Insert Research Project if it's a Research Proposal or Publication
        if ($item['res_status'] !== null) {
            $resStmt = $pdo->prepare("SELECT id FROM research_projects WHERE title = ? LIMIT 1");
            $resStmt->execute([$item['title']]);
            $existingRes = $resStmt->fetchColumn();

            if (!$existingRes) {
                $insRes = $pdo->prepare("
                    INSERT INTO research_projects (title, researchers, abstract, funding_source, grant_amount, document_id, submitted_by, status, workflow_stage, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, CURRENT_TIMESTAMP - INTERVAL '20 days')
                ");
                $stage = ($item['res_status'] === 'Approved') ? 'Completed' : 'Proposal';
                $insRes->execute([
                    $item['title'],
                    $item['author'],
                    $item['abstract'],
                    $item['funding'],
                    $item['grant'],
                    $documentId,
                    $userId,
                    $item['res_status'],
                    $stage
                ]);
                $results['research_created']++;
            }
        }

        // 3. Insert Extension Project if applicable
        if ($item['ext_data'] !== null) {
            $ext = $item['ext_data'];
            $extStmt = $pdo->prepare("SELECT id FROM extension_projects WHERE project_title = ? LIMIT 1");
            $extStmt->execute([$item['title']]);
            $existingExt = $extStmt->fetchColumn();

            if (!$existingExt) {
                $insExt = $pdo->prepare("
                    INSERT INTO extension_projects (project_title, coordinator, community_partner, budget, approved_budget, start_date, end_date, progress_status, status, workflow_stage, document_id, submitted_by, created_at)
                    VALUES (?, ?, ?, ?, ?, ?, ?, ?, ?, 'Proposal', ?, ?, CURRENT_TIMESTAMP - INTERVAL '30 days')
                ");
                $insExt->execute([
                    $item['title'],
                    $ext['coordinator'],
                    $ext['partner'],
                    $ext['budget'],
                    $ext['budget'],
                    $ext['start'],
                    $ext['end'],
                    $ext['progress'],
                    $ext['status'],
                    $documentId,
                    $userId
                ]);
                $results['extension_created']++;
            }
        }

        // 4. Insert Accomplishment Report if applicable
        if ($catName === 'Accomplishment Report') {
            try {
                $accStmt = $pdo->prepare("SELECT id FROM accomplishment_reports WHERE title = ? LIMIT 1");
                $accStmt->execute([$item['title']]);
                if (!$accStmt->fetchColumn()) {
                    $insAcc = $pdo->prepare("
                        INSERT INTO accomplishment_reports (title, document_id, submitted_by, created_at)
                        VALUES (?, ?, ?, CURRENT_TIMESTAMP - INTERVAL '10 days')
                    ");
                    $insAcc->execute([$item['title'], $documentId, $userId]);
                    $results['accomplishments_created']++;
                }
            } catch (Throwable $e) {}
        }
    }

    // 5. University Announcements
    $sampleAnnouncements = [
        [
            'title' => 'Call for Institutional Research & Creative Works Proposals (CY 2026-2027)',
            'content' => 'The Office of the Vice President for Research and Extension announces the open call for faculty institutional research grants. Proposals qualifying under priority university niches (AI, Ecotourism, Sustainable Infrastructure, Public Health) may receive up to ₱250,000 seed funding. Submit via I-Record portal before the deadline.',
            'category' => 'Research',
            'audience' => 'All'
        ],
        [
            'title' => 'FY 2026 2nd Quarter Community Extension Review & Monitoring',
            'content' => 'All College Extension Coordinators and Faculty Extensionists are advised to submit mid-year progress documentations and beneficiary verification forms for ongoing barangay projects by next month.',
            'category' => 'Extension',
            'audience' => 'Coordinators'
        ],
        [
            'title' => 'OVPRE Research Colloquium & Best Paper Presentation',
            'content' => 'Registration is now open for the Annual University Research Colloquium. Outstanding faculty manuscripts and completed extension impact reports will be featured with institutional publication awards.',
            'category' => 'Events',
            'audience' => 'Faculty'
        ]
    ];

    foreach ($sampleAnnouncements as $ann) {
        $annCheck = $pdo->prepare("SELECT id FROM announcements WHERE title = ? LIMIT 1");
        $annCheck->execute([$ann['title']]);
        if (!$annCheck->fetchColumn()) {
            $annIns = $pdo->prepare("INSERT INTO announcements (title, content, audience, status, posted_by, created_at) VALUES (?, ?, ?, 'Published', ?, NOW())");
            $annIns->execute([$ann['title'], $ann['content'], $ann['audience'], $adminId]);
            $results['announcements_created']++;
        }
    }

    // 6. University Calendar Activities
    $sampleActivities = [
        [
            'title' => 'Annual University Research Colloquium 2026',
            'type' => 'Seminar',
            'date' => date('Y-m-d', strtotime('+14 days')),
            'venue' => 'University Gymnasium & Performing Arts Auditorium',
            'desc' => 'Multi-college academic colloquium and peer review sessions for approved faculty and graduate research studies.'
        ],
        [
            'title' => 'Grant Proposal Writing Workshop for Early-Career Faculty',
            'type' => 'Workshop',
            'date' => date('Y-m-d', strtotime('+7 days')),
            'venue' => 'Audio-Visual Room (AVR), Main Building',
            'desc' => 'Capacity building on crafting CHED- and DOST-compliant funding proposals with RIUH evaluation scoring benchmarks.'
        ],
        [
            'title' => 'Barangay Health & Livelihood Community Outreach Caravan',
            'type' => 'Community Outreach',
            'date' => date('Y-m-d', strtotime('+21 days')),
            'venue' => 'Barangay San Lucas Covered Court',
            'desc' => 'Inter-college extension mission combining medical screening, digital literacy tutorials, and micro-business counseling.'
        ]
    ];

    foreach ($sampleActivities as $act) {
        $actCheck = $pdo->prepare("SELECT id FROM activities WHERE title = ? LIMIT 1");
        $actCheck->execute([$act['title']]);
        if (!$actCheck->fetchColumn()) {
            $actIns = $pdo->prepare("INSERT INTO activities (title, activity_type, date, venue, description, approval_status, created_by, created_at) VALUES (?, ?, ?, ?, ?, 'Approved', ?, NOW())");
            $actIns->execute([$act['title'], $act['type'], $act['date'], $act['venue'], $act['desc'], $adminId]);
            $results['activities_created']++;
        }
    }

    // Log the action
    log_action($adminId, 'Executed Demo Data & Sample Files Seeder');

} catch (Throwable $e) {
    $error = $e->getMessage();
}

if (php_sapi_name() === 'cli') {
    if (!empty($error)) {
        echo "Error during database seeding: {$error}\n";
        exit(1);
    }
    echo "=============================================\n";
    echo "  OVPRE DEMO DATA SEEDED SUCCESSFULLY!      \n";
    echo "=============================================\n";
    foreach ($results as $k => $v) {
        echo " - " . str_pad($k, 25) . ": {$v}\n";
    }
    echo "=============================================\n";
    exit(0);
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Demo Data Seeder | OVPRE I-Record</title>
    <link rel="icon" type="image/jpeg" href="assets/img/ovpre-logo.jpg">
    <link rel="stylesheet" href="assets/css/style.css">
    <link href="https://cdn.jsdelivr.net/npm/remixicon@4.2.0/fonts/remixicon.css" rel="stylesheet">
    <style>
        body { background: #f8fafc; font-family: system-ui, -apple-system, sans-serif; padding: 40px 20px; }
        .seed-container { max-width: 780px; margin: 0 auto; background: #ffffff; border-radius: 16px; border: 1px solid #e2e8f0; padding: 32px; box-shadow: 0 10px 25px rgba(0,0,0,0.05); }
        .seed-head { display: flex; align-items: center; gap: 14px; margin-bottom: 24px; padding-bottom: 20px; border-bottom: 1px solid #f1f5f9; }
        .seed-head i { font-size: 2.2rem; color: #15803d; }
        .seed-grid { display: grid; grid-template-columns: repeat(auto-fit, minmax(160px, 1fr)); gap: 14px; margin: 24px 0; }
        .seed-card { background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; padding: 14px; text-align: center; }
        .seed-card span { display: block; font-size: 0.75rem; font-weight: 700; color: #64748b; text-transform: uppercase; margin-bottom: 4px; }
        .seed-card strong { font-size: 1.6rem; color: #0f172a; font-weight: 800; }
        .action-row { display: flex; gap: 12px; margin-top: 28px; }
        .btn-green { background: #15803d; color: #ffffff; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }
        .btn-green:hover { background: #166534; }
        .btn-outline { background: #ffffff; color: #334155; border: 1px solid #cbd5e1; padding: 10px 20px; border-radius: 8px; text-decoration: none; font-weight: 700; display: inline-flex; align-items: center; gap: 8px; }
        .btn-outline:hover { background: #f8fafc; }
    </style>
</head>
<body>
    <div class="seed-container">
        <div class="seed-head">
            <i class="ri-database-2-line"></i>
            <div>
                <h1 style="margin: 0; font-size: 1.5rem; color: #0f172a; font-weight: 800;">OVPRE Demo Data &amp; Files Seeder</h1>
                <p style="margin: 4px 0 0; color: #64748b; font-size: 0.9rem;">Comprehensive data generation across all 9 academic colleges and document repository.</p>
            </div>
        </div>

        <?php if (!empty($error)): ?>
            <div style="background: #fef2f2; border: 1px solid #fecaca; color: #991b1b; padding: 14px; border-radius: 8px; margin-bottom: 20px;">
                <strong><i class="ri-error-warning-line"></i> Error during seeding:</strong> <?php echo h($error); ?>
            </div>
        <?php else: ?>
            <div style="background: #f0fdf4; border: 1px solid #bbf7d0; color: #166534; padding: 14px; border-radius: 8px; margin-bottom: 20px; display: flex; align-items: center; gap: 10px;">
                <i class="ri-checkbox-circle-line" style="font-size: 1.3rem;"></i>
                <span><strong>Demo Data Populated Successfully!</strong> Sample research projects, community extension outreach, and downloadable PDF files are now active.</span>
            </div>

            <div class="seed-grid">
                <div class="seed-card">
                    <span>Generated Files</span>
                    <strong style="color: #2563eb;"><?php echo $results['files_created']; ?></strong>
                </div>
                <div class="seed-card">
                    <span>Documents</span>
                    <strong style="color: #15803d;"><?php echo $results['documents_created']; ?></strong>
                </div>
                <div class="seed-card">
                    <span>Research Projects</span>
                    <strong style="color: #7c3aed;"><?php echo $results['research_created']; ?></strong>
                </div>
                <div class="seed-card">
                    <span>Extension Projects</span>
                    <strong style="color: #0284c7;"><?php echo $results['extension_created']; ?></strong>
                </div>
                <div class="seed-card">
                    <span>Accomplishments</span>
                    <strong style="color: #ea580c;"><?php echo $results['accomplishments_created']; ?></strong>
                </div>
                <div class="seed-card">
                    <span>Announcements</span>
                    <strong style="color: #d97706;"><?php echo $results['announcements_created']; ?></strong>
                </div>
            </div>

            <div style="padding: 16px; background: #f8fafc; border: 1px solid #e2e8f0; border-radius: 10px; font-size: 0.88rem; color: #475569;">
                <strong><i class="ri-folder-open-line"></i> Sample Physical Files Created in:</strong> <code>uploads/records/</code><br>
                All sample PDF documents can now be downloaded and previewed directly through the <a href="admin-documents.php" style="color: #15803d; font-weight: 700;">Document Repository</a>.
            </div>
        <?php endif; ?>

        <div class="action-row">
            <a href="dashboard.php" class="btn-green">
                <i class="ri-dashboard-line"></i> Return to Admin Dashboard
            </a>
            <a href="admin-documents.php" class="btn-outline">
                <i class="ri-file-list-3-line"></i> View Document Repository
            </a>
            <a href="research.php" class="btn-outline">
                <i class="ri-flask-line"></i> Research Pipeline
            </a>
            <a href="extension.php" class="btn-outline">
                <i class="ri-community-line"></i> Extension Pipeline
            </a>
        </div>
    </div>
</body>
</html>
