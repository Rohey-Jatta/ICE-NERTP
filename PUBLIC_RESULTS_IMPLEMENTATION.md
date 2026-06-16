# Public Results Display Implementation

## Overview

This document details the implementation of three interconnected fixes for the public election results pages:

1. **Issue 1:** Map Region filter + additional filter options (Ward, Constituency)
2. **Issue 2:** Display result sheet images and party representative reactions
3. **Issue 3:** Apply strict data gating - only publish certified results to public

## Architecture

### Data Flow

```
User Request
    ↓
ResultsMapController::index()
    ├─ Resolve election
    ├─ Set isPublished = election.status === 'certified'
    ├─ Pass to computeStationsData($election, $isPublished)
    │   ├─ Load stations with current results
    │   ├─ IF isPublished: load photo_url, party_acceptances
    │   └─ IF !isPublished: return null for sensitive fields
    ├─ Cache with pub/prov differentiation
    └─ Inertia render with isPublished flag
         ↓
    ResultsMap.jsx
    ├─ Receives isPublished prop
    ├─ Passes to DrillPanel
    └─ SidebarContent uses for filter display
         ↓
    User sees:
    - IF isPublished: Full data (photos, party reactions)
    - IF !isPublished: "Available once published" placeholders
```

## Implementation Details

### 1. Backend: ResultsMapController.php

#### Changes to `index()` method

**Added publication status check:**
```php
$isPublished = $election->status === 'certified';
```

**Differentiated cache keys:**
```php
$cacheKey = "results_map_{$election->id}_" . ($isPublished ? 'pub' : 'prov');
$stations = Cache::remember($cacheKey, 30, 
    fn() => $this->computeStationsData($election, $isPublished)
);
```

**Pass flag to frontend:**
```php
return Inertia::render('Public/ResultsMap', [
    // ... other props
    'isPublished' => $isPublished,
]);
```

#### Changes to `computeStationsData()` method

**Signature:**
```php
private function computeStationsData(Election $election, bool $isPublished = false): array
```

**Station query includes photo path:**
```php
$selectRaw("
    ...
    r.result_sheet_photo_path
")
```

**Conditional party acceptances loading:**
```php
if ($isPublished) {
    $paRows = DB::select("
        SELECT
            pa.result_id,
            pp.name AS party_name,
            pp.abbreviation AS party_abbr,
            pa.status,
            pa.comments
        FROM party_acceptances pa
        JOIN political_parties pp ON pp.id = pa.political_party_id
        WHERE pa.result_id IN ({$placeholders})
    ", $resultIds);
    
    foreach ($paRows as $row) {
        $partyAcceptancesByResult[$row->result_id][] = [...];
    }
}
```

**Data returned to frontend:**
```php
$station->party_acceptances = ($isPublished && $station->result_id)
    ? ($partyAcceptancesByResult[$station->result_id] ?? [])
    : [];

$station->photo_url = ($isPublished && $station->result_sheet_photo_path)
    ? asset('storage/' . $station->result_sheet_photo_path)
    : null;
```

#### Changes to `stationsJson()` method

**Updated JSON response:**
```php
return response()->json([
    'stations' => $stations, 
    'isPublished' => $isPublished
]);
```

### 2. Frontend: ResultsMap.jsx

#### Added Ward Filter Support

**State:**
```jsx
const [selWard, setSelWard] = useState('all');
```

**Ward options calculation:**
```jsx
const constScoped = useMemo(() => (
    regionScoped.filter((s) => selConst === 'all' || s.constituency_name === selConst)
), [regionScoped, selConst]);

const wardOptions = useMemo(() => buildOptions(constScoped, 'ward_name'), [constScoped]);
```

**Ward filtering:**
```jsx
const locationFiltered = useMemo(() => (
    constScoped.filter((s) => selWard === 'all' || s.ward_name === selWard)
), [constScoped, selWard]);
```

**Reset logic:**
```jsx
const handleStationRegion = useCallback((val) => { 
    setSelStationReg(val); 
    setSelConst('all'); 
    setSelWard('all');  // ← NEW
}, []);
```

**Clear filters:**
```jsx
const clearFilters = useCallback(() => { 
    setSearchTerm(''); 
    setSelStationReg('all'); 
    setSelConst('all'); 
    setSelWard('all');  // ← NEW
    setStatusFilter('all'); 
}, []);

const hasFilters = searchTerm || selStationReg !== 'all' || selConst !== 'all' || 
    selWard !== 'all' || statusFilter !== 'all';  // ← NEW CHECK
```

#### Updated SidebarContent Component

