# IEC NERTP - Role-Based Access Control (RBAC) Flow

## Overview of Roles

The system has **7 primary roles** plus **public users**:

1. **Polling Station Officer** - Submits results from polling stations
2. **Ward Approver** - Reviews and certifies ward-level results
3. **Constituency Approver** - Reviews and certifies constituency results
4. **Administrative Area Approver** - Reviews and certifies admin area results
5. **IEC Chairman** - Final national certification
6. **IEC Administrator** - System configuration and user management
7. **Political Party Representative** - Accepts or disputes results
8. **Election Monitor** - Submits observations
9. **Public User** - Views certified results (no authentication)

---

## RBAC Permission Matrix

```mermaid
flowchart TB
    Start([User Login]) --> Auth{Authenticated?}
    Auth -->|No| Public[Public User Role]
    Auth -->|Yes| CheckRole{Identify Role}
    
    CheckRole -->|Polling Officer| PO[Polling Station Officer]
    CheckRole -->|Ward Approver| WA[Ward Approver]
    CheckRole -->|Constituency Approver| CA[Constituency Approver]
    CheckRole -->|Admin Area Approver| AA[Admin Area Approver]
    CheckRole -->|IEC Chairman| Chair[IEC Chairman]
    CheckRole -->|IEC Admin| Admin[IEC Administrator]
    CheckRole -->|Party Rep| PR[Party Representative]
    CheckRole -->|Monitor| Mon[Election Monitor]
    
    PO --> POPerms[Can:<br/>- Submit Results<br/>- Upload Photos<br/>- View Own Submissions<br/>- Edit Pending Results]
    
    WA --> WAPerms[Can:<br/>- View Ward Results<br/>- Approve Ward Results<br/>- Reject Ward Results<br/>- View Approval Queue<br/>- Add Comments]
    
    CA --> CAPerms[Can:<br/>- View Constituency Results<br/>- Approve Constituency Results<br/>- Reject Constituency Results<br/>- View Ward Breakdowns<br/>- Generate Reports]
    
    AA --> AAPerms[Can:<br/>- View Admin Area Results<br/>- Approve Admin Area Results<br/>- Reject Admin Area Results<br/>- View Constituency Breakdowns<br/>- Analytics Access]
    
    Chair --> ChairPerms[Can:<br/>- National Certification<br/>- View All Results<br/>- Final Approval Authority<br/>- Override Rejections<br/>- Full Analytics]
    
    Admin --> AdminPerms[Can:<br/>- Create Elections<br/>- Manage Users<br/>- Assign Roles<br/>- Configure Workflows<br/>- System Settings<br/>- Audit Log Access]
    
    PR --> PRPerms[Can:<br/>- View Assigned Stations<br/>- Accept Results<br/>- Reject Results<br/>- Add Comments<br/>- View Party Dashboard]
    
    Mon --> MonPerms[Can:<br/>- View Assigned Stations<br/>- Submit Observations<br/>- View Results<br/>- Export Reports]
    
    Public --> PublicPerms[Can:<br/>- View Certified Results<br/>- View Map<br/>- Filter by Location<br/>- View Party Acceptance]
```

---

## Role Access Hierarchy

