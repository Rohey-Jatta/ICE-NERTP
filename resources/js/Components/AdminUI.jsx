import { Link } from '@inertiajs/react';

/**
 * AdminUI — light-theme workspace primitives.
 * Visual style mirrors the Admin Dashboard (white panels, navy headings,
 * pink accents, slate-700 body text). Public API is preserved so existing
 * admin pages (Users, Elections, PollingStations, Parties, AuditLogs,
 * Settings) keep working without refactoring.
 */

export const roleLabel = (role) => {
    if (!role) return 'No role';
    return role.replace(/-/g, ' ').replace(/\b\w/g, (char) => char.toUpperCase());
};

/* ── Page Header ───────────────────────────────────────────────── */
export function PageHeader({
    title,
    description,
    actions,
    backHref = '/admin/dashboard',
    backLabel = '← Back to Dashboard',
}) {
    return (
        <div className="ws-page-header">
            <div className="min-w-0">
                {backHref && (
                    <Link href={backHref} className="ws-page-back">
                        {backLabel}
                    </Link>
                )}
                <h1 className="ws-page-title">{title}</h1>
                {description && <p className="ws-page-desc">{description}</p>}
            </div>
            {actions && <div className="flex flex-wrap gap-2">{actions}</div>}
        </div>
    );
}

/* ── Panel ──────────────────────────────────────────────────────── */
export function Panel({ children, className = '' }) {
    return (
        <section className={`ws-panel ${className}`}>
            {children}
        </section>
    );
}

/* ── Button ─────────────────────────────────────────────────────── */
export function Button({
    children,
    href,
    onClick,
    type = 'button',
    variant = 'primary',
    disabled = false,
    className = '',
}) {
    const variants = {
        primary:   'ws-btn ws-btn-primary',
        secondary: 'ws-btn ws-btn-secondary',
        quiet:     'ws-btn ws-btn-quiet',
        danger:    'ws-btn ws-btn-danger',
        warning:   'ws-btn ws-btn-warning',
    };

    const cls = `${variants[variant] || variants.primary} ${className}`.trim();

    if (href) {
        return <Link href={href} className={cls}>{children}</Link>;
    }

    return (
        <button type={type} onClick={onClick} disabled={disabled} className={cls}>
            {children}
        </button>
    );
}

/* ── Badge ──────────────────────────────────────────────────────── */
export function Badge({ children, tone = 'slate' }) {
    const tones = {
        slate: 'ws-badge ws-badge-slate',
        teal:  'ws-badge ws-badge-teal',
        pink:  'ws-badge ws-badge-pink',
        amber: 'ws-badge ws-badge-amber',
        rose:  'ws-badge ws-badge-rose',
        blue:  'ws-badge ws-badge-blue',
        navy:  'ws-badge ws-badge-navy',
    };

    return <span className={tones[tone] || tones.slate}>{children}</span>;
}

/* ── Toolbar (filter bar) ───────────────────────────────────────── */
export function Toolbar({ children }) {
    return <div className="ws-toolbar">{children}</div>;
}

/* ── Field ──────────────────────────────────────────────────────── */
export function Field({ label, children }) {
    return (
        <label className="block">
            {label && <span className="ws-label">{label}</span>}
            {children}
        </label>
    );
}

/* Backwards-compatible input class — points to the new light style */
export const inputClass = 'ws-input';

/* ── DataTable ──────────────────────────────────────────────────── */
export function DataTable({ columns, rows, empty = 'No records found', rowKey = (row) => row.id }) {
    return (
        <div className="ws-table-wrap">
            <div className="ws-table-scroll">
                <table className="ws-table">
                    <thead>
                        <tr>
                            {columns.map((column) => (
                                <th
                                    key={column.key}
                                    className={
                                        column.align === 'right' ? 'is-right'
                                            : column.align === 'center' ? 'is-center'
                                                : ''
                                    }
                                >
                                    {column.header}
                                </th>
                            ))}
                        </tr>
                    </thead>
                    <tbody>
                        {rows.length > 0 ? rows.map((row) => (
                            <tr key={rowKey(row)}>
                                {columns.map((column) => (
                                    <td
                                        key={column.key}
                                        className={
                                            column.align === 'right' ? 'is-right'
                                                : column.align === 'center' ? 'is-center'
                                                    : ''
                                        }
                                    >
                                        {column.render ? column.render(row) : row[column.key]}
                                    </td>
                                ))}
                            </tr>
                        )) : (
                            <tr>
                                <td colSpan={columns.length} className="ws-table-empty">
                                    {empty}
                                </td>
                            </tr>
                        )}
                    </tbody>
                </table>
            </div>
        </div>
    );
}

/* ── Pagination ─────────────────────────────────────────────────── */
export function Pagination({ links }) {
    if (!links?.length) return null;

    return (
        <div className="ws-pagination">
            {links.map((link, index) => link.url ? (
                <Link
                    key={`${link.label}-${index}`}
                    href={link.url}
                    className={`ws-pagination-link ${link.active ? 'is-active' : ''}`}
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ) : (
                <span
                    key={`${link.label}-${index}`}
                    className="ws-pagination-disabled"
                    dangerouslySetInnerHTML={{ __html: link.label }}
                />
            ))}
        </div>
    );
}
