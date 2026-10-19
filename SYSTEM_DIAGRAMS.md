# OVPRE System Diagrams (Mermaid)

> **How to View & Edit in VS Code:**
> 1. Open this file in VS Code.
> 2. Press `Ctrl + Shift + V` (or press `Ctrl + K, V` to open the preview side-by-side).
> 3. All diagrams below are 100% Mermaid syntax-compliant and render automatically in real time.
> 4. You can directly edit any text, labels, or connections in this file to update the diagrams.

---

## 1. System Architecture Block Diagram

```mermaid
graph TB
    %% User Layer
    subgraph Users ["User and Stakeholder Layer"]
        R["Faculty Researchers"]
        FE["Faculty Extensionists"]
        RC["College Research Coordinators"]
        EC["College Extension Coordinators"]
        RD["Research Directors"]
        ED["Extension Directors"]
        ADM["OVPRE Admin / Super Admin / VP"]
    end

    %% Presentation Layer
    subgraph Presentation ["Presentation Layer (Client-Side)"]
        Landing["Landing and Public Portal (index.php, landing.php)"]
        AuthUI["Auth Pages (login, register, forgot-password, OTP)"]
        RolePortalUI["Role-Based Portals (role-portal/pages/*.php)"]
        AdminUI["Admin Consoles (users, reports, audit-logs)"]
    end

    %% Application Layer
    subgraph AppServer ["Application Layer (Apache + PHP 8 Engine)"]
        Router["Session and Access Control Guard (includes/bootstrap.php)"]
        AuthMod["Authentication and RBAC Engine"]
        ResearchMod["Research Proposal and Progress Tracker"]
        ExtensionMod["Extension Projects and Reports Tracker"]
        DocMod["Document and Version Management"]
        ReviewMod["Multi-Stage Review and Audit Engine"]
        NoticeMod["Notification and Announcement System"]
        BackupMod["Database Snapshot and Recovery Manager"]
    end

    %% External Services
    subgraph ExternalServices ["External Services"]
        SMTP["SMTP Mail Server (PHPMailer Alerts and OTP)"]
        Supabase["Supabase Cloud API (Optional Auth/Sync)"]
    end

    %% Persistence Layer
    subgraph DataTier ["Persistence and Storage Layer"]
        MySQL[("MySQL Database (ovpre)")]
        FileSystem[("Local Storage (uploads/)")]
    end

    %% Connections
    Users --> Presentation
    Presentation --> Router
    Router --> AuthMod
    Router --> ResearchMod
    Router --> ExtensionMod
    Router --> DocMod

    AuthMod --> MySQL
    AuthMod --> SMTP
    AuthMod -.-> Supabase

    ResearchMod --> MySQL
    ResearchMod --> FileSystem
    ResearchMod --> NoticeMod

    ExtensionMod --> MySQL
    ExtensionMod --> FileSystem
    ExtensionMod --> NoticeMod

    DocMod --> MySQL
    DocMod --> FileSystem

    ReviewMod --> MySQL
    NoticeMod --> MySQL
    BackupMod --> MySQL
```

---

## 2. System Administrator Flowchart (Super Admin & OVPRE Admin)

This flowchart details the operations, governance, user management, executive approvals, and system maintenance performed by administrators in `dashboard.php`, `admin-users.php`, `admin-pending-users.php`, and `system-backup.php`.