```mermaid
graph TD
    subgraph Certification_Chain[Certification Chain - Hierarchical Access]
        PO[Polling Station Officer<br/>Level 1]
        WA[Ward Approver<br/>Level 2]
        CA[Constituency Approver<br/>Level 3]
        AA[Admin Area Approver<br/>Level 4]
        Chair[IEC Chairman<br/>Level 5]
    end
    
    subgraph Admin_Chain[Administrative Chain]
        Admin[IEC Administrator<br/>Full System Access]
    end
    
    subgraph Observer_Chain[Observer Chain - Read Only]
        PR[Party Representative<br/>Limited Read + Accept/Reject]
        Mon[Election Monitor<br/>Read + Observations]
        Public[Public User<br/>Certified Results Only]
    end
    
    PO -->|Submits to| WA
    WA -->|Certifies to| CA
    CA -->|Certifies to| AA
    AA -->|Certifies to| Chair
    
    Admin -.Manages.-> PO
    Admin -.Manages.-> WA
    Admin -.Manages.-> CA
    Admin -.Manages.-> AA
    Admin -.Manages.-> Chair
    Admin -.Manages.-> PR
    Admin -.Manages.-> Mon
    
    PO -.Visible to.-> PR
    PO -.Visible to.-> Mon
    Chair -.Publishes to.-> Public
    
    style PO fill:#e3f2fd
    style WA fill:#bbdefb
    style CA fill:#90caf9
    style AA fill:#64b5f6
    style Chair fill:#2196f3
    style Admin fill:#ff9800
    style PR fill:#81c784
    style Mon fill:#aed581
    style Public fill:#e0e0e0
```

---

## Detailed Permission Flow by Role

### 1. Polling Station Officer Flow

```mermaid
flowchart TD
    Login[Login with 2FA] --> GPS{GPS Valid?}
    GPS -->|No| GPSError[Access Denied:<br/>Not at Polling Station]
    GPS -->|Yes| Dashboard[Officer Dashboard]
    
    Dashboard --> Actions{Select Action}
    
    Actions -->|Submit| CanSubmit{Permission Check}
    Actions -->|View| CanView{Permission Check}
    Actions -->|Edit| CanEdit{Permission Check}
    
    CanSubmit -->|Has Permission| Submit[Submit Result Form]
    CanSubmit -->|No Permission| Denied1[Access Denied]
    
    CanView -->|Has Permission| View[View Own Submissions]
    CanView -->|No Permission| Denied2[Access Denied]
    
    CanEdit -->|Has Permission & Status=Pending| Edit[Edit Pending Result]
    CanEdit -->|No Permission or Certified| Denied3[Access Denied]
    
    Submit --> Upload[Upload Photos]
    Upload --> Review[Review Data]
    Review --> SubmitAPI[Submit to API]
    SubmitAPI --> Audit[Log in Audit Trail]
```

### 2. Ward Approver Flow

```mermaid
flowchart TD
    Login[Login] --> Dashboard[Ward Dashboard]
    Dashboard --> Queue[View Approval Queue]
    Queue --> Filter{Filter Options}
    
    Filter -->|Pending| Pending[Show Pending Results]
    Filter -->|Approved| Approved[Show Approved Results]
    Filter -->|All| All[Show All Results]
    
    Pending --> Select[Select Result to Review]
    Select --> Check{Permission Check}
    
    Check -->|Has Permission| Review[Review Result Details]
    Check -->|No Permission| Denied[Access Denied]
    
    Review --> ViewData[View Vote Counts<br/>View Photos<br/>View Party Status]
    ViewData --> Decision{Approve or Reject?}
    
    Decision -->|Approve| Approve[Certify at Ward Level]
    Decision -->|Reject| Reject[Add Rejection Reason]
    
    Approve --> UpdateStatus[Status: Ward Certified]
    Reject --> ReturnPS[Return to Polling Station]
    
    UpdateStatus --> Promote[Auto-Promote to<br/>Constituency Queue]
    ReturnPS --> NotifyOfficer[Notify Polling Officer]
    
    Promote --> AuditLog[Log Certification]
    NotifyOfficer --> AuditLog
```

### 3. IEC Administrator Flow

