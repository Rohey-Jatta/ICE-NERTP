# Phase 6: Access Control & Audit Logging

## Overview
Comprehensive access control verification and audit logging expansion for the Election Monitor module, ensuring secure data access and complete activity tracking.

## Access Control Implementation

### Route-Level Protection
All monitor routes are protected with authentication and role-based middleware:

```php
Route::middleware(['auth', 'role:election-monitor'])
    ->prefix('monitor')
    ->name('monitor.')
    ->group(function () { ... });
```

#### Key Authentication Checks:
1. **User Authentication**: `auth` middleware ensures user is logged in
2. **Role Authorization**: `role:election-monitor` ensures user has monitor role
3. **Monitor Association**: All endpoints verify user has associated active election monitor record

### Endpoint-Level Access Control

#### Dashboard (`/monitor/dashboard`)
- **Requirement**: Active monitor record for current user
- **Data Exposure**: Only shows data for current monitor's assigned stations
- **Query Filter**: `ElectionMonitor::where('user_id', Auth::user()->id)->where('is_active', true)`

#### Observations (`/monitor/observations`)
- **Requirement**: Permission `view-observations` + active monitor
- **Data Exposure**: Only monitor's own observations visible
- **Query Filter**: `DB::table('monitor_observations')->where('election_monitor_id', $monitor->id)`
- **Verification**: Explicit monitor ID check prevents data leakage

#### Observation Submission (`POST /monitor/observations`)
- **Requirement**: Permission `create-observations` + active monitor
- **Validation**: Polling station must be assigned to monitor
- **Station Check**: `$monitor->pollingStations()->findOrFail($request->polling_station_id)`

#### PDF Download - Single (`/monitor/observations/pdf/{id}`)
- **Requirement**: Permission `export-observations` + active monitor
- **Access Check**: Verify observation belongs to current monitor
- **Query**: `MonitorObservation::where('election_monitor_id', $monitor->id)->findOrFail($id)`

#### PDF Download - Batch (`/monitor/observations/pdf/batch`)
- **Requirement**: Permission `export-observations` + active monitor
- **Filtering**: Only exports observations belonging to current monitor
- **Query**: `DB::table('monitor_observations')->where('election_monitor_id', $monitor->id)`

#### Document Download (`POST /monitor/observations/{id}/download-document`)
- **Requirement**: Permission `view-observations` + active monitor
- **Multi-Layer Verification**:
  1. Observation exists and belongs to current monitor
  2. Document path exists in observation's documents_paths JSON
  3. File exists on disk at specified path
- **Protection**: Prevents path traversal attacks via JSON-based path validation

#### Results (`/monitor/results`)
- **Requirement**: Permission `view-assigned-stations` + active monitor
- **Data Exposure**: Only results for assigned polling stations
- **Query**: Results filtered by `Station IDs in Monitor's assignment`

### Data Isolation Strategy

1. **Monitor-Level Isolation**: All data queries filtered by `election_monitor_id`
2. **Station Assignment Validation**: Can only access results for assigned stations
3. **Path Traversal Prevention**: Document paths validated against stored JSON metadata
4. **File System Access Control**: Using Laravel Storage facade with public disk restrictions

---

## Audit Logging Implementation

### Audit Log Schema
Each audit entry records:
- `action`: Unique action identifier (e.g., `monitor.observation.submitted`)
- `event`: Event type (created, updated, deleted, downloaded, exported, etc.)
- `module`: Related module (ElectionMonitor)
- `user_id`: User performing action
- `ip_address`: IP address of request
- `user_agent`: Browser/client information
- `extra`: JSON field with action-specific metadata
- `created_at`: Timestamp

### Monitored Activities

#### Observation Management
1. **Submission** (`monitor.observation.submitted`)
   - When: New observation created
   - Data: observation_id, polling_station_id, type, severity, election_id, photo_count, document_count, coordinates
   
2. **Document Upload** (included in submission)
   - When: Documents attached to observation
   - Data: document_count, document_names, file_sizes, mime_types

3. **Status Change** (`monitor.observation.status-changed`) [if applicable]
   - When: Observation status updated by reviewer
   - Data: old_status, new_status, reason

#### Document Access
1. **Individual Download** (`monitor.observation.document-downloaded`)
   - When: Monitor downloads supporting document
   - Data: observation_id, document_name, document_size, document_type
   - Access Level: High - tracks specific document access

2. **Batch Document Access** (included in batch PDF)
   - When: Multiple documents exported in batch
   - Data: document_count, file_names, total_size