```mermaid
flowchart TD
    AdminLogin([Admin Logs In]) --> AdminHome["Access Main Administrative Dashboard (dashboard.php)"]
    
    AdminHome --> TaskSelect{"Select Administrative Operation"}

    %% 1. User Approval and Account Management
    TaskSelect -->|User Administration| UserManage["Open User Queue (admin-pending-users.php / admin-users.php)"]
    UserManage --> UserAction{"Action Type?"}
    UserAction -->|Pending Registration| ReviewAccount{"Screen Self-Registered User"}
    ReviewAccount -->|Reject| RejectUser["Set Status: Rejected (Record Reason and Send Email)"]
    ReviewAccount -->|Approve| ApproveUser["Set Status: Active (Set approved_by and approved_at)"]
    UserAction -->|Role Assignment| ModifyRole["Update User Roles, Department, or Permissions"]
    UserAction -->|Security Action| LockAction["Unlock Account or Reset User Credentials"]

    %% 2. Final Executive Approval
    TaskSelect -->|Executive Pipeline| ExecQueue["Review Proposals in 'Pending Executive Approval'"]
    ExecQueue --> ExecDecision{"Executive Decision"}
    ExecDecision -->|Decline| DeclineProp["Set Status: Declined (Record Final Remarks)"]
    ExecDecision -->|Grant Approval| ApproveProp["Set Status: Approved (Grant Released and Project Activated)"]
    DeclineProp --> NotifyProponent["Send In-App Notification and Email to Proponent"]
    ApproveProp --> NotifyProponent

    %% 3. Document Repository Governance
    TaskSelect -->|Repository Control| DocControl["Manage Documents (admin-documents.php)"]
    DocControl --> DocOps{"Repository Operation"}
    DocOps -->|Categorization| UpdateCategory["Add or Edit Document Categories"]
    DocOps -->|Archival| ArchiveDoc["Archive Stale Documents or Restore Past Records"]
    DocOps -->|Version Review| VersionLog["Inspect Document Version History and File Logs"]

    %% 4. System Maintenance & Security
    TaskSelect -->|System Health and Security| SysHealth["Open Security and Backups (system-backup.php)"]
    SysHealth --> HealthOps{"Maintenance Action"}
    HealthOps -->|Audit Logs| ViewAudit["Audit User Activities, IP Logs, and Timestamps"]
    HealthOps -->|Backup Snapshot| CreateBackup["Generate and Download Database JSON/SQL Snapshot"]
    HealthOps -->|Restore Database| RestoreBackup["Upload and Merge Database Snapshot Backup"]

    %% 5. Announcements and Activities
    TaskSelect -->|Broadcasts| BroadCast["Post Announcements or University Calendar Events"]
    BroadCast --> PublishNotice["Publish Targeted Notice (Directors, Faculty, or All)"]
```

---

## 3. Directors Flowchart (Research Director & Extension Director)

This flowchart details the institutional evaluation, RIUH scoring, grant budget recommendations, and project oversight performed by Directors in `research.php`, `extension.php`, and `analytics.php`.

```mermaid
flowchart TD
    DirLogin([Director Logs In]) --> DirDashboard["Access Director Portal Dashboard and Analytics"]
    
    DirDashboard --> SelectDirTrack{"Select Oversight Track"}

    %% Research Track
    SelectDirTrack -->|Research Track| ResearchDirectorQueue["Open Research Pipeline (research.php)"]
    ResearchDirectorQueue --> ReviewResProposal["Screen Proposals in 'Pending Director Review'"]
    ReviewResProposal --> ResEvaluation{"RIUH Evaluation and Review"}
    
    ResEvaluation -->|Needs Revisions| ResReturn["Action: director_return (Set Status: Needs Revision)"]
    ResReturn --> ResReturnNote["Add Specific Review Remarks and Required Corrections"]
    ResReturnNote --> NotifyResearcher["Notify Researcher via In-App Notification"]

    ResEvaluation -->|Qualifies for Institutional Funding| ResEndorse["Action: director_endorse"]
    ResEndorse --> ResScore["Input RIUH Evaluation Score and Recommended Grant Amount"]
    ResScore --> ResToExec["Set Status: Pending Executive Approval"]
    ResToExec --> NotifyVP1["Route Proposal to Vice President / Super Admin"]

    %% Extension Track
    SelectDirTrack -->|Extension Track| ExtensionDirectorQueue["Open Extension Pipeline (extension.php)"]
    ExtensionDirectorQueue --> ReviewExtProposal["Screen Extension Proposals in 'Pending Director Review'"]
    ReviewExtProposal --> ExtEvaluation{"Community Viability and Budget Screening"}

    ExtEvaluation -->|Needs Revisions| ExtReturn["Action: director_return (Set Status: Needs Revision)"]
    ExtReturn --> ExtReturnNote["Add Community / Budget Amendment Remarks"]
    ExtReturnNote --> NotifyExtensionist["Notify Faculty Extensionist"]

    ExtEvaluation -->|Endorse Project| ExtEndorse["Action: director_endorse"]
    ExtEndorse --> ExtToExec["Set Status: Pending Executive Approval"]
    ExtToExec --> NotifyVP2["Route Proposal to Vice President / Super Admin"]

    %% Post-Approval Oversight
    DirDashboard -->|Ongoing Projects| TrackProjects["Monitor Active Institutional Projects"]
    TrackProjects --> ReviewReports{"Review Milestone Reports"}
    ReviewReports -->|Research| InspectResearchMilestones["Evaluate Progress, Liquidation, and Publication Proof"]
    ReviewReports -->|Extension| InspectExtensionImpact["Evaluate Community Outreach and Accomplishment Reports"]
    InspectResearchMilestones --> DirArchive["Approve Final Output for University Archival"]
    InspectExtensionImpact --> DirArchive
```

