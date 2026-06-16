import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';
import SearchableSelect from '@/Components/SearchableSelect';
import { useNotifications, ToastContainer } from '@/Components/Notifications';

const OBSERVATION_TYPES = [
    { value: 'general',         label: 'General Observation',   color: 'text-iec-pink-600',   icon: '📋' },
    { value: 'positive',        label: 'Positive Observation',  color: 'text-green-500',  icon: '✅' },
    { value: 'process_concern', label: 'Process Concern',       color: 'text-yellow-500',  icon: '⚠️' },
    { value: 'irregularity',    label: 'Irregularity',          color: 'text-orange-500', icon: '🚨' },
    { value: 'incident',        label: 'Incident',              color: 'text-red-500',    icon: '🔴' },
];

const SEVERITIES = [
    { value: 'low',      label: 'Low',      color: 'bg-green-500/20 text-green-500 border-green-500/40' },
    { value: 'medium',   label: 'Medium',   color: 'bg-yellow-500/20 text-yellow-500 border-amber-500/40' },
    { value: 'high',     label: 'High',     color: 'bg-orange-500/20 text-orange-500 border-orange-500/40' },
    { value: 'critical', label: 'Critical', color: 'bg-red-500/20 text-red-500 border-red-500/40' },
];

export default function SubmitObservation({ auth, monitor, stations = [], preselectedStation }) {
    const [photoPreviews, setPhotoPreviews] = useState([]);
    const [documentPreviews, setDocumentPreviews] = useState([]);
    const [locationLoading, setLocationLoading] = useState(false);
    const { toasts, removeNotification, notify } = useNotifications();

    const { data, setData, post, processing, errors, reset } = useForm({
        polling_station_id: preselectedStation || '',
        observation_type:   'general',
        title:              '',
        observation:        '',
        severity:           'low',
        observed_at:        new Date().toISOString().slice(0, 16), // datetime-local format
        is_public:          true,
        latitude:           '',
        longitude:          '',
        photos:             [],
        documents:          [],
    });

    const handlePhotoChange = (e) => {
        const files = Array.from(e.target.files);
        if (files.length > 5) {
            notify.error('Maximum 5 photos allowed.');
            return;
        }
        setData('photos', files);

        // Generate previews
        const previews = [];
        files.forEach(file => {
            const reader = new FileReader();
            reader.onloadend = () => {
                previews.push(reader.result);
                if (previews.length === files.length) {
                    setPhotoPreviews([...previews]);
                }
            };
            reader.readAsDataURL(file);
        });
        if (files.length === 0) setPhotoPreviews([]);
    };

    const removePhoto = (index) => {
        const newPhotos = data.photos.filter((_, i) => i !== index);
        setData('photos', newPhotos);
        setPhotoPreviews(prev => prev.filter((_, i) => i !== index));
    };

    const ALLOWED_DOCUMENT_TYPES = ['application/pdf', 'application/msword', 
        'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'application/vnd.ms-excel',
        'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
        'text/csv', 'text/plain'];
    const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'csv', 'txt'];

    const handleDocumentChange = (e) => {
        const files = Array.from(e.target.files);
        if (files.length > 10) {
            notify.error('Maximum 10 documents allowed.');
            return;
        }

        // Validate file types and sizes
        const validFiles = [];
        const invalidFiles = [];
        
        files.forEach(file => {
            const ext = file.name.split('.').pop().toLowerCase();
            if (!ALLOWED_EXTENSIONS.includes(ext)) {
                invalidFiles.push(`${file.name} (invalid type)`);
                return;
            }
            if (file.size > 10 * 1024 * 1024) { // 10MB limit
                invalidFiles.push(`${file.name} (exceeds 10MB)`);
                return;
            }
            validFiles.push(file);
        });

        if (invalidFiles.length > 0) {
            notify.error(`Invalid files:\n${invalidFiles.join('\n')}`);
        }

        setData('documents', validFiles);

        // Generate previews with file info
        const previews = validFiles.map(file => ({
            name: file.name,
            size: (file.size / 1024 / 1024).toFixed(2),
            ext: file.name.split('.').pop().toUpperCase(),
            type: file.type,
        }));
        setDocumentPreviews(previews);
    };

    const removeDocument = (index) => {
        const newDocuments = data.documents.filter((_, i) => i !== index);
        setData('documents', newDocuments);
        setDocumentPreviews(prev => prev.filter((_, i) => i !== index));
    };

    const handleGetLocation = () => {
        if (!navigator.geolocation) {
            notify.error('Geolocation not supported on this device.');
            return;
        }
        setLocationLoading(true);
        navigator.geolocation.getCurrentPosition(
            (pos) => {
                setData('latitude', pos.coords.latitude.toFixed(8));
                setData('longitude', pos.coords.longitude.toFixed(8));
                setLocationLoading(false);
            },
            () => {
                notify.error('Could not get location. Please enter manually or try again.');
                setLocationLoading(false);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/monitor/observations', {
            forceFormData: true,
            preserveState: false,
            onStart: () => notify.info('Submitting observation...'),
            onSuccess: (page) => {
                notify.success('Observation submitted successfully');
                // Optionally reset previews and form
                setPhotoPreviews([]);
                setDocumentPreviews([]);
                reset();
            },
            onError: (errs) => {
                // errs is an object of field => messages
                const messages = [];
                Object.values(errs || {}).forEach((v) => {
                    if (Array.isArray(v)) messages.push(v[0]);
                    else messages.push(v);
                });
                const message = messages.length ? messages.join(' — ') : 'Failed to submit observation.';
                notify.error(message);
            },
            onFinish: () => {},
        });
    };

    const selectedType = OBSERVATION_TYPES.find(t => t.value === data.observation_type);

    return (
        <AppLayout user={auth?.user}>
            <ToastContainer toasts={toasts} onRemoveToast={removeNotification} />
            
            <div className="container mx-auto px-4 py-8 max-w-3xl">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/monitor/dashboard" className="text-slate-500 hover:text-iec-navy text-sm mb-2 inline-flex items-center gap-1">
                        Back to Monitor Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-iec-navy">Submit Observation</h1>
                    <p className="text-slate-500 mt-1">Record your findings from a polling station visit</p>
                </div>

                {!monitor ? (
                    <div className="bg-red-500/20 border border-red-500/50 rounded-xl p-6 text-red-300">
                        You are not registered as an active election monitor. Contact the IEC Administrator.
                    </div>
                ) : (
                    <>
                        {errors.error && (
                            <div className="mb-4 p-4 rounded-xl border border-red-500/30 bg-red-500/10 text-red-300 text-sm">
                                ⚠ {Array.isArray(errors.error) ? errors.error[0] : errors.error}
                            </div>
                        )}
                        <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Station Selection */}
                        <div className="bg-white rounded-xl p-6 border border-slate-200">
                            <h2 className="text-lg font-bold text-iec-navy mb-4">1. Select Polling Station</h2>
                            {stations.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No polling stations assigned. Contact the IEC Administrator.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <SearchableSelect
                                        value={String(data.polling_station_id)}
                                        onChange={(val) => setData('polling_station_id', val)}
                                        options={stations.map(s => ({ value: String(s.id), label: `${s.code} - ${s.name}` }))}
                                        placeholder="Select a Polling Station"
                                        className="w-full"
                                    />
                                    {errors.polling_station_id && (
                                        <p className="text-red-400 text-sm mt-1">{errors.polling_station_id}</p>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Observation Type & Severity */}
                        <div className="bg-white rounded-xl p-6 border border-slate-200">
                            <h2 className="text-lg font-bold text-iec-navy mb-4">2. Observation Type & Severity</h2>

                            <div className="mb-4">
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Observation Type <span className="text-red-400">*</span>
                                </label>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    {OBSERVATION_TYPES.map(type => (
                                        <label
                                            key={type.value}
                                            className={`flex items-center gap-2 p-3 rounded-lg cursor-pointer border transition-colors ${
                                                data.observation_type === type.value
                                                    ? 'bg-white/60 border-teal-500/50'
                                                    : 'bg-slate-50 border-slate-200 hover:bg-white'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="observation_type"
                                                value={type.value}
                                                checked={data.observation_type === type.value}
                                                onChange={() => setData('observation_type', type.value)}
                                                className="sr-only"
                                            />
                                            <span>{type.icon}</span>
                                            <span className={`text-sm font-medium ${type.color}`}>{type.label}</span>
                                        </label>
                                    ))}
                                </div>
                            </div>

                            <div>
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Severity <span className="text-red-400">*</span>
                                </label>
                                <div className="flex flex-wrap gap-3">
                                    {SEVERITIES.map(sev => (
                                        <label
                                            key={sev.value}
                                            className={`flex items-center gap-2 px-4 py-2 rounded-lg cursor-pointer border transition-colors ${
                                                data.severity === sev.value
                                                    ? sev.color + ' ring-2 ring-white/20'
                                                    : 'bg-slate-50 border-slate-200 text-slate-500 hover:bg-white'
                                            }`}
                                        >
                                            <input
                                                type="radio"
                                                name="severity"
                                                value={sev.value}
                                                checked={data.severity === sev.value}
                                                onChange={() => setData('severity', sev.value)}
                                                className="sr-only"
                                            />
                                            <span className="text-sm font-semibold">{sev.label}</span>
                                        </label>
                                    ))}
                                </div>
                                {errors.severity && <p className="text-red-400 text-sm mt-1">{errors.severity}</p>}
                            </div>
                        </div>

                        {/* Observation Details */}
                        <div className="bg-white rounded-xl p-6 border border-slate-200">
                            <h2 className="text-lg font-bold text-iec-navy mb-4">3. Observation Details</h2>

                            <div className="mb-4">
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Title <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    placeholder={`e.g., ${selectedType?.label || 'Observation'} at station`}
                                    maxLength={255}
                                    required
                                />
                                {errors.title && <p className="text-red-400 text-sm mt-1">{errors.title}</p>}
                            </div>

                            <div className="mb-4">
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Detailed Observation <span className="text-red-400">*</span>
                                </label>
                                <textarea
                                    value={data.observation}
                                    onChange={(e) => setData('observation', e.target.value)}
                                    rows={6}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy resize-none focus:outline-none focus:border-iec-pink-500"
                                    placeholder="Describe your observation in detail. Include time, persons involved, what you witnessed, and any relevant context…"
                                    maxLength={5000}
                                    required
                                />
                                <div className="flex justify-between mt-1">
                                    {errors.observation && <p className="text-red-400 text-sm">{errors.observation}</p>}
                                    <span className="text-slate-500 text-xs ml-auto">{data.observation.length}/5000</span>
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="block text-slate-600 mb-2 font-semibold">
                                    Date & Time of Observation <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.observed_at}
                                    onChange={(e) => setData('observed_at', e.target.value)}
                                    className="w-full px-4 py-3 bg-white border border-slate-200 rounded-lg text-iec-navy"
                                    required
                                />
                                {errors.observed_at && <p className="text-red-400 text-sm mt-1">{errors.observed_at}</p>}
                            </div>

                            {/* Visibility */}
                            <label className="flex items-center gap-3 p-4 bg-slate-50 rounded-lg cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_public}
                                    onChange={(e) => setData('is_public', e.target.checked)}
                                    className="w-5 h-5 text-iec-pink-600 bg-white border-slate-200 rounded"
                                />
                                <div>
                                    <div className="text-iec-navy font-medium">Make this observation public</div>
                                    <div className="text-slate-500 text-sm">Public observations are visible on the results dashboard</div>
                                </div>
                            </label>
                        </div>

                        {/* Photos */}
                        <div className="bg-white rounded-xl p-6 border border-slate-200">
                            <h2 className="text-lg font-bold text-iec-navy mb-4">4. Supporting Photos (Optional)</h2>
                            <p className="text-slate-500 text-sm mb-4">Upload up to 5 photos as evidence. Max 5MB each.</p>

                            {/* Photo upload */}
                            <div className="border-2 border-dashed border-slate-200 rounded-lg p-6 text-center hover:border-teal-500/50 transition-colors">
                                <input
                                    type="file"
                                    accept="image/*"
                                    multiple
                                    onChange={handlePhotoChange}
                                    className="hidden"
                                    id="photo-upload"
                                />
                                <label htmlFor="photo-upload" className="cursor-pointer">
                                    <div className="text-4xl mb-2">📷</div>
                                    <p className="text-iec-navy font-semibold">Click to upload photos</p>
                                    <p className="text-slate-500 text-sm mt-1">PNG, JPG up to 5MB each · Max 5 photos</p>
                                </label>
                            </div>

                            {/* Previews */}
                            {photoPreviews.length > 0 && (
                                <div className="mt-4 grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    {photoPreviews.map((preview, i) => (
                                        <div key={i} className="relative group">
                                            <img
                                                src={preview}
                                                alt={`Photo ${i + 1}`}
                                                className="w-full h-32 object-cover rounded-lg border border-slate-200"
                                            />
                                            <button
                                                type="button"
                                                onClick={() => removePhoto(i)}
                                                className="absolute top-1 right-1 w-6 h-6 bg-red-600 hover:bg-red-700 text-white rounded-full text-xs flex items-center justify-center opacity-0 group-hover:opacity-100 transition-opacity"
                                            >
                                                ×
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {errors.photos && <p className="text-red-400 text-sm mt-2">{errors.photos}</p>}
                        </div>

                        {/* Supporting Documents */}
                        <div className="bg-white rounded-xl p-6 border border-slate-200">
                            <h2 className="text-lg font-bold text-iec-navy mb-4">5. Supporting Documents (Optional)</h2>
                            <p className="text-slate-500 text-sm mb-4">
                                Upload supporting evidence documents. Accepted formats: PDF, DOC, DOCX, XLS, XLSX, CSV, TXT. Max 10MB each, up to 10 files.
                            </p>

                            {/* Document upload */}
                            <div className="border-2 border-dashed border-slate-200 rounded-lg p-6 text-center hover:border-teal-500/50 transition-colors">
                                <input
                                    type="file"
                                    accept=".pdf,.doc,.docx,.xls,.xlsx,.csv,.txt"
                                    multiple
                                    onChange={handleDocumentChange}
                                    className="hidden"
                                    id="document-upload"
                                />
                                <label htmlFor="document-upload" className="cursor-pointer">
                                    <div className="text-4xl mb-2">📄</div>
                                    <p className="text-iec-navy font-semibold">Click to upload documents</p>
                                    <p className="text-slate-500 text-sm mt-1">PDF, DOC, DOCX, XLS, XLSX, CSV, TXT up to 10MB each</p>
                                </label>
                            </div>

                            {/* Document Previews */}
                            {documentPreviews.length > 0 && (
                                <div className="mt-4 space-y-2">
                                    <div className="text-sm font-semibold text-slate-700 mb-3">
                                        {documentPreviews.length} document{documentPreviews.length !== 1 ? 's' : ''} selected
                                    </div>
                                    {documentPreviews.map((doc, i) => (
                                        <div
                                            key={i}
                                            className="flex items-center justify-between p-3 bg-slate-50 rounded-lg border border-slate-200 hover:bg-slate-100 transition-colors"
                                        >
                                            <div className="flex items-center gap-3 flex-1 min-w-0">
                                                <div className="flex-shrink-0 w-8 h-8 bg-slate-200 rounded flex items-center justify-center text-xs font-bold text-slate-600">
                                                    {doc.ext}
                                                </div>
                                                <div className="flex-1 min-w-0">
                                                    <div className="text-sm font-medium text-slate-700 truncate">{doc.name}</div>
                                                    <div className="text-xs text-slate-500">{doc.size} MB</div>
                                                </div>
                                            </div>
                                            <button
                                                type="button"
                                                onClick={() => removeDocument(i)}
                                                className="ml-2 flex-shrink-0 w-6 h-6 text-slate-400 hover:text-red-600 hover:bg-red-50 rounded flex items-center justify-center transition-colors"
                                                title="Remove document"
                                            >
                                                ✕
                                            </button>
                                        </div>
                                    ))}
                                </div>
                            )}
                            {errors.documents && <p className="text-red-400 text-sm mt-2">{errors.documents}</p>}
                        </div>

                        {/* GPS Location */}
                        <div className="bg-white rounded-xl p-6 border border-slate-200">
                            <h2 className="text-lg font-bold text-iec-navy mb-4">6. GPS Location (Optional)</h2>
                            <p className="text-slate-500 text-sm mb-4">
                                Your location helps verify where the observation was made.
                            </p>

                            <div className="flex gap-3 mb-4">
                                <button
                                    type="button"
                                    onClick={handleGetLocation}
                                    disabled={locationLoading}
                                    className="px-4 py-2 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg flex items-center gap-2"
                                >
                                    {locationLoading ? (
                                        <>
                                            <svg className="animate-spin h-4 w-4" viewBox="0 0 24 24" fill="none">
                                                <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                                <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                            </svg>
                                            Getting location…
                                        </>
                                    ) : '📍 Use My Location'}
                                </button>
                                {(data.latitude || data.longitude) && (
                                    <span className="text-iec-pink-600 text-sm flex items-center gap-1">
                                        ✓ Location captured ({parseFloat(data.latitude).toFixed(4)}, {parseFloat(data.longitude).toFixed(4)})
                                    </span>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-slate-500 text-xs mb-1">Latitude</label>
                                    <input
                                        type="number"
                                        step="0.00000001"
                                        value={data.latitude}
                                        onChange={(e) => setData('latitude', e.target.value)}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm font-mono"
                                        placeholder="e.g. 13.4549"
                                    />
                                </div>
                                <div>
                                    <label className="block text-slate-500 text-xs mb-1">Longitude</label>
                                    <input
                                        type="number"
                                        step="0.00000001"
                                        value={data.longitude}
                                        onChange={(e) => setData('longitude', e.target.value)}
                                        className="w-full px-3 py-2 bg-white border border-slate-200 rounded-lg text-iec-navy text-sm font-mono"
                                        placeholder="e.g. -16.5790"
                                    />
                                </div>
                            </div>
                        </div>

                        {/* Submit */}
                        <div className="flex gap-4">
                            <button
                                type="submit"
                                disabled={processing || stations.length === 0}
                                className="flex-1 px-6 py-4 bg-iec-pink-600 hover:bg-iec-pink-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-lg text-lg"
                            >
                                {processing ? (
                                    <span className="flex items-center justify-center gap-2">
                                        <svg className="animate-spin h-5 w-5" viewBox="0 0 24 24" fill="none">
                                            <circle className="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" strokeWidth="4"/>
                                            <path className="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"/>
                                        </svg>
                                        Submitting…
                                    </span>
                                ) : '📝 Submit Observation'}
                            </button>
                            <Link
                                href="/monitor/dashboard"
                                className="px-6 py-4 bg-white hover:bg-slate-100 text-iec-navy font-bold rounded-lg"
                            >
                                Cancel
                            </Link>
                        </div>
                        </form>
                        </>
                    )}
            </div>
        </AppLayout>
    );
}