```mermaid
flowchart TD
    Login[Administrator Login] --> Dashboard[Admin Dashboard]
    Dashboard --> Actions{Select Action}
    
    Actions -->|Users| UserMgmt[User Management]
    Actions -->|Elections| ElectionMgmt[Election Management]
    Actions -->|Roles| RoleMgmt[Role Management]
    Actions -->|System| SystemMgmt[System Settings]
    Actions -->|Audit| AuditView[Audit Logs]
    
    UserMgmt --> UserActions{User Actions}
    UserActions -->|Create| CreateUser[Create New User]
    UserActions -->|Edit| EditUser[Edit User Details]
    UserActions -->|Assign Role| AssignRole[Assign/Change Role]
    UserActions -->|Deactivate| DeactivateUser[Deactivate User]
    
    CreateUser --> SelectRole{Select Role}
    SelectRole -->|Officer| AssignOfficer[Assign to Polling Station]
    SelectRole -->|Approver| AssignArea[Assign to Ward/Constituency/Area]
    SelectRole -->|Party Rep| AssignParty[Assign to Party & Stations]
    SelectRole -->|Monitor| AssignMonitor[Assign to Stations]
    
    ElectionMgmt --> ElectionActions{Election Actions}
    ElectionActions -->|Create| CreateElection[Create New Election]
    ElectionActions -->|Configure| ConfigHierarchy[Configure Hierarchy]
    ElectionActions -->|Workflow| SetupWorkflow[Setup Workflow]
    ElectionActions -->|Register| RegisterParties[Register Parties/Candidates]
    
    RoleMgmt --> RoleActions{Role Actions}
    RoleActions -->|Permissions| ManagePermissions[Manage Permissions]
    RoleActions -->|Create Custom| CreateRole[Create Custom Role]
    
    SystemMgmt --> SysActions{System Actions}
    SysActions -->|Config| SystemConfig[System Configuration]
    SysActions -->|Backup| BackupSettings[Backup Settings]
    SysActions -->|Monitoring| MonitorSystem[System Monitoring]
    
    AuditView --> AuditFilter{Filter Audit Logs}
    AuditFilter -->|By User| UserAudit[View User Actions]
    AuditFilter -->|By Action| ActionAudit[View Specific Actions]
    AuditFilter -->|By Date| DateAudit[View Date Range]
```

### 4. Political Party Representative Flow

```mermaid
flowchart TD
    Login[Party Rep Login] --> Check{Check Assignment}
    Check -->|Has Stations| Dashboard[Party Dashboard]
    Check -->|No Stations| NoAccess[No Polling Stations Assigned]
    
    Dashboard --> ViewStations[View Assigned Stations]
    ViewStations --> Filter{Filter}
    
    Filter -->|Pending| Pending[Pending Acceptance]
    Filter -->|Accepted| Accepted[Already Accepted]
    Filter -->|Rejected| Rejected[Already Rejected]
    
    Pending --> SelectStation[Select Station]
    SelectStation --> PermCheck{Permission Check}
    
    PermCheck -->|Authorized| ViewResult[View Result Details]
    PermCheck -->|Not Authorized| Denied[Access Denied]
    
    ViewResult --> ReviewData[Review:<br/>- Vote Counts<br/>- Result Sheet Photo<br/>- Turnout Data]
    
    ReviewData --> Decision{Decision}
    Decision -->|Accept| Accept[Submit Acceptance]
    Decision -->|Accept with Reservation| Reserve[Add Reservation Comment]
    Decision -->|Reject| RejectRes[Add Rejection Reason]
    
    Accept --> UpdateStatus1[Status: Accepted]
    Reserve --> UpdateStatus2[Status: Accepted w/ Reservation]
    RejectRes --> UpdateStatus3[Status: Rejected]
    
    UpdateStatus1 --> PublicView[Visible to Public]
    UpdateStatus2 --> PublicView
    UpdateStatus3 --> PublicView
    
    PublicView --> AuditLog[Log in Audit Trail]
```

---

## Permission Gates & Middleware

```mermaid
flowchart LR
    Request[HTTP Request] --> AuthMiddleware{Authenticated?}
    AuthMiddleware -->|No| Unauthorized[401 Unauthorized]
    AuthMiddleware -->|Yes| RoleMiddleware{Has Required Role?}
    
    RoleMiddleware -->|No| Forbidden[403 Forbidden]
    RoleMiddleware -->|Yes| PermissionGate{Has Permission?}
    
    PermissionGate -->|No| Forbidden2[403 Forbidden]
    PermissionGate -->|Yes| ContextCheck{Context Valid?}
    
    ContextCheck -->|GPS Invalid| GPSError[403 GPS Validation Failed]
    ContextCheck -->|Not Assigned| NotAssigned[403 Not Assigned to Resource]
    ContextCheck -->|Status Invalid| StatusError[403 Invalid Status for Action]
    ContextCheck -->|Valid| Allowed[Request Allowed]
    
    Allowed --> Controller[Controller Action]
    Controller --> AuditLog[Log Action in Audit Trail]
```