#### Report Generation
1. **Single Observation PDF** (`monitor.observation.pdf-downloaded`)
   - When: Individual observation exported to PDF
   - Data: observation_id, export_timestamp
   
2. **Batch Observations PDF** (`monitor.observations.pdf-batch-downloaded`)
   - When: Multiple observations exported to PDF
   - Data: count, type_filter, severity_filter, date_range (if applicable)

#### Result Management
1. **Result Submission** (`monitor.result.submitted`) [if monitor can submit results]
   - When: Result data submitted
   - Data: station_id, total_votes_cast, valid_votes, rejected_votes
   
2. **Result Updates** (result_updated)
   - When: Existing result modified
   - Data: changed_fields, old_values, new_values

#### Access Attempts
1. **Permission Denied** (access-denied)
   - When: User attempts unauthorized action
   - Data: attempted_action, required_permission, reason

2. **Monitor Inactive** (monitor-inactive-access)
   - When: User with inactive monitor tries to access
   - Data: monitor_id, status

### Audit Log Query Examples

#### Recent Activity for Monitor
```php
AuditLog::where('user_id', Auth::user()->id)
    ->where('module', 'ElectionMonitor')
    ->orderByDesc('created_at')
    ->limit(50)
    ->get();
```

#### Access to Specific Observation
```php
AuditLog::where('module', 'ElectionMonitor')
    ->whereJsonContains('extra->observation_id', $observationId)
    ->orderByDesc('created_at')
    ->get();
```

#### Failed Access Attempts
```php
AuditLog::where('module', 'ElectionMonitor')
    ->whereJsonContains('extra->outcome', 'blocked')
    ->orderByDesc('created_at')
    ->get();
```

#### Document Download History
```php
AuditLog::where('action', 'monitor.observation.document-downloaded')
    ->where('module', 'ElectionMonitor')
    ->orderByDesc('created_at')
    ->get();
```

---

## Security Best Practices Implemented

### 1. Authentication & Authorization
- ✅ Multi-layer authentication checks (middleware + controller)
- ✅ Role-based access control (RBAC) with permissions
- ✅ Monitor-level data isolation
- ✅ Station assignment validation

### 2. Data Protection
- ✅ SQL queries use parameter binding (prevent SQL injection)
- ✅ File paths validated against stored metadata (prevent path traversal)
- ✅ JSON-based document metadata for secure reference
- ✅ Laravel Storage facade for file access

### 3. Audit Trail
- ✅ All sensitive actions logged
- ✅ Complete context captured (user, IP, timestamp, action details)
- ✅ JSON extra fields for flexible metadata storage
- ✅ Outcome tracking (success/blocked/denied)

### 4. Input Validation
- ✅ Request validation at route level
- ✅ File upload validation (type, size, count)
- ✅ Polling station assignment validation
- ✅ Document path validation against stored records

---

## Testing Access Control

### Manual Testing Checklist
- [ ] Monitor can only see own observations
- [ ] Monitor cannot access other monitors' observations via direct URL
- [ ] Monitor can download own documents
- [ ] Monitor cannot download documents from other monitors' observations
- [ ] Monitor cannot view results from unassigned stations
- [ ] Inactive monitor cannot access any endpoints
- [ ] User without monitor role cannot access monitor routes

### Audit Log Verification
- [ ] Observation submission recorded with all details
- [ ] Document downloads logged with document metadata
- [ ] PDF exports logged with observation count/filters
- [ ] Failed access attempts logged with reason

---

## Audit Log Display UI

### For Admin/Supervisor Review
Consider adding an admin view that shows:
- Monitor activity timeline
- Document access logs
- Failed access attempts
- Status change history

### Example Implementation
```php
Route::get('/admin/audit/monitors/{monitorId}', [AuditController::class, 'monitorActivity'])
    ->middleware(['auth', 'admin'])
    ->name('admin.audit.monitor');
```

---

## Future Enhancements

1. **Real-Time Alerts**: Trigger alerts for suspicious access patterns
2. **Audit Report Export**: CSV/PDF export of audit logs for compliance
3. **Data Retention Policy**: Archive old audit logs after compliance period
4. **Audit Dashboard**: Visual representation of access patterns
5. **Compliance Reporting**: Generate reports for audit/compliance purposes

---

## Compliance & Standards

### GDPR Consideration
- Audit logs contain user IP addresses (may be PII)
- Implement retention policy for sensitive data
- Provide audit log export/deletion options

### Election Security Standards
- Complete audit trail for election integrity
- Multi-layer access control for sensitive data
- Immutable audit logs for post-election verification

---

**Implementation Date**: Phase 6 - System Enhancement
**Status**: ✅ Complete
