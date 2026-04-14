import { useRef, useCallback, useEffect } from 'react';

/**
 * RichTextEditor - Rich text editor
 * Uses contentEditable with execCommand for formatting
 */
export default function RichTextEditor({ value, onChange, placeholder = 'Add your comments here...', minHeight = '200px' }) {
    const editorRef = useRef(null);
    const isComposing = useRef(false);

    // Sync value into the editor only on mount (or when value is cleared externally)
    useEffect(() => {
        if (!editorRef.current) return;
        // Only reset if the editor is empty and value exists, to avoid cursor jumping
        if (editorRef.current.innerHTML !== value) {
            editorRef.current.innerHTML = value || '';
        }
    }, []); // intentionally only on mount

    const execFormat = useCallback((command, val = null) => {
        editorRef.current?.focus();
        document.execCommand(command, false, val);
        if (onChange && editorRef.current) {
            onChange(editorRef.current.innerHTML);
        }
    }, [onChange]);

    const handleInput = useCallback(() => {
        if (isComposing.current) return;
        if (onChange && editorRef.current) {
            onChange(editorRef.current.innerHTML);
        }
    }, [onChange]);

    const tools = [
        { label: 'B',  title: 'Bold',         cmd: 'bold',          cls: 'font-bold' },
        { label: 'I',  title: 'Italic',        cmd: 'italic',        cls: 'italic' },
        { label: 'U',  title: 'Underline',     cmd: 'underline',     cls: 'underline' },
        { label: 'S',  title: 'Strikethrough', cmd: 'strikeThrough', cls: 'line-through' },
    ];

    const listTools = [
        { label: '≡',  title: 'Bullet List',   cmd: 'insertUnorderedList' },
        { label: '1.', title: 'Numbered List', cmd: 'insertOrderedList' },
    ];

    const alignTools = [
        { label: '⬅', title: 'Align Left',   cmd: 'justifyLeft' },
        { label: '↔', title: 'Align Center', cmd: 'justifyCenter' },
        { label: '➡', title: 'Align Right',  cmd: 'justifyRight' },
    ];

    const headingOptions = [
        { label: 'Normal',    tag: 'p' },
        { label: 'Heading 1', tag: 'h1' },
        { label: 'Heading 2', tag: 'h2' },
        { label: 'Heading 3', tag: 'h3' },
    ];

    const ToolButton = ({ title, onClick, children }) => (
        <button
            type="button"
            title={title}
            onMouseDown={(e) => { e.preventDefault(); onClick(); }}
            className="px-2 py-1 text-sm rounded hover:bg-slate-600 text-gray-200 transition-colors select-none"
        >
            {children}
        </button>
    );

    return (
        <div
            className="border border-slate-500 rounded-lg overflow-hidden bg-slate-900 focus-within:border-teal-400 transition-colors shadow-lg"
            dir="ltr"
            style={{ direction: 'ltr', unicodeBidi: 'normal' }}
        >
            {/* Toolbar */}
            <div
                className="flex flex-wrap items-center gap-1 px-3 py-2 bg-slate-800 border-b border-slate-600"
                dir="ltr"
            >
                {/* Heading select */}
                <select
                    onMouseDown={(e) => e.stopPropagation()}
                    onChange={(e) => {
                        document.execCommand('formatBlock', false, e.target.value);
                        editorRef.current?.focus();
                        handleInput();
                    }}
                    className="text-xs bg-slate-700 text-gray-200 border border-slate-600 rounded px-1 py-1 mr-1 cursor-pointer"
                >
                    {headingOptions.map(h => (
                        <option key={h.tag} value={h.tag}>{h.label}</option>
                    ))}
                </select>

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {tools.map(t => (
                    <ToolButton key={t.cmd} title={t.title} onClick={() => execFormat(t.cmd)}>
                        <span className={t.cls}>{t.label}</span>
                    </ToolButton>
                ))}

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {listTools.map(t => (
                    <ToolButton key={t.cmd} title={t.title} onClick={() => execFormat(t.cmd)}>
                        {t.label}
                    </ToolButton>
                ))}

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {alignTools.map(t => (
                    <ToolButton key={t.cmd} title={t.title} onClick={() => execFormat(t.cmd)}>
                        {t.label}
                    </ToolButton>
                ))}

                <div className="w-px h-5 bg-slate-600 mx-1" />

                <ToolButton title="Increase font size" onClick={() => execFormat('fontSize', '5')}>A+</ToolButton>
                <ToolButton title="Decrease font size" onClick={() => execFormat('fontSize', '2')}>A-</ToolButton>

                <div className="w-px h-5 bg-slate-600 mx-1" />

                <label title="Text color" className="flex items-center gap-1 cursor-pointer">
                    <span className="text-xs text-gray-400">Color</span>
                    <input
                        type="color"
                        defaultValue="#f8fafc"
                        onMouseDown={(e) => e.stopPropagation()}
                        onChange={(e) => {
                            document.execCommand('foreColor', false, e.target.value);
                            editorRef.current?.focus();
                            handleInput();
                        }}
                        className="w-6 h-6 rounded cursor-pointer border-0 bg-transparent"
                    />
                </label>

                <div className="w-px h-5 bg-slate-600 mx-1" />

                <ToolButton title="Remove formatting" onClick={() => execFormat('removeFormat')}>
                    <span className="text-xs">Clear</span>
                </ToolButton>

                <div className="w-px h-5 bg-slate-600 mx-1" />
                <ToolButton title="Undo" onClick={() => execFormat('undo')}>↩</ToolButton>
                <ToolButton title="Redo" onClick={() => execFormat('redo')}>↪</ToolButton>
            </div>

            {/* Editor area */}
            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                dir="ltr"
                lang="en"
                onInput={handleInput}
                onCompositionStart={() => { isComposing.current = true; }}
                onCompositionEnd={() => {
                    isComposing.current = false;
                    handleInput();
                }}
                onKeyDown={(e) => {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        document.execCommand('insertText', false, '    ');
                    }
                }}
                data-placeholder={placeholder}
                className="px-4 py-3 text-white focus:outline-none"
                style={{
                    minHeight,
                    direction: 'ltr',
                    textAlign: 'left',
                    unicodeBidi: 'normal',
                    writingMode: 'horizontal-tb',
                    overflowY: 'auto',
                    maxHeight: '300px',
                    fontSize: '14px',
                    fontFamily: 'system-ui, -apple-system, sans-serif',
                    lineHeight: '1.7',
                    whiteSpace: 'pre-wrap',
                    wordBreak: 'break-word',
                }}
            />

            <style>{`
                [contenteditable][dir="ltr"]:empty:before {
                    content: attr(data-placeholder);
                    color: #6b7280;
                    pointer-events: none;
                    direction: ltr;
                    text-align: left;
                    unicode-bidi: normal;
                }
                [contenteditable][dir="ltr"] * {
                    direction: ltr !important;
                    unicode-bidi: normal !important;
                    text-align: left;
                }
                [contenteditable][dir="ltr"] ul,
                [contenteditable][dir="ltr"] ol {
                    padding-left: 1.5rem;
                    margin: 0.25rem 0;
                }
                [contenteditable][dir="ltr"] h1 { font-size: 1.5em; font-weight: bold; margin: 0.25rem 0; }
                [contenteditable][dir="ltr"] h2 { font-size: 1.25em; font-weight: bold; margin: 0.25rem 0; }
                [contenteditable][dir="ltr"] h3 { font-size: 1.1em;  font-weight: bold; margin: 0.25rem 0; }
            `}</style>
        </div>
    );
}