---

## 4. Coordinators Flowchart (Research Coordinator & Extension Coordinator)

This flowchart details the college-level screening, proposal endorsement, and department monitoring conducted by College Coordinators in `dashboard.php`, `research.php`, and `extension.php`.

```mermaid
flowchart TD
    CoordLogin([Coordinator Logs In]) --> CoordHome["Access College Coordinator Dashboard"]
    
    CoordHome --> FilterCollege["Filter Submissions by Assigned College / Department"]
    FilterCollege --> SelectType{"Submission Type"}

    %% Research Coordinator Track
    SelectType -->|Research Proposals| ResCoordQueue["View Department Proposals (Pending Coordinator Review)"]
    ResCoordQueue --> ResScreening{"College Research Screening"}
    
    ResScreening -->|Does Not Meet Criteria| ResCoordReturn["Action: coordinator_return"]
    ResCoordReturn --> ResRemarks["Enter College Feedback and Revision Requirements"]
    ResRemarks --> ResSetRevision["Set Status: Needs Revision"]
    ResSetRevision --> NotifyFacultyRes["Send Notification to Faculty Proponent"]

    ResScreening -->|Aligns with Research Agenda| ResCoordEndorse["Action: coordinator_endorse"]
    ResCoordEndorse --> ResEndorseNote["Record Endorsement Remarks and College Clearance"]
    ResEndorseNote --> ResSetDirector["Set Status: Pending Director Review"]
    ResSetDirector --> NotifyResDirector["Notify University Research Director"]

    %% Extension Coordinator Track
    SelectType -->|Extension Proposals| ExtCoordQueue["View Department Proposals (Pending Coordinator Review)"]
    ExtCoordQueue --> ExtScreening{"College Extension Screening"}

    ExtScreening -->|Needs Revision| ExtCoordReturn["Action: coordinator_return"]
    ExtCoordReturn --> ExtRemarks["Enter Local Alignment and Partner Feedback"]
    ExtRemarks --> ExtSetRevision["Set Status: Needs Revision"]
    ExtSetRevision --> NotifyFacultyExt["Send Notification to Faculty Extensionist"]

    ExtScreening -->|Vetted and Endorsed| ExtCoordEndorse["Action: coordinator_endorse"]
    ExtCoordEndorse --> ExtEndorseNote["Record College Endorsement Clearance"]
    ExtEndorseNote --> ExtSetDirector["Set Status: Pending Director Review"]
    ExtSetDirector --> NotifyExtDirector["Notify University Extension Director"]

    %% Local Monitoring
    CoordHome -->|College Calendar| DepartmentActivities["Coordinate Department Colloquiums and Activities"]
    DepartmentActivities --> PostActivity["Schedule Department Defense, Workshop, or Colloquium"]
```

---

## 5. Faculty Proponents Flowchart (Faculty Researcher & Faculty Extensionist)

This flowchart details the proposal submission, real-time tracking, revision turnaround, and progress/accomplishment reporting completed by Faculty Proponents.

