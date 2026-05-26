import { useState, useRef, useEffect } from 'react';

/**
 * SearchableSelect — Drop-in replacement for <select> with type-to-filter.
 *
 * Props:
 *   value       — current selected value (string | number)
 *   onChange    — (value) => void
 *   options     — [{ value, label, meta? }]
 *   placeholder — string
 *   disabled    — bool
 *   clearable   — bool (show × to clear)
 *   className   — extra tailwind classes
 *   emptyLabel  — message when no options match
 */
export default function SearchableSelect({
    value,
    onChange,
    options = [],
    placeholder = 'Select…',
    disabled = false,
    clearable = false,
    className = '',
    emptyLabel = 'No results found',
}) {
    const [open, setOpen]   = useState(false);
    const [query, setQuery] = useState('');
    const containerRef      = useRef(null);
    const inputRef          = useRef(null);
    const listRef           = useRef(null);
    const [highlightIdx, setHighlightIdx] = useState(-1);

    const selectedOption = options.find(o => String(o.value) === String(value));

    const filtered = query.trim()
        ? options.filter(o =>
            String(o.label).toLowerCase().includes(query.toLowerCase()) ||
            (o.meta && String(o.meta).toLowerCase().includes(query.toLowerCase()))
          )
        : options;

    /* close on outside click */
    useEffect(() => {
        const handler = (e) => {
            if (containerRef.current && !containerRef.current.contains(e.target)) {
                setOpen(false);
                setQuery('');
                setHighlightIdx(-1);
            }
        };
        document.addEventListener('mousedown', handler);
        return () => document.removeEventListener('mousedown', handler);
    }, []);

    /* scroll highlighted option into view */
    useEffect(() => {
        if (highlightIdx >= 0 && listRef.current) {
            const el = listRef.current.querySelectorAll('[data-opt]')[highlightIdx];
            el?.scrollIntoView({ block: 'nearest' });
        }
    }, [highlightIdx]);

    function handleOpen() {
        if (disabled) return;
        setOpen(true);
        setHighlightIdx(-1);
        setTimeout(() => inputRef.current?.focus(), 10);
    }

    function handleSelect(option) {
        onChange(option.value);
        setOpen(false);
        setQuery('');
        setHighlightIdx(-1);
    }

    function handleKeyDown(e) {
        if (!open) return;
        if (e.key === 'ArrowDown') {
            e.preventDefault();
            setHighlightIdx(i => Math.min(i + 1, filtered.length - 1));
        } else if (e.key === 'ArrowUp') {
            e.preventDefault();
            setHighlightIdx(i => Math.max(i - 1, 0));
        } else if (e.key === 'Enter') {
            e.preventDefault();
            if (highlightIdx >= 0 && filtered[highlightIdx]) {
                handleSelect(filtered[highlightIdx]);
            }
        } else if (e.key === 'Escape') {
            setOpen(false);
            setQuery('');
            setHighlightIdx(-1);
        }
    }

    function handleClear(e) {
        e.stopPropagation();
        onChange('');
        setQuery('');
    }

    return (
        <div ref={containerRef} className={`relative ${className}`}>
            {/* Trigger button */}
            <button
                type="button"
                onClick={handleOpen}
                disabled={disabled}
                className={[
                    'ws-input w-full flex items-center justify-between text-left h-auto py-2',
                    open ? 'border-[var(--iec-pink)] ring-2 ring-[var(--iec-pink)]/10' : '',
                    disabled ? 'opacity-50 cursor-not-allowed' : 'cursor-pointer',
                ].join(' ')}
            >
                <span className={selectedOption ? 'text-slate-900 truncate' : 'text-slate-400 truncate'}>
                    {selectedOption ? selectedOption.label : placeholder}
                </span>
                <div className="flex items-center gap-1 flex-shrink-0 ml-2">
                    {clearable && selectedOption && (
                        <span
                            onClick={handleClear}
                            className="text-slate-400 hover:text-slate-600 text-xs leading-none px-1"
                            role="button"
                            aria-label="Clear selection"
                        >
                            ✕
                        </span>
                    )}
                    <svg
                        className={`w-4 h-4 text-slate-400 transition-transform duration-150 ${open ? 'rotate-180' : ''}`}
                        viewBox="0 0 24 24" fill="none" stroke="currentColor" strokeWidth="2"
                    >
                        <path strokeLinecap="round" strokeLinejoin="round" d="M6 9l6 6 6-6"/>
                    </svg>
                </div>
            </button>

            {/* Dropdown */}
            {open && (
                <div className="absolute z-[200] w-full mt-1 bg-white rounded-lg border border-slate-200 shadow-xl overflow-hidden"
                     style={{ minWidth: '100%' }}>
                    {/* Search */}
                    <div className="p-2 border-b border-slate-100 bg-slate-50">
                        <input
                            ref={inputRef}
                            type="text"
                            value={query}
                            onChange={e => { setQuery(e.target.value); setHighlightIdx(0); }}
                            onKeyDown={handleKeyDown}
                            placeholder="Type to search…"
                            className="w-full px-3 py-1.5 text-sm border border-slate-200 rounded-md bg-white focus:outline-none focus:border-[var(--iec-pink)] focus:ring-1 focus:ring-[var(--iec-pink)]/30"
                        />
                    </div>

                    {/* Option list */}
                    <ul
                        ref={listRef}
                        className="max-h-56 overflow-y-auto"
                        role="listbox"
                    >
                        {filtered.length === 0 ? (
                            <li className="px-3 py-3 text-sm text-slate-400 text-center">
                                {emptyLabel}
                            </li>
                        ) : (
                            filtered.map((option, idx) => {
                                const isSelected   = String(option.value) === String(value);
                                const isHighlighted = idx === highlightIdx;
                                return (
                                    <li
                                        key={option.value}
                                        data-opt
                                        role="option"
                                        aria-selected={isSelected}
                                        onClick={() => handleSelect(option)}
                                        onMouseEnter={() => setHighlightIdx(idx)}
                                        className={[
                                            'flex items-center justify-between px-3 py-2 text-sm cursor-pointer transition-colors',
                                            isHighlighted ? 'bg-[var(--iec-pink-faint)]' : '',
                                            isSelected
                                                ? 'text-[var(--iec-pink)] font-semibold bg-[var(--iec-pink-faint)]'
                                                : 'text-slate-700 hover:bg-slate-50',
                                        ].join(' ')}
                                    >
                                        <span className="truncate">{option.label}</span>
                                        {isSelected && (
                                            <svg className="w-4 h-4 flex-shrink-0 ml-2" fill="currentColor" viewBox="0 0 20 20">
                                                <path fillRule="evenodd" d="M16.707 5.293a1 1 0 010 1.414l-8 8a1 1 0 01-1.414 0l-4-4a1 1 0 011.414-1.414L8 12.586l7.293-7.293a1 1 0 011.414 0z" clipRule="evenodd"/>
                                            </svg>
                                        )}
                                    </li>
                                );
                            })
                        )}
                    </ul>
                </div>
            )}
        </div>
    );
}