---

## Data Access Control

```mermaid
flowchart TB
    subgraph DataAccess[Data Access by Role]
        subgraph FullAccess[Full Access]
            FA1[IEC Chairman<br/>All Elections, All Levels]
            FA2[IEC Administrator<br/>All System Data]
        end
        
        subgraph HierarchicalAccess[Hierarchical Access]
            HA1[Admin Area Approver<br/>Own Area + Below]
            HA2[Constituency Approver<br/>Own Constituency + Below]
            HA3[Ward Approver<br/>Own Ward + Below]
        end
        
        subgraph LimitedAccess[Limited Access]
            LA1[Polling Officer<br/>Own Station Only]
            LA2[Party Rep<br/>Assigned Stations Only]
            LA3[Monitor<br/>Assigned Stations Only]
        end
        
        subgraph PublicAccess[Public Access]
            PA1[Public User<br/>Certified Results Only]
        end
    end
    
    style FullAccess fill:#f44336
    style HierarchicalAccess fill:#ff9800
    style LimitedAccess fill:#4caf50
    style PublicAccess fill:#9e9e9e
```

---

## Permission Implementation (Laravel)

### Role Seeder Structure

```php
// database/seeders/RoleSeeder.php

$roles = [
    'polling-officer' => [
        'submit-result',
        'view-own-result',
        'edit-pending-result',
        'upload-photo',
    ],
    
    'ward-approver' => [
        'view-ward-results',
        'approve-ward-result',
        'reject-ward-result',
        'view-ward-queue',
        'add-certification-comment',
    ],
    
    'constituency-approver' => [
        'view-constituency-results',
        'approve-constituency-result',
        'reject-constituency-result',
        'view-constituency-queue',
        'view-ward-breakdowns',
        'generate-constituency-report',
    ],
    
    'admin-area-approver' => [
        'view-admin-area-results',
        'approve-admin-area-result',
        'reject-admin-area-result',
        'view-admin-area-queue',
        'view-constituency-breakdowns',
        'access-analytics',
    ],
    
    'iec-chairman' => [
        'national-certification',
        'view-all-results',
        'override-rejection',
        'final-approval',
        'access-full-analytics',
        'publish-results',
    ],
    
    'iec-administrator' => [
        'create-election',
        'manage-users',
        'assign-roles',
        'configure-workflow',
        'system-settings',
        'view-audit-logs',
        'manage-polling-stations',
        'register-parties',
    ],
    
    'party-representative' => [
        'view-assigned-stations',
        'accept-result',
        'reject-result',
        'add-acceptance-comment',
        'view-party-dashboard',
    ],
    
    'election-monitor' => [
        'view-assigned-stations',
        'submit-observation',
        'view-observation-history',
        'export-observations',
    ],
];
```

---

## Middleware Chain Example

```mermaid
sequenceDiagram
    participant User
    participant Auth
    participant Role
    participant Permission
    participant GPS
    participant Assignment
    participant Controller
    
    User->>Auth: Request /api/results/submit
    Auth->>Auth: Verify JWT Token
    
    alt Not Authenticated
        Auth-->>User: 401 Unauthorized
    else Authenticated
        Auth->>Role: Check User Role
        
        alt Invalid Role
            Role-->>User: 403 Forbidden (Wrong Role)
        else Valid Role
            Role->>Permission: Check "submit-result" Permission
            
            alt No Permission
                Permission-->>User: 403 Forbidden (No Permission)
            else Has Permission
                Permission->>GPS: Validate GPS Location
                
                alt GPS Invalid
                    GPS-->>User: 403 GPS Validation Failed
                else GPS Valid
                    GPS->>Assignment: Check Station Assignment
                    
                    alt Not Assigned
                        Assignment-->>User: 403 Not Assigned
                    else Assigned
                        Assignment->>Controller: Allow Request
                        Controller-->>User: 200 Success
                    end
                end
            end
        end
    end
```