```mermaid
flowchart TD
    FacultyLogin([Faculty Logs In]) --> CheckProponentRole{"Proponent Role"}

    %% Researcher Workflow
    CheckProponentRole -->|Researcher| ResDashboard["Access Researcher Portal (role-portal/pages/research.php)"]
    ResDashboard --> ResActionSelect{"Action?"}

    ResActionSelect -->|New Submission| NewResearch["Fill Research Proposal Form"]
    NewResearch --> ResDetails["Enter Title, Co-Researchers, Abstract, and Funding Source"]
    ResDetails --> ResUpload["Upload Proposal Manuscript and Ethics Forms"]
    ResUpload --> ResSubmit["Submit Proposal (Status: Pending Coordinator Review)"]
    ResSubmit --> ResTrackStatus

    ResActionSelect -->|Track Progress| ResTrackStatus["Monitor Pipeline Status (status.php / notifications.php)"]
    ResTrackStatus --> ResCurrentStatus{"Current Status?"}
    
    ResCurrentStatus -->|Needs Revision| ResRevise["Review Coordinator or Director Remarks"]
    ResRevise --> ResResubmit["Upload Revised Manuscript and Notes (Action: resubmit_proposal)"]
    ResResubmit --> ResSubmit

    ResCurrentStatus -->|Approved| ResImplementation["Project Active (Workflow Stage: Implementation)"]
    ResImplementation --> ResMilestone["Submit Progress Reports (Milestone, Liquidation, Dissemination)"]
    ResMilestone --> ResCompletion["Submit Final Research Accomplishment Report"]
    ResCompletion --> ResDone([Research Completed])

    %% Faculty Extensionist Workflow
    CheckProponentRole -->|Faculty Extensionist| ExtDashboard["Access Extensionist Portal (role-portal/pages/extension.php)"]
    ExtDashboard --> ExtActionSelect{"Action?"}

    ExtActionSelect -->|New Proposal| NewExtension["Fill Extension Project Proposal Form"]
    NewExtension --> ExtDetails["Enter Title, Community Partner, Target Beneficiaries, Budget, Dates"]
    ExtDetails --> ExtUpload["Upload Project Brief and Partner MoA/MoU"]
    ExtUpload --> ExtSubmit["Submit Project (Status: Pending Coordinator Review, Planning)"]
    ExtSubmit --> ExtTrackStatus

    ExtActionSelect -->|Track Progress| ExtTrackStatus["Monitor Pipeline Status (status.php / notifications.php)"]
    ExtTrackStatus --> ExtCurrentStatus{"Current Status?"}

    ExtCurrentStatus -->|Needs Revision| ExtRevise["Review Screening Remarks"]
    ExtRevise --> ExtResubmit["Update Project Information and Resubmit (Action: resubmit_proposal)"]
    ExtResubmit --> ExtSubmit

    ExtCurrentStatus -->|Approved| ExtOngoing["Project Approved (Progress Status: Ongoing)"]
    ExtOngoing --> ExtCommunityWork["Execute Community Outreach and Extension Activities"]
    ExtCommunityWork --> ExtAccomplish["Submit Accomplishment Report and Impact Photos"]
    ExtAccomplish --> ExtDone([Extension Project Completed])
```

---

## 6. Research Proposal End-to-End Approval Pipeline

```mermaid
flowchart TD
    %% Proposal Submission
    Start([Researcher Submits Proposal]) --> UploadDoc["Upload Proposal Documents and Abstract"]
    UploadDoc --> Stage1["Status: Pending Coordinator Review"]
    
    %% Stage 1: Coordinator
    Stage1 --> CoordReview{"College Research Coordinator Screening"}
    
    CoordReview -->|Needs Revision| CoordRevise["Status: Needs Revision"]
    CoordRevise --> NoticeRes1["Notify Researcher with Remarks"]
    NoticeRes1 --> ResubmitRes["Researcher Resubmits Revised Proposal"]
    ResubmitRes --> Stage1

    CoordReview -->|Endorsed| Stage2["Status: Pending Director Review"]
    Stage2 --> NoticeDir["Notify Research Director"]

    %% Stage 2: Director Evaluation
    NoticeDir --> DirectorEval{"Research Director Evaluation and RIUH"}
    
    DirectorEval -->|Needs Revision| DirRevise["Status: Needs Revision"]
    DirRevise --> NoticeRes2["Notify Researcher with RIUH Remarks"]
    NoticeRes2 --> ResubmitRes

    DirectorEval -->|Endorsed| SetRIUH["Record RIUH Score and Recommended Grant Amount"]
    SetRIUH --> Stage3["Status: Pending Executive Approval"]
    Stage3 --> NoticeExec["Notify VP / Super Admin"]

    %% Stage 3: Executive Approval
    NoticeExec --> ExecDecision{"Vice President / Super Admin Review"}
    
    ExecDecision -->|Declined| DeclinedState["Status: Declined"]
    DeclinedState --> NoticeDeclined["Notify Proponent of Decision"]
    NoticeDeclined --> EndDeclined([Proposal Closed])

    ExecDecision -->|Approved| ApprovedState["Status: Approved"]
    ApprovedState --> NoticeApproved["Notify Proponent of Institutional Approval"]
    
    %% Post Approval Workflow
    ApprovedState --> ImplementationPhase["Workflow Stage: Implementation"]
    ImplementationPhase --> ProgressReports["Researcher Submits Milestone and Liquidation Reports"]
    ProgressReports --> CompletionReport["Submit Final Accomplishment Report"]
    CompletionReport --> ProjectArchived([Project Completed and Archived])
```

