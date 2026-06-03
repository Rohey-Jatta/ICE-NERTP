export const RESULT_STATUS = Object.freeze({
    NOT_REPORTED: 'not_reported',
    SUBMITTED: 'submitted',
    PENDING_PARTY_ACCEPTANCE: 'pending_party_acceptance',
    PENDING_WARD: 'pending_ward',
    WARD_CERTIFIED: 'ward_certified',
    PENDING_CONSTITUENCY: 'pending_constituency',
    CONSTITUENCY_CERTIFIED: 'constituency_certified',
    PENDING_ADMIN_AREA: 'pending_admin_area',
    ADMIN_AREA_CERTIFIED: 'admin_area_certified',
    PENDING_NATIONAL: 'pending_national',
    NATIONALLY_CERTIFIED: 'nationally_certified',
    REJECTED: 'rejected',
});

export const ACTIVE_CERTIFICATION_PIPELINE = Object.freeze([
    { key: RESULT_STATUS.SUBMITTED, label: 'Submitted', step: 1 },
    { key: RESULT_STATUS.PENDING_WARD, label: 'Ward Review', step: 2 },
    { key: RESULT_STATUS.WARD_CERTIFIED, label: 'Ward Certified', step: 3 },
    { key: RESULT_STATUS.PENDING_CONSTITUENCY, label: 'Constituency Review', step: 4 },
    { key: RESULT_STATUS.CONSTITUENCY_CERTIFIED, label: 'Constituency Certified', step: 5 },
    { key: RESULT_STATUS.PENDING_ADMIN_AREA, label: 'Admin Area Review', step: 6 },
    { key: RESULT_STATUS.ADMIN_AREA_CERTIFIED, label: 'Admin Area Certified', step: 7 },
    { key: RESULT_STATUS.PENDING_NATIONAL, label: 'National Review', step: 8 },
    { key: RESULT_STATUS.NATIONALLY_CERTIFIED, label: 'Nationally Certified', step: 9 },
]);

export const ACTIVE_CERTIFICATION_PIPELINE_KEYS = ACTIVE_CERTIFICATION_PIPELINE.map((step) => step.key);

export const LEGACY_CERTIFICATION_STATUSES = Object.freeze([
    RESULT_STATUS.PENDING_PARTY_ACCEPTANCE,
]);

export const EARLY_PIPELINE_STATUSES = Object.freeze([
    RESULT_STATUS.SUBMITTED,
    RESULT_STATUS.PENDING_PARTY_ACCEPTANCE,
    RESULT_STATUS.PENDING_WARD,
]);

export const CERTIFIED_RESULT_STATUSES = Object.freeze([
    RESULT_STATUS.WARD_CERTIFIED,
    RESULT_STATUS.CONSTITUENCY_CERTIFIED,
    RESULT_STATUS.ADMIN_AREA_CERTIFIED,
    RESULT_STATUS.NATIONALLY_CERTIFIED,
]);

