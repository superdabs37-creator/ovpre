-- ==========================================================
-- OVPRE I-Record System Demo Data Seed Script
-- Includes Research Proposals, Publications, Extension Projects,
-- Accomplishment Reports, Documents, Announcements & Activities
-- ==========================================================

-- 1. Ensure Categories Exist
INSERT INTO categories (id, category_name) VALUES
(1, 'Research Proposal'),
(2, 'Research Report'),
(3, 'Extension Project'),
(4, 'Accomplishment Report'),
(5, 'Publication'),
(6, 'Memorandum'),
(7, 'Administrative Record')
ON DUPLICATE KEY UPDATE category_name = VALUES(category_name);

-- 2. Ensure Departments Exist
INSERT INTO departments (id, department_name, created_at) VALUES
(1, 'College of Computer Studies and Technology', NOW()),
(2, 'College of Engineering', NOW()),
(3, 'College of Nursing and Allied Health Sciences', NOW()),
(4, 'College of Arts and Sciences', NOW()),
(5, 'College of Business Administration', NOW()),
(6, 'College of Human Kinetics', NOW()),
(7, 'College of Teacher Education', NOW()),
(8, 'College of Tourism and Hospitality Management', NOW()),
(9, 'College of Accountancy', NOW())
ON DUPLICATE KEY UPDATE department_name = VALUES(department_name);