---

## 7. Extension Project End-to-End Approval Pipeline

```mermaid
flowchart TD
    StartExt([Faculty Extensionist Submits Project]) --> ExtInput["Enter Title, Partner, Location, Beneficiaries, Budget"]
    ExtInput --> ExtDoc["Upload Extension Proposal Document"]
    ExtDoc --> ExtStage1["Status: Pending Coordinator Review (Planning)"]

    ExtStage1 --> ExtCoord{"College Extension Coordinator Screening"}
    
    ExtCoord -->|Needs Amendments| ExtCoordRev["Status: Needs Revision"]
    ExtCoordRev --> ExtRevise["Extensionist Updates Details and Resubmits"]
    ExtRevise --> ExtStage1

    ExtCoord -->|Endorsed| ExtStage2["Status: Pending Director Review"]
    ExtStage2 --> ExtDirReview{"Extension Director Review and Community Vetting"}

    ExtDirReview -->|Needs Adjustment| ExtDirRev["Status: Needs Revision"]
    ExtDirRev --> ExtRevise

    ExtDirReview -->|Director Endorsed| ExtStage3["Status: Pending Executive Approval"]
    ExtStage3 --> ExtExec{"Vice President / Super Admin Approval"}

    ExtExec -->|Declined| ExtDeclined["Status: Declined"]
    ExtDeclined --> ExtEndClosed([Project Closed])

    ExtExec -->|Executive Approved| ExtApproved["Status: Approved (Ongoing)"]
    ExtApproved --> ExtNotify["Notify Coordinator and Extensionist"]

    ExtApproved --> CommunityWork["Community Outreach and Program Implementation"]
    CommunityWork --> SubmitAccomplish["Submit Accomplishment Report and Documentation"]
    SubmitAccomplish --> ExtCompleted["Status: Completed"]
    ExtCompleted --> ExtArchive([Archived in Accomplishment Repository])
```

---

## 8. User Registration, Authentication & RBAC Flowchart