const STATUS_META = Object.freeze({
    [RESULT_STATUS.NOT_REPORTED]: {
        label: 'Not Reported',
        publicLabel: 'Not reported',
        mapLabel: 'Not Reported',
        textClass: 'text-slate-500',
        badgeClass: 'bg-slate-100 text-slate-600',
        borderedBadgeClass: 'bg-slate-100 text-slate-600 border-slate-200',
        mapColor: '#64748b',
        icon: '•',
    },
    [RESULT_STATUS.SUBMITTED]: {
        label: 'Submitted',
        publicLabel: 'Submitted',
        mapLabel: 'Submitted',
        textClass: 'text-sky-500',
        badgeClass: 'bg-sky-500/20 text-sky-300',
        borderedBadgeClass: 'bg-sky-500/20 text-sky-300 border-sky-500/30',
        mapColor: '#f97316',
        icon: '📤',
    },
    [RESULT_STATUS.PENDING_PARTY_ACCEPTANCE]: {
        label: 'Legacy Party Gate',
        publicLabel: 'Legacy party gate',
        mapLabel: 'Legacy Party Gate',
        textClass: 'text-yellow-400',
        badgeClass: 'bg-amber-500/20 text-amber-300',
        borderedBadgeClass: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        mapColor: '#f59e0b',
        icon: '⏳',
    },
    [RESULT_STATUS.PENDING_WARD]: {
        label: 'Ward Review',
        publicLabel: 'Pending ward review',
        mapLabel: 'At Ward',
        textClass: 'text-amber-400',
        badgeClass: 'bg-amber-500/20 text-amber-300',
        borderedBadgeClass: 'bg-amber-500/20 text-amber-300 border-amber-500/30',
        mapColor: '#f59e0b',
        icon: '⏳',
    },
    [RESULT_STATUS.WARD_CERTIFIED]: {
        label: 'Ward Certified',
        publicLabel: 'Ward certified',
        mapLabel: 'Ward Certified',
        textClass: 'text-iec-pink-600',
        badgeClass: 'bg-iec-pink-500/20 text-iec-pink-600',
        borderedBadgeClass: 'bg-iec-pink-500/20 text-iec-pink-600 border-teal-500/30',
        mapColor: '#3b82f6',
        icon: '✓',
    },
    [RESULT_STATUS.PENDING_CONSTITUENCY]: {
        label: 'Constituency Review',
        publicLabel: 'Pending constituency review',
        mapLabel: 'At Constituency',
        textClass: 'text-iec-pink-600',
        badgeClass: 'bg-iec-pink-500/20 text-iec-pink-600',
        borderedBadgeClass: 'bg-iec-pink-500/20 text-iec-pink-600 border-blue-500/30',
        mapColor: '#a855f7',
        icon: '⏳',
    },
    [RESULT_STATUS.CONSTITUENCY_CERTIFIED]: {
        label: 'Constituency Certified',
        publicLabel: 'Constituency certified',
        mapLabel: 'Const. Certified',
        textClass: 'text-cyan-400',
        badgeClass: 'bg-cyan-500/20 text-cyan-300',
        borderedBadgeClass: 'bg-cyan-500/20 text-cyan-300 border-cyan-500/30',
        mapColor: '#06b6d4',
        icon: '✓',
    },
    [RESULT_STATUS.PENDING_ADMIN_AREA]: {
        label: 'Admin Area Review',
        publicLabel: 'Pending admin area review',
        mapLabel: 'At Admin Area',
        textClass: 'text-iec-pink-600',
        badgeClass: 'bg-iec-pink-50 text-iec-pink-600',
        borderedBadgeClass: 'bg-iec-pink-50 text-iec-pink-600 border-iec-pink-100',
        mapColor: '#8b5cf6',
        icon: '⏳',
    },
    [RESULT_STATUS.ADMIN_AREA_CERTIFIED]: {
        label: 'Admin Area Certified',
        publicLabel: 'Admin area certified',
        mapLabel: 'Area Certified',
        textClass: 'text-violet-400',
        badgeClass: 'bg-violet-500/20 text-violet-300',
        borderedBadgeClass: 'bg-violet-500/20 text-violet-300 border-violet-500/30',
        mapColor: '#14b8a6',
        icon: '✓',
    },
    [RESULT_STATUS.PENDING_NATIONAL]: {
        label: 'National Review',
        publicLabel: 'Pending national review',
        mapLabel: 'At Chairman',
        textClass: 'text-pink-400',
        badgeClass: 'bg-amber-500/20 text-amber-300 font-bold',
        borderedBadgeClass: 'bg-pink-500/20 text-pink-300 border-pink-500/30',
        mapColor: '#6366f1',
        icon: '⏳',
    },
    [RESULT_STATUS.NATIONALLY_CERTIFIED]: {
        label: 'Nationally Certified',
        publicLabel: 'Nationally certified',
        mapLabel: 'Certified ✓',
        textClass: 'text-green-400',
        badgeClass: 'bg-green-500/20 text-green-300',
        borderedBadgeClass: 'bg-green-500/20 text-green-300 border-green-500/30',
        mapColor: '#10b981',
        icon: '🏆',
    },
    [RESULT_STATUS.REJECTED]: {
        label: 'Rejected',
        publicLabel: 'Rejected',
        mapLabel: 'Rejected',
        textClass: 'text-red-400',
        badgeClass: 'bg-red-500/20 text-red-300',
        borderedBadgeClass: 'bg-red-500/20 text-red-300 border-red-500/30',
        mapColor: '#ef4444',
        icon: '✗',
    },
});

export const RESULT_STATUS_CONFIG = STATUS_META;

export const RESULT_STATUS_LABELS = Object.fromEntries(
    Object.entries(STATUS_META).map(([key, meta]) => [key, meta.label])
);

export const RESULT_STATUS_PUBLIC_LABELS = Object.fromEntries(
    Object.entries(STATUS_META).map(([key, meta]) => [key, meta.publicLabel])
);

export const RESULT_STATUS_MAP_LABELS = Object.fromEntries(
    Object.entries(STATUS_META).map(([key, meta]) => [key, meta.mapLabel])
);

export const RESULT_STATUS_MAP_COLORS = Object.fromEntries(
    Object.entries(STATUS_META).map(([key, meta]) => [key, meta.mapColor])
);

export function normalizeCertificationStatus(status) {
    return status === RESULT_STATUS.PENDING_PARTY_ACCEPTANCE
        ? RESULT_STATUS.PENDING_WARD
        : status;
}

export function getResultStatusMeta(status, fallback = RESULT_STATUS.NOT_REPORTED) {
    return STATUS_META[status] || STATUS_META[fallback];
}

export function getResultStatusLabel(status, variant = 'label') {
    const meta = getResultStatusMeta(status);
    return meta[variant] || meta.label;
}

export function getCertificationPipelinePercent(status) {
    const normalizedStatus = normalizeCertificationStatus(status);
    const currentStep = ACTIVE_CERTIFICATION_PIPELINE_KEYS.indexOf(normalizedStatus);
    return currentStep >= 0
        ? Math.round(((currentStep + 1) / ACTIVE_CERTIFICATION_PIPELINE_KEYS.length) * 100)
        : 0;
}

export function getPublicStationCategory(status) {
    if (status === RESULT_STATUS.NATIONALLY_CERTIFIED) return 'certified';
    if ([
        RESULT_STATUS.WARD_CERTIFIED,
        RESULT_STATUS.PENDING_CONSTITUENCY,
        RESULT_STATUS.CONSTITUENCY_CERTIFIED,
        RESULT_STATUS.PENDING_ADMIN_AREA,
        RESULT_STATUS.ADMIN_AREA_CERTIFIED,
        RESULT_STATUS.PENDING_NATIONAL,
    ].includes(status)) {
        return 'in_progress';
    }
    if ([
        RESULT_STATUS.SUBMITTED,
        RESULT_STATUS.PENDING_WARD,
        RESULT_STATUS.PENDING_PARTY_ACCEPTANCE,
    ].includes(status)) {
        return 'submitted';
    }
    return 'not_reported';
}