**Added Ward filter to UI:**
```jsx
function SidebarContent({
    searchTerm, setSearchTerm,
    selectedRegion, setSelectedRegion,
    selectedConst, setSelectedConst,
    selectedWard, setSelectedWard,           // ← NEW
    regionOptions, constOptions, wardOptions, // ← NEW
    statusFilter, setStatusFilter,
    filterCounts, hasFilters, clearFilters,
    totalCount, regionScopedCount, constScopedCount, // ← NEW
}) {
    return (
        <div className="flex flex-1 flex-col overflow-hidden">
            {/* ... existing code ... */}
            
            {/* NEW: Ward filter dropdown */}
            <div>
                <label className="mb-2 block text-xs font-bold uppercase tracking-wide text-slate-500">Ward</label>
                <SearchableSelect value={selectedWard} onChange={setSelectedWard}
                                  options={[{ value: 'all', label: `All (${constScopedCount})` }, ...wardOptions]} 
                                  placeholder="All Wards" className="w-full text-sm" />
            </div>
            
            {/* ... rest of component ... */}
        </div>
    );
}
```

#### isPublished Prop Flow

**Export function:**
```jsx
export default function ResultsMap({ 
    election, 
    elections = [], 
    selectedElectionId, 
    stations = [], 
    regions = [], 
    national = null, 
    isPublished = false  // ← NEW
}) {
```

**Passed to DrillPanel (desktop):**
```jsx
<aside className="hidden w-[360px] flex-shrink-0 border-l border-slate-200 bg-white md:block">
    <DrillPanel
        regions={regions}
        drill={drill}
        onDrill={setDrill}
        stations={stationList}
        param={param}
        isPublished={isPublished}  // ← NEW
    />
</aside>
```

**Passed to DrillPanel (mobile):**
```jsx
{mode === 'regions' && (
    <div className="border-t border-slate-200 bg-white md:hidden">
        <div className="max-h-64 overflow-y-auto">
            <DrillPanel
                regions={regions}
                drill={drill}
                onDrill={setDrill}
                stations={stationList}
                param={param}
                isPublished={isPublished}  // ← NEW
            />
        </div>
    </div>
)}
```

#### DrillPanel Component Update

**Function signature:**
```jsx
function DrillPanel({ 
    regions, 
    drill, 
    onDrill, 
    stations, 
    param, 
    isPublished = false  // ← NEW
}) {
```

**Ready to use in ward station rendering** (see next section)

### 3. Ward Station Display Enhancement (PENDING JSX UPDATE)

**Location:** ResultsMap.jsx line ~243-270

**Current code (simple list):**
```jsx
wardStations.map((s) => {
    const isReported = s.status !== 'not_reported' && s.total_votes_cast != null;
    const statusColor = RESULT_STATUS_MAP_COLORS[s.status] || RESULT_STATUS_MAP_COLORS[RESULT_STATUS.NOT_REPORTED];
    return (
        <div key={s.id} className="flex items-start gap-3 border-b border-slate-100 px-5 py-3">
            {/* Station info */}
        </div>
    );
})
```

**Needs update to (expandable card):**
```jsx
wardStations.map((s) => {
    const isReported = s.status !== 'not_reported' && s.total_votes_cast != null;
    const statusColor = RESULT_STATUS_MAP_COLORS[s.status] || RESULT_STATUS_MAP_COLORS[RESULT_STATUS.NOT_REPORTED];
    return (
        <div key={s.id} className="border-b border-slate-100">
            {/* Station header */}
            <div className="flex items-start gap-3 px-5 py-3">
                <span className="mt-1.5 h-2 w-2 flex-shrink-0 rounded-full" style={{ backgroundColor: statusColor }} />
                <div className="min-w-0 flex-1">
                    <div className="truncate text-sm font-semibold text-slate-800">{s.name}</div>
                    <div className="font-mono text-xs text-slate-400">{s.code}</div>
                </div>
                <div className="flex-shrink-0 text-right text-xs tabular-nums text-slate-500">
                    {isReported ? numeric(s.valid_votes).toLocaleString() + ' valid' : '—'}
                </div>
            </div>
            
            {/* Result sheet photo (published only) */}
            {isPublished && s.photo_url && (
                <div className="border-t border-slate-100 px-5 py-3">
                    <img
                        src={s.photo_url}
                        alt={`Result sheet for ${s.name}`}
                        className="w-full rounded-lg border border-slate-200 object-cover"
                    />
                </div>
            )}
            
            {/* Party acceptances (published only) */}
            {isPublished && s.party_acceptances?.length > 0 && (
                <div className="border-t border-slate-100 px-5 py-2">
                    <div className="text-[10px] font-semibold uppercase tracking-[0.08em] text-slate-500 mb-2">
                        Party Acceptances
                    </div>
                    <div className="flex flex-col gap-1">
                        {s.party_acceptances.map((pa, idx) => (
                            <div key={`${pa.party_abbr}-${idx}`} className="flex items-center justify-between gap-2 text-[11px]">
                                <span className="truncate font-semibold text-slate-800">
                                    {pa.party_abbr}
                                </span>
                                <span className={`flex-shrink-0 rounded-full px-2 py-0.5 text-[9px] font-semibold capitalize ${
                                    pa.status === 'accepted' ? 'bg-emerald-100 text-emerald-700' : 
                                    pa.status === 'rejected' ? 'bg-red-100 text-red-700' : 
                                    'bg-slate-100 text-slate-600'
                                }`}>
                                    {pa.status}
                                </span>
                            </div>
                        ))}
                    </div>
                </div>
            )}
        </div>
    );
})
```