-- 3. Sample Documents (with actual local files in uploads/records/)
INSERT INTO documents (title, category_id, description, author, department_id, filename, original_filename, uploaded_by, status, submission_date, created_at) VALUES
('AI-Driven Crop Disease Detection for Calabarzon Agrarian Farmers', 1, 'Edge-computing mobile application using CNN to diagnose plant pathologies in coconut and rice crops.', 'Dr. Aris Thorne', 1, 'sample-1-ai-driven-crop-disease-detection.pdf', 'AI-Driven Crop Disease Detection.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 15 DAY),
('A Benchmark on Lightweight Cryptographic Ciphers for IoT Microgrids', 5, 'Empirical analysis of AES-128 and ChaCha20 lightweight encryption primitives.', 'Dr. Aris Thorne', 1, 'sample-1-a-benchmark-on-lightweight-cryptographi.pdf', 'Lightweight Ciphers IoT.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 20 DAY),
('Digital Literacy and Cybersecurity Bootcamp for San Pablo Senior Citizens', 3, 'Community-driven extension program providing hands-on mobile banking and fraud awareness seminars.', 'Dr. Aris Thorne', 1, 'sample-1-digital-literacy-and-cybersecurity-bootc.pdf', 'Digital Literacy Bootcamp MOA.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 30 DAY),
('Solar-Powered Automated Rainwater Harvesting and Potability Monitoring Station', 1, 'Design of smart multiparameter sensors integrated with solar microcontrollers for flood-prone barangays.', 'Engr. Katrina Valdez', 2, 'sample-2-solar-powered-automated-rainwater-harves.pdf', 'Solar Rainwater Harvesting Proposal.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 12 DAY),
('Thermal Performance Evaluation of Bio-Composite Bricks Using Rice Husk Ash', 5, 'Compressive strength and thermal dissipation properties of eco-friendly masonry units.', 'Engr. Katrina Valdez', 2, 'sample-2-thermal-performance-evaluation-of-bio-co.pdf', 'Bio-Composite Bricks Manuscript.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 25 DAY),
('Community Water Filtration System Installation in Barangay San Lucas', 3, 'Engineering extension project installing gravity-fed filtration units for Sitio Ilaya.', 'Engr. Katrina Valdez', 2, 'sample-2-community-water-filtration-system-instal.pdf', 'Water Filtration Barangay MOA.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 40 DAY),
('Pediatric Immunization Hesitancy Dynamics in Post-Pandemic Rural Households', 1, 'Cross-sectional epidemiologic study investigating predictors among rural Laguna mothers.', 'Prof. Lorna Bautista', 3, 'sample-3-pediatric-immunization-hesitancy-dynamic.pdf', 'Immunization Hesitancy Study.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 8 DAY),
('Oplan Kalusugan: Barangay Maternal and Neonatal Care Mentorship', 3, 'Healthcare outreach training barangay health workers in pre-natal monitoring and infant care.', 'Prof. Lorna Bautista', 3, 'sample-3-oplan-kalusugan-barangay-maternal-and-ne.pdf', 'Oplan Kalusugan Outreach Plan.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 35 DAY),
('Terminal Accomplishment Report: Oplan Kalusugan Barangay Health Mission Phase 1', 4, 'Summary documentation and beneficiary impact assessment for 420 indigent families in District 3.', 'Prof. Lorna Bautista', 3, 'sample-3-terminal-accomplishment-report-oplan-kal.pdf', 'Oplan Kalusugan Terminal Report.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 5 DAY),
('Preservation of San Pablo Indigenous Folk Legends and Oral Literature', 1, 'Cultural and linguistic documentation of Seven Lakes folklore through dialectological mapping.', 'Dr. Gabriel Mendoza', 4, 'sample-4-preservation-of-san-pablo-indigenous-fol.pdf', 'Seven Lakes Folklore Proposal.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 18 DAY),
('Limnological Assessment and Biodiversity Indices of Lake Pandin and Lake Yambo', 5, 'Two-year limnological study examining microflora and dissolved oxygen variations.', 'Dr. Gabriel Mendoza', 4, 'sample-4-limnological-assessment-and-biodiversity.pdf', 'Lake Limnology Research Paper.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 28 DAY),
('Micro-Enterprise E-Commerce Adoption and Supply Chain Resiliency in Laguna', 1, 'Empirical survey examining financial technology uptake and logistics barriers among 250 MSMEs.', 'Prof. Raymond Cruz', 5, 'sample-5-micro-enterprise-e-commerce-adoption-and.pdf', 'MSME E-Commerce Adoption.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 14 DAY),
('MSME Financial Management and Digital Bookkeeping Mentorship Clinic', 3, 'Weekend workshops training women cooperative entrepreneurs on basic QuickBooks and cloud invoicing.', 'Prof. Raymond Cruz', 5, 'sample-5-msme-financial-management-and-digital-bo.pdf', 'Financial Management Clinic MOA.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 22 DAY),
('High-Intensity Tactical Conditioning Protocol for Collegiate Athletes', 1, 'Comparative biomechanical analysis of periodized plyometrics on agility and aerobic capacity.', 'Coach Daniel Flores', 6, 'sample-6-high-intensity-tactical-conditioning-pro.pdf', 'Athletic Conditioning Protocol.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 10 DAY),
('Community Grassroots Sports Clinic and Youth Wellness Initiative', 3, 'Physical education workshops and equipment donations for out-of-school youth in Del Remedio.', 'Coach Daniel Flores', 6, 'sample-6-community-grassroots-sports-clinic-and-y.pdf', 'Grassroots Sports Project.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 32 DAY),
('Adaptive Blended Learning Efficacy in Secondary Public School Mathematics', 1, 'Action research evaluating gamified modular learning packets in secondary education.', 'Dr. Maricel Delos Reyes', 7, 'sample-7-adaptive-blended-learning-efficacy-in-se.pdf', 'Math Blended Learning Proposal.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 16 DAY),
('Pedagogical Framework for Multilingual Mother-Tongue Classroom Instruction', 5, 'Reframing early grade literacy assessments using contextualized Tagalog materials.', 'Dr. Maricel Delos Reyes', 7, 'sample-7-pedagogical-framework-for-multilingual-m.pdf', 'Mother Tongue Pedagogy Paper.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 24 DAY),
('Agri-Tourism Destination Competitiveness and Heritage Culinary Mapping', 1, 'Assessment of farm tourism destinations with economic projections for culinary heritage preservation.', 'Prof. Janice Aquino', 8, 'sample-8-agri-tourism-destination-competitiveness.pdf', 'Agri-Tourism Mapping Proposal.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 11 DAY),
('Barangay Homestay Hospitality and Sanitation Standards Workshop', 3, 'Capacity building seminars for local ecotourism homestay operators along Sampaloc Lake.', 'Prof. Janice Aquino', 8, 'sample-8-barangay-homestay-hospitality-and-sanita.pdf', 'Homestay Standards Project Plan.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 19 DAY),
('Internal Audit Compliance and Public Procurement Governance in Local SUCs', 1, 'Evaluating adherence to RA 9184 standards, value-for-money audits, and internal controls.', 'Atty. Victor Ramos, CPA', 9, 'sample-9-internal-audit-compliance-and-public-pro.pdf', 'Internal Audit Compliance Proposal.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 9 DAY),
('Tax Literacy, Barangay Financial Bookkeeping and COA Audit Preparedness', 3, 'Outreach service training 40 barangay treasurers on automated cash disbursement registers.', 'Atty. Victor Ramos, CPA', 9, 'sample-9-tax-literacy-barangay-financial-bookkee.pdf', 'Barangay Tax Literacy Project.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 45 DAY),
('Terminal Accomplishment Report: Tax Literacy & Barangay Bookkeeping Program', 4, 'Comprehensive completion documentation and post-training financial audit scorecards.', 'Atty. Victor Ramos, CPA', 9, 'sample-9-terminal-accomplishment-report-tax-liter.pdf', 'Tax Literacy Terminal Report.pdf', 1, 'Approved', CURDATE(), NOW() - INTERVAL 4 DAY);

-- 4. Sample Research Projects (Different Workflow Stages)
INSERT INTO research_projects (title, researchers, abstract, funding_source, grant_amount, submitted_by, status, workflow_stage, created_at) VALUES
('AI-Driven Crop Disease Detection for Calabarzon Agrarian Farmers', 'Dr. Aris Thorne', 'Edge-computing mobile application using CNN to diagnose plant pathologies in coconut and rice crops.', 'Institutional Research Seed Fund', 150000.00, 1, 'Pending Executive Approval', 'Proposal', NOW() - INTERVAL 15 DAY),
('A Benchmark on Lightweight Cryptographic Ciphers for IoT Microgrids', 'Dr. Aris Thorne', 'Empirical analysis of AES-128 and ChaCha20 lightweight encryption primitives.', 'CHED Institutional Development Fund', 75000.00, 1, 'Approved', 'Completed', NOW() - INTERVAL 20 DAY),
('Solar-Powered Automated Rainwater Harvesting and Potability Monitoring Station', 'Engr. Katrina Valdez', 'Design of smart multiparameter sensors integrated with solar microcontrollers for flood-prone barangays.', 'DOST-SEI Grant-in-Aid', 220000.00, 1, 'Under Review', 'Proposal', NOW() - INTERVAL 12 DAY),
('Thermal Performance Evaluation of Bio-Composite Bricks Using Rice Husk Ash', 'Engr. Katrina Valdez', 'Compressive strength and thermal dissipation properties of eco-friendly masonry units.', 'University Creative Works Fund', 90000.00, 1, 'Approved', 'Completed', NOW() - INTERVAL 25 DAY),
('Pediatric Immunization Hesitancy Dynamics in Post-Pandemic Rural Households', 'Prof. Lorna Bautista', 'Cross-sectional epidemiologic study investigating predictors among rural Laguna mothers.', 'Department of Health Health Systems Fund', 125000.00, 1, 'Pending Coordinator Review', 'Proposal', NOW() - INTERVAL 8 DAY),
('Preservation of San Pablo Indigenous Folk Legends and Oral Literature', 'Dr. Gabriel Mendoza', 'Cultural and linguistic documentation of Seven Lakes folklore through dialectological mapping.', 'National Commission for Culture and the Arts (NCCA)', 80000.00, 1, 'Approved', 'Completed', NOW() - INTERVAL 18 DAY),
('Limnological Assessment and Biodiversity Indices of Lake Pandin and Lake Yambo', 'Dr. Gabriel Mendoza', 'Two-year limnological study examining microflora and dissolved oxygen variations.', 'OVPRE Research Grant', 130000.00, 1, 'Approved', 'Completed', NOW() - INTERVAL 28 DAY),
('Micro-Enterprise E-Commerce Adoption and Supply Chain Resiliency in Laguna', 'Prof. Raymond Cruz', 'Empirical survey examining financial technology uptake and logistics barriers among 250 MSMEs.', 'DTI-University Partnership Grant', 95000.00, 1, 'Pending Executive Approval', 'Proposal', NOW() - INTERVAL 14 DAY),
('High-Intensity Tactical Conditioning Protocol for Collegiate Athletes', 'Coach Daniel Flores', 'Comparative biomechanical analysis of periodized plyometrics on agility and aerobic capacity.', 'Institutional Sports Science Fund', 50000.00, 1, 'Under Review', 'Proposal', NOW() - INTERVAL 10 DAY),
('Adaptive Blended Learning Efficacy in Secondary Public School Mathematics', 'Dr. Maricel Delos Reyes', 'Action research evaluating gamified modular learning packets in secondary education.', 'DepEd-Higher Education Research Fellowship', 110000.00, 1, 'Approved', 'Completed', NOW() - INTERVAL 16 DAY),
('Agri-Tourism Destination Competitiveness and Heritage Culinary Mapping', 'Prof. Janice Aquino', 'Assessment of farm tourism destinations with economic projections for culinary heritage preservation.', 'DOT Tourism Development Fund', 75000.00, 1, 'Pending Coordinator Review', 'Proposal', NOW() - INTERVAL 11 DAY),
('Internal Audit Compliance and Public Procurement Governance in Local SUCs', 'Atty. Victor Ramos, CPA', 'Evaluating adherence to RA 9184 standards, value-for-money audits, and internal controls.', 'Institutional Governance Seed Fund', 100000.00, 1, 'Approved', 'Completed', NOW() - INTERVAL 9 DAY);

-- 5. Sample Extension Projects (Planning, Ongoing, Completed)
INSERT INTO extension_projects (project_title, coordinator, community_partner, budget, approved_budget, start_date, end_date, progress_status, status, workflow_stage, submitted_by, created_at) VALUES
('Digital Literacy and Cybersecurity Bootcamp for San Pablo Senior Citizens', 'Dr. Aris Thorne', 'Office of Senior Citizens Affairs (OSCA) San Pablo', 65000.00, 65000.00, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 4 MONTH), 'Ongoing', 'Approved', 'Proposal', 1, NOW() - INTERVAL 30 DAY),
('Community Water Filtration System Installation in Barangay San Lucas', 'Engr. Katrina Valdez', 'Barangay Council of San Lucas II', 110000.00, 110000.00, DATE_SUB(CURDATE(), INTERVAL 5 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'Completed', 'Approved', 'Proposal', 1, NOW() - INTERVAL 40 DAY),
('Oplan Kalusugan: Barangay Maternal and Neonatal Care Mentorship', 'Prof. Lorna Bautista', 'City Health Office - San Pablo', 85000.00, 85000.00, DATE_SUB(CURDATE(), INTERVAL 1 MONTH), DATE_ADD(CURDATE(), INTERVAL 5 MONTH), 'Ongoing', 'Approved', 'Proposal', 1, NOW() - INTERVAL 35 DAY),
('MSME Financial Management and Digital Bookkeeping Mentorship Clinic', 'Prof. Raymond Cruz', 'San Pablo City Chamber of Commerce', 70000.00, 70000.00, DATE_SUB(CURDATE(), INTERVAL 3 MONTH), DATE_ADD(CURDATE(), INTERVAL 3 MONTH), 'Ongoing', 'Approved', 'Proposal', 1, NOW() - INTERVAL 22 DAY),
('Community Grassroots Sports Clinic and Youth Wellness Initiative', 'Coach Daniel Flores', 'Sangguniang Kabataan Federation', 45000.00, 45000.00, DATE_SUB(CURDATE(), INTERVAL 4 MONTH), DATE_SUB(CURDATE(), INTERVAL 1 MONTH), 'Completed', 'Approved', 'Proposal', 1, NOW() - INTERVAL 32 DAY),
('Barangay Homestay Hospitality and Sanitation Standards Workshop', 'Prof. Janice Aquino', 'City Tourism Office - San Pablo', 55000.00, 55000.00, DATE_SUB(CURDATE(), INTERVAL 2 MONTH), DATE_ADD(CURDATE(), INTERVAL 2 MONTH), 'Ongoing', 'Approved', 'Proposal', 1, NOW() - INTERVAL 19 DAY),
('Tax Literacy, Barangay Financial Bookkeeping and COA Audit Preparedness', 'Atty. Victor Ramos, CPA', 'Liga ng mga Barangay - San Pablo Chapter', 90000.00, 90000.00, DATE_SUB(CURDATE(), INTERVAL 6 MONTH), DATE_SUB(CURDATE(), INTERVAL 2 MONTH), 'Completed', 'Approved', 'Proposal', 1, NOW() - INTERVAL 45 DAY);

-- 6. Sample Announcements
INSERT INTO announcements (title, content, audience, status, posted_by, created_at) VALUES
('Call for Institutional Research & Creative Works Proposals (CY 2026-2027)', 'The Office of the Vice President for Research and Extension announces the open call for faculty institutional research grants. Proposals qualifying under priority university niches (AI, Ecotourism, Sustainable Infrastructure, Public Health) may receive up to ₱250,000 seed funding. Submit via I-Record portal before the deadline.', 'All', 'Published', 1, NOW()),
('FY 2026 2nd Quarter Community Extension Review & Monitoring', 'All College Extension Coordinators and Faculty Extensionists are advised to submit mid-year progress documentations and beneficiary verification forms for ongoing barangay projects by next month.', 'Coordinators', 'Published', 1, NOW() - INTERVAL 2 DAY),
('OVPRE Research Colloquium & Best Paper Presentation', 'Registration is now open for the Annual University Research Colloquium. Outstanding faculty manuscripts and completed extension impact reports will be featured with institutional publication awards.', 'Faculty', 'Published', 1, NOW() - INTERVAL 4 DAY);

-- 7. Sample University Calendar Activities
INSERT INTO activities (title, activity_type, date, venue, description, approval_status, created_by, created_at) VALUES
('Annual University Research Colloquium 2026', 'Research Colloquium', DATE_ADD(CURDATE(), INTERVAL 14 DAY), 'University Gymnasium & Performing Arts Auditorium', 'Multi-college academic colloquium and peer review sessions for approved faculty and graduate research studies.', 'Approved', 1, NOW()),
('Grant Proposal Writing Workshop for Early-Career Faculty', 'Workshop', DATE_ADD(CURDATE(), INTERVAL 7 DAY), 'Audio-Visual Room (AVR), Main Building', 'Capacity building on crafting CHED- and DOST-compliant funding proposals with RIUH evaluation scoring benchmarks.', 'Approved', 1, NOW()),
('Barangay Health & Livelihood Community Outreach Caravan', 'Community Outreach', DATE_ADD(CURDATE(), INTERVAL 21 DAY), 'Barangay San Lucas Covered Court', 'Inter-college extension mission combining medical screening, digital literacy tutorials, and micro-business counseling.', 'Approved', 1, NOW());
