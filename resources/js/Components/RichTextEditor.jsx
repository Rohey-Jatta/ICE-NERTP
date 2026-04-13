import { useRef, useCallback } from 'react';

/**
 * RichTextEditor - Rich text editor
 * Uses contentEditable with execCommand for formatting
 */
export default function RichTextEditor({ value, onChange, placeholder = 'Add your comments here...', minHeight = '200px' }) {
    const editorRef = useRef(null);

    const execFormat = useCallback((command, value = null) => {
        document.execCommand(command, false, value);
        editorRef.current?.focus();
        handleInput();
    }, []);

    const handleInput = useCallback(() => {
        if (editorRef.current && onChange) {
            onChange(editorRef.current.innerHTML);
        }
    }, [onChange]);

    const tools = [
        { label: 'B',  title: 'Bold',          cmd: 'bold',          style: 'font-bold' },
        { label: 'I',  title: 'Italic',         cmd: 'italic',        style: 'italic' },
        { label: 'U',  title: 'Underline',      cmd: 'underline',     style: 'underline' },
        { label: 'S',  title: 'Strikethrough',  cmd: 'strikeThrough', style: 'line-through' },
    ];

    const listTools = [
        { label: '≡', title: 'Bullet List',   cmd: 'insertUnorderedList' },
        { label: '1.', title: 'Numbered List', cmd: 'insertOrderedList' },
    ];

    const alignTools = [
        { label: '⬅', title: 'Align Left',    cmd: 'justifyLeft' },
        { label: '↔', title: 'Align Center',  cmd: 'justifyCenter' },
        { label: '➡', title: 'Align Right',   cmd: 'justifyRight' },
    ];

    const headingOptions = [
        { label: 'Normal',    tag: 'p' },
        { label: 'Heading 1', tag: 'h1' },
        { label: 'Heading 2', tag: 'h2' },
        { label: 'Heading 3', tag: 'h3' },
    ];

    const ToolButton = ({ title, onClick, children, className = '' }) => (
        <button
            type="button"
            title={title}
            onMouseDown={(e) => { e.preventDefault(); onClick(); }}
            className={`px-2 py-1 text-sm rounded hover:bg-slate-600 text-gray-200 transition-colors ${className}`}
        >
            {children}
        </button>
    );

    return (
        <div className="border border-slate-500 rounded-lg overflow-hidden bg-slate-900 focus-within:border-teal-400 transition-colors shadow-lg">
            {/* Toolbar */}
            <div className="flex flex-wrap items-center gap-1 px-3 py-2 bg-slate-800 border-b border-slate-600">
                {/* Heading select */}
                <select
                    onMouseDown={(e) => e.stopPropagation()}
                    onChange={(e) => {
                        const tag = e.target.value;
                        document.execCommand('formatBlock', false, tag);
                        editorRef.current?.focus();
                        handleInput();
                    }}
                    className="text-xs bg-slate-700 text-gray-200 border border-slate-600 rounded px-1 py-1 mr-1"
                >
                    {headingOptions.map(h => (
                        <option key={h.tag} value={h.tag}>{h.label}</option>
                    ))}
                </select>

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {/* Formatting */}
                {tools.map(t => (
                    <ToolButton key={t.cmd} title={t.title} onClick={() => execFormat(t.cmd)}>
                        <span className={t.style}>{t.label}</span>
                    </ToolButton>
                ))}

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {/* Lists */}
                {listTools.map(t => (
                    <ToolButton key={t.cmd} title={t.title} onClick={() => execFormat(t.cmd)}>
                        {t.label}
                    </ToolButton>
                ))}

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {/* Alignment */}
                {alignTools.map(t => (
                    <ToolButton key={t.cmd} title={t.title} onClick={() => execFormat(t.cmd)}>
                        {t.label}
                    </ToolButton>
                ))}

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {/* Font size */}
                <ToolButton title="Increase font size" onClick={() => execFormat('fontSize', '5')}>A+</ToolButton>
                <ToolButton title="Decrease font size" onClick={() => execFormat('fontSize', '2')}>A-</ToolButton>

                <div className="w-px h-5 bg-slate-600 mx-1" />

                {/* Color */}
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

                {/* Clear */}
                <ToolButton title="Remove formatting" onClick={() => execFormat('removeFormat')}>
                    <span className="text-xs">Clear</span>
                </ToolButton>

                {/* Undo/Redo */}
                <div className="w-px h-5 bg-slate-600 mx-1" />
                <ToolButton title="Undo (Ctrl+Z)" onClick={() => execFormat('undo')}>↩</ToolButton>
                <ToolButton title="Redo (Ctrl+Y)" onClick={() => execFormat('redo')}>↪</ToolButton>
            </div>

            {/* Editor area */}
            <div
                ref={editorRef}
                contentEditable
                suppressContentEditableWarning
                onInput={handleInput}
                onKeyDown={(e) => {
                    if (e.key === 'Tab') {
                        e.preventDefault();
                        document.execCommand('insertText', false, '    ');
                    }
                }}
                className="px-4 py-3 text-white focus:outline-none"
                style={{
                    minHeight,
                    whiteSpace: 'pre-wrap',
                    lineHeight: '1.7',
                    direction: 'ltr',
                    textAlign: 'left',
                    unicodeBidi: 'plaintext',
                    overflowY: 'auto',
                    maxHeight: '300px',
                    fontSize: '14px',
                    fontFamily: 'system-ui, -apple-system, sans-serif',
                }}
                data-placeholder={placeholder}
                dangerouslySetInnerHTML={{ __html: value || '' }}
            />

            {/* Placeholder styling */}
            <style>{`
                [contenteditable]:empty:before {
                    content: attr(data-placeholder);
                    color: #6b7280;
                    pointer-events: none;
                    direction: ltr;
                    text-align: left;
                }
                [contenteditable] * {
                    direction: ltr !important;
                    unicode-bidi: normal !important;
                }
            `}</style>
        </div>
    );
}