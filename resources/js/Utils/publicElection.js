const TYPE_LABELS = {
    presidential: 'Presidential election',
    parliamentary: 'National Assembly election',
    local_government: 'Regional / local government election',
    by_election: 'Constituency / by-election',
};

export function electionYear(election) {
    if (!election?.start_date) return null;
    const year = new Date(election.start_date).getFullYear();
    return Number.isNaN(year) ? null : String(year);
}

export function publicElectionTitle(election) {
    if (!election?.name) return 'Public results';

    const name = election.name.trim();
    const year = electionYear(election);
    const match = name.match(/^(\d{4})\s+(.+)$/);

    if (!match) return name;
    return match[1] === year ? name : match[2];
}

export function electionTypeLabel(election) {
    return TYPE_LABELS[election?.type] || 'Election';
}