---

## Role-Based UI Rendering

```mermaid
flowchart TD
    LoadApp[Load Application] --> CheckAuth{User Authenticated?}
    CheckAuth -->|No| PublicUI[Render Public Interface]
    CheckAuth -->|Yes| GetRole[Get User Role]
    
    GetRole --> RenderUI{Render Role-Specific UI}
    
    RenderUI -->|Polling Officer| POUI[Render:<br/>- Result Entry Form<br/>- My Submissions<br/>- Help Guide]
    
    RenderUI -->|Ward Approver| WAUI[Render:<br/>- Approval Queue<br/>- Ward Dashboard<br/>- Analytics]
    
    RenderUI -->|Constituency Approver| CAUI[Render:<br/>- Constituency Queue<br/>- Constituency Dashboard<br/>- Advanced Analytics]
    
    RenderUI -->|Admin Area Approver| AAUI[Render:<br/>- Admin Area Queue<br/>- Area Dashboard<br/>- Full Analytics]
    
    RenderUI -->|IEC Chairman| ChairUI[Render:<br/>- National Dashboard<br/>- All Queues<br/>- Full System Access]
    
    RenderUI -->|IEC Administrator| AdminUI[Render:<br/>- Admin Panel<br/>- User Management<br/>- System Settings<br/>- Audit Logs]
    
    RenderUI -->|Party Rep| PRUI[Render:<br/>- Assigned Stations<br/>- Acceptance Interface<br/>- Party Dashboard]
    
    RenderUI -->|Monitor| MonUI[Render:<br/>- Assigned Stations<br/>- Observation Form<br/>- Reports]
    
    PublicUI --> Components[Show Only:<br/>- Public Results<br/>- Map View<br/>- Filters]
```

---

## Security Rules Summary

| Role | Authentication | 2FA Required | Device Binding | GPS Validation | Data Scope |
|------|----------------|--------------|----------------|----------------|------------|
| **Polling Officer** | âœ… | âœ… | âœ… | âœ… | Own station only |
| **Ward Approver** | âœ… | âœ… | âœ… | âŒ | Own ward + below |
| **Constituency Approver** | âœ… | âœ… | âœ… | âŒ | Own constituency + below |
| **Admin Area Approver** | âœ… | âœ… | âœ… | âŒ | Own admin area + below |
| **IEC Chairman** | âœ… | âœ… | âœ… | âŒ | All data |
| **IEC Administrator** | âœ… | âœ… | âœ… | âŒ | All system data |
| **Party Representative** | âœ… | âœ… | âŒ | âŒ | Assigned stations only |
| **Election Monitor** | âœ… | âœ… | âŒ | âŒ | Assigned stations only |
| **Public User** | âŒ | âŒ | âŒ | âŒ | Certified results only |

---

## Audit Trail for RBAC Actions

```mermaid
flowchart LR
    Action[User Action] --> Intercept[RBAC Interceptor]
    Intercept --> Log{Audit Log}
    
    Log --> LogUser[Record User ID]
    Log --> LogRole[Record User Role]
    Log --> LogPermission[Record Permission Used]
    Log --> LogResource[Record Resource Accessed]
    Log --> LogResult[Record Action Result]
    Log --> LogTimestamp[Record Timestamp]
    Log --> LogIP[Record IP Address]
    Log --> LogDevice[Record Device Info]
    
    LogUser --> Store[(Immutable Audit Database)]
    LogRole --> Store
    LogPermission --> Store
    LogResource --> Store
    LogResult --> Store
    LogTimestamp --> Store
    LogIP --> Store
    LogDevice --> Store
```