```mermaid
flowchart TD
    UserVisit([User Visits Portal]) --> HasAccount{"Already have an account?"}
    
    %% Registration
    HasAccount -->|No| RegisterForm["Fill Registration Form (Role, Department, Email, Credentials)"]
    RegisterForm --> SelfRegCheck{"Is Role Self-Registerable?"}
    
    SelfRegCheck -->|Yes| CreatedPending["Insert into users (Status: Pending Approval)"]
    SelfRegCheck -->|No| InviteOnly["Requires Super Admin Invitation"]

    CreatedPending --> AdminReview{"Super Admin / OVPRE Admin Screening"}
    AdminReview -->|Reject| RegRejected["Status: Rejected (Email Sent to User)"]
    AdminReview -->|Approve| RegApproved["Status: Active (Access Granted)"]
    
    %% Login
    HasAccount -->|Yes| LoginForm["Enter Username and Password"]
    RegApproved --> LoginForm

    LoginForm --> CredCheck{"Validate Password Hash and Lock Status"}
    CredCheck -->|Failed 5 Times| AccountLocked["Lock Account for 15 Minutes (Log in login_history)"]
    CredCheck -->|Invalid Password| LoginError["Show Invalid Credentials Error"]

    CredCheck -->|Valid Credentials| CheckStatus{"Check Account Status"}
    CheckStatus -->|Pending Approval| NotApprovedNotice["Show Notice: Awaiting Admin Approval"]
    CheckStatus -->|Inactive| InactiveNotice["Show Notice: Account Deactivated"]

    CheckStatus -->|Active| CheckOTP{"Is 2FA / OTP Required?"}
    CheckOTP -->|Yes| SendOTP["Send OTP Code via SMTP Mail"]
    SendOTP --> VerifyOTP{"Enter 6-Digit Code"}
    VerifyOTP -->|Invalid or Expired| LoginError
    VerifyOTP -->|Valid Code| CreateSession["Initialize PHP Session and Set Secure Cookie"]

    CheckOTP -->|No| CreateSession

    %% Portal Routing
    CreateSession --> RouteByRole{"Route by User Role"}
    RouteByRole -->|Researcher| PortalResearcher["role-portal/pages/research.php"]
    RouteByRole -->|Faculty Extensionist| PortalExtension["role-portal/pages/extension.php"]
    RouteByRole -->|Coordinators| PortalCoord["role-portal/pages/dashboard.php"]
    RouteByRole -->|Directors| PortalDirector["role-portal/pages/analytics.php"]
    RouteByRole -->|Super Admin or OVPRE Admin| AdminConsole["dashboard.php (Main Admin Console)"]
```

---

## 9. Database Entity-Relationship (ER) Diagram

```mermaid
erDiagram
    DEPARTMENTS ||--o{ USERS : "has members"
    DEPARTMENTS ||--o{ DOCUMENTS : "owns"
    CATEGORIES ||--o{ DOCUMENTS : "classifies"
    
    USERS ||--o{ DOCUMENTS : "uploads"
    USERS ||--o{ DOCUMENT_VERSIONS : "modifies"
    USERS ||--o{ RESEARCH_PROJECTS : "submits"
    USERS ||--o{ EXTENSION_PROJECTS : "coordinates"
    USERS ||--o{ ACCOMPLISHMENT_REPORTS : "submits"
    USERS ||--o{ ANNOUNCEMENTS : "posts"
    USERS ||--o{ NOTIFICATIONS : "receives"
    USERS ||--o{ AUDIT_LOGS : "generates"
    USERS ||--o{ REVIEW_LOGS : "logs"

    DOCUMENTS ||--o{ DOCUMENT_VERSIONS : "versions"
    DOCUMENTS ||--o{ ARCHIVED_DOCUMENTS : "archives"
    DOCUMENTS ||--o{ RESEARCH_PROJECTS : "attaches"
    DOCUMENTS ||--o{ EXTENSION_PROJECTS : "attaches"
    DOCUMENTS ||--o{ ACCOMPLISHMENT_REPORTS : "attaches"

    USERS {
        int id PK
        string fullname
        string email
        string username
        string password
        string role
        int department_id FK
        string status
        datetime approved_at
        int approved_by FK
    }

    DOCUMENTS {
        int id PK
        string title
        int category_id FK
        int department_id FK
        string filename
        int uploaded_by FK
        string status
        date submission_date
    }

    RESEARCH_PROJECTS {
        int id PK
        string title
        string researchers
        string abstract
        string funding_source
        int document_id FK
        int submitted_by FK
        string status
        int coordinator_id FK
        int director_id FK
        int executive_id FK
        float aihr_score
        float grant_amount
    }

    EXTENSION_PROJECTS {
        int id PK
        string project_title
        string coordinator
        string community_partner
        float budget
        date start_date
        date end_date
        string progress_status
        string status
        int document_id FK
        int submitted_by FK
    }

    NOTIFICATIONS {
        int id PK
        int user_id FK
        string message
        string link
        string status
        datetime created_at
    }

    AUDIT_LOGS {
        int id PK
        int user_id FK
        string action
        string ip_address
        datetime created_at
    }

    REVIEW_LOGS {
        int id PK
        string entity_type
        int entity_id
        int user_id FK
        string action
        string remarks
        datetime created_at
    }
```