## Security & Data Integrity

### API-Level Gating

**Data filtered at source:**
- Backend never returns photo_url or party_acceptances for unpublished elections
- Frontend cannot override since data isn't present
- Separate cache keys prevent cache poisoning between pub/prov states

### Frontend-Level Protection

**Secondary gating with `isPublished` prop:**
- Component receives flag from backend
- Conditional rendering: `{isPublished && photo_url ? <img/> : <placeholder/>}`
- Acts as fail-safe if backend data structure changes

### User Experience

**For unpublished elections:**
- Stations show vote counts but not detailed results
- Photos show placeholder: "Available once published"
- Party reactions hidden

**For published elections:**
- Full data visible: candidate votes, photos, party reactions
- Complete transparency for public users

## Performance Considerations

### Caching Strategy

**Separate cache keys:**
```
results_map_{election_id}_pub   (30 sec TTL) - published results
results_map_{election_id}_prov  (30 sec TTL) - provisional results
```

**Benefits:**
- No cache collision between publication states
- Election state change automatically uses different cache
- Party acceptances query only runs for published elections
- Asset path computation only for published elections

### Database Query Optimization

**Conditional party_acceptances JOIN:**
- Only executed when `$isPublished = true`
- Saves SQL query cost for unpublished elections
- Reduces JSON payload size

**Result set DISTINCT ON:**
```sql
SELECT DISTINCT ON (polling_station_id) *
FROM results
ORDER BY polling_station_id,
    CASE WHEN certification_status = 'nationally_certified' THEN 0 ELSE 1 END,
    nationally_certified_at DESC NULLS LAST
```

Ensures latest result per station regardless of certification status

## Testing Checklist

### Backend API Tests

- [ ] ResultsMapController returns `isPublished = true` for certified elections
- [ ] ResultsMapController returns `isPublished = false` for non-certified elections
- [ ] `photo_url` populated only when `isPublished = true`
- [ ] `party_acceptances` array returned only when `isPublished = true`
- [ ] Cache differentiation: pub vs prov cache keys
- [ ] JSON endpoint returns both stations and `isPublished` flag

### Frontend Filter Tests

- [ ] Ward filter appears in sidebar
- [ ] Ward filter builds options from current region+constituency
- [ ] Ward filter combination works: Region + Constituency + Ward
- [ ] Changing Region resets Constituency and Ward
- [ ] Clear filters button resets all filters including Ward
- [ ] Station count updates when filters change

### Data Display Tests

- [ ] Map drill-down shows photos for published elections
- [ ] Map drill-down shows party reactions for published elections
- [ ] Map drill-down shows placeholders for unpublished elections
- [ ] Stations page shows photos/reactions (already implemented)
- [ ] All status colors display correctly

### Security Tests

- [ ] Public user cannot access unpublished data
- [ ] Unpublished election data not in network response
- [ ] Cache doesn't leak data between states
- [ ] Unauthenticated users see only published results

## Related Components

### ResultsStationsController
✅ Already implements similar gating:
```php
$isPublished = $election->status === 'certified';
if ($isPublished) { load party_acceptances }
'photo_url' => ($isPublished && $station->result_sheet_photo_path) ? asset(...) : null
```

### ResultsSummaryController
✅ Already uses NATIONALLY_CERTIFIED filtering:
```php
AND certification_status = 'nationally_certified'
```

### resultStatus.js Utils
✅ Status mappings already defined:
- `RESULT_STATUS.NATIONALLY_CERTIFIED`
- `RESULT_STATUS_MAP_COLORS`
- `getPublicStationCategory()`

## Deployment Notes

1. **Database:** Ensure `result_sheet_photo_path` column exists in results table
2. **Storage:** Verify result sheet files accessible via `asset('storage/...')`
3. **Cache:** Clear `results_map_*` cache keys after deployment
4. **Feature flag:** Can be toggled via `allow_provisional_public_display` election setting
5. **Backward compatibility:** `isPublished` defaults to `false` if missing from props

## Future Enhancements

1. **Landing page:** Apply same `isPublished` gating to Results.jsx landing page
2. **Search:** Ensure search filters respect publication status
3. **Analytics:** Track which results users view (published vs provisional access attempts)
4. **Export:** Restrict result exports to published data for public users
5. **Notifications:** Alert when results become published (state transitions)
