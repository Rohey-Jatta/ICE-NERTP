import AppLayout from '@/Layouts/AppLayout';
import { useForm, Link } from '@inertiajs/react';
import { useState } from 'react';

const OBSERVATION_TYPES = [
    { value: 'general',         label: 'General Observation',   color: 'text-blue-300',   icon: '📋' },
    { value: 'positive',        label: 'Positive Observation',  color: 'text-green-300',  icon: '✅' },
    { value: 'process_concern', label: 'Process Concern',       color: 'text-amber-300',  icon: '⚠️' },
    { value: 'irregularity',    label: 'Irregularity',          color: 'text-orange-300', icon: '🚨' },
    { value: 'incident',        label: 'Incident',              color: 'text-red-300',    icon: '🔴' },
];

const SEVERITIES = [
    { value: 'low',      label: 'Low',      color: 'bg-green-500/20 text-green-300 border-green-500/40' },
    { value: 'medium',   label: 'Medium',   color: 'bg-amber-500/20 text-amber-300 border-amber-500/40' },
    { value: 'high',     label: 'High',     color: 'bg-orange-500/20 text-orange-300 border-orange-500/40' },
    { value: 'critical', label: 'Critical', color: 'bg-red-500/20 text-red-300 border-red-500/40' },
];

export default function SubmitObservation({ auth, monitor, stations = [], preselectedStation }) {
    const [photoPreviews, setPhotoPreviews] = useState([]);
    const [locationLoading, setLocationLoading] = useState(false);

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
    });

    const handlePhotoChange = (e) => {
        const files = Array.from(e.target.files);
        if (files.length > 5) {
            alert('Maximum 5 photos allowed.');
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

    const handleGetLocation = () => {
        if (!navigator.geolocation) {
            alert('Geolocation not supported on this device.');
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
                alert('Could not get location. Please enter manually or try again.');
                setLocationLoading(false);
            },
            { enableHighAccuracy: true, timeout: 10000 }
        );
    };

    const handleSubmit = (e) => {
        e.preventDefault();
        post('/monitor/observations', { forceFormData: true });
    };

    const selectedType = OBSERVATION_TYPES.find(t => t.value === data.observation_type);

    return (
        <AppLayout user={auth?.user}>
            <div className="container mx-auto px-4 py-8 max-w-3xl">

                {/* Header */}
                <div className="mb-6">
                    <Link href="/monitor/dashboard" className="text-gray-400 hover:text-white text-sm mb-2 inline-flex items-center gap-1">
                        ← Back to Monitor Dashboard
                    </Link>
                    <h1 className="text-3xl font-bold text-white">Submit Observation</h1>
                    <p className="text-gray-400 mt-1">Record your findings from a polling station visit</p>
                </div>

                {!monitor ? (
                    <div className="bg-red-500/20 border border-red-500/50 rounded-xl p-6 text-red-300">
                        You are not registered as an active election monitor. Contact the IEC Administrator.
                    </div>
                ) : (
                    <form onSubmit={handleSubmit} className="space-y-6">

                        {/* Station Selection */}
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <h2 className="text-lg font-bold text-white mb-4">1. Select Polling Station</h2>
                            {stations.length === 0 ? (
                                <div className="p-4 bg-amber-500/10 border border-amber-500/30 rounded-lg">
                                    <p className="text-amber-300 text-sm">
                                        No polling stations assigned. Contact the IEC Administrator.
                                    </p>
                                </div>
                            ) : (
                                <>
                                    <select
                                        value={data.polling_station_id}
                                        onChange={(e) => setData('polling_station_id', e.target.value)}
                                        className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                        required
                                    >
                                        <option value="">— Select a Polling Station —</option>
                                        {stations.map(s => (
                                            <option key={s.id} value={s.id}>{s.code} — {s.name}</option>
                                        ))}
                                    </select>
                                    {errors.polling_station_id && (
                                        <p className="text-red-400 text-sm mt-1">{errors.polling_station_id}</p>
                                    )}
                                </>
                            )}
                        </div>

                        {/* Observation Type & Severity */}
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <h2 className="text-lg font-bold text-white mb-4">2. Observation Type & Severity</h2>

                            <div className="mb-4">
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Observation Type <span className="text-red-400">*</span>
                                </label>
                                <div className="grid grid-cols-2 sm:grid-cols-3 gap-3">
                                    {OBSERVATION_TYPES.map(type => (
                                        <label
                                            key={type.value}
                                            className={`flex items-center gap-2 p-3 rounded-lg cursor-pointer border transition-colors ${
                                                data.observation_type === type.value
                                                    ? 'bg-slate-700/60 border-teal-500/50'
                                                    : 'bg-slate-900/30 border-slate-700/30 hover:bg-slate-800/50'
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
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Severity <span className="text-red-400">*</span>
                                </label>
                                <div className="flex flex-wrap gap-3">
                                    {SEVERITIES.map(sev => (
                                        <label
                                            key={sev.value}
                                            className={`flex items-center gap-2 px-4 py-2 rounded-lg cursor-pointer border transition-colors ${
                                                data.severity === sev.value
                                                    ? sev.color + ' ring-2 ring-white/20'
                                                    : 'bg-slate-900/30 border-slate-700/30 text-gray-400 hover:bg-slate-800/50'
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
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <h2 className="text-lg font-bold text-white mb-4">3. Observation Details</h2>

                            <div className="mb-4">
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Title <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="text"
                                    value={data.title}
                                    onChange={(e) => setData('title', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    placeholder={`e.g., ${selectedType?.label || 'Observation'} at station`}
                                    maxLength={255}
                                    required
                                />
                                {errors.title && <p className="text-red-400 text-sm mt-1">{errors.title}</p>}
                            </div>

                            <div className="mb-4">
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Detailed Observation <span className="text-red-400">*</span>
                                </label>
                                <textarea
                                    value={data.observation}
                                    onChange={(e) => setData('observation', e.target.value)}
                                    rows={6}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white resize-none focus:outline-none focus:border-teal-500"
                                    placeholder="Describe your observation in detail. Include time, persons involved, what you witnessed, and any relevant context…"
                                    maxLength={5000}
                                    required
                                />
                                <div className="flex justify-between mt-1">
                                    {errors.observation && <p className="text-red-400 text-sm">{errors.observation}</p>}
                                    <span className="text-gray-500 text-xs ml-auto">{data.observation.length}/5000</span>
                                </div>
                            </div>

                            <div className="mb-4">
                                <label className="block text-gray-300 mb-2 font-semibold">
                                    Date & Time of Observation <span className="text-red-400">*</span>
                                </label>
                                <input
                                    type="datetime-local"
                                    value={data.observed_at}
                                    onChange={(e) => setData('observed_at', e.target.value)}
                                    className="w-full px-4 py-3 bg-slate-900/50 border border-slate-600 rounded-lg text-white"
                                    required
                                />
                                {errors.observed_at && <p className="text-red-400 text-sm mt-1">{errors.observed_at}</p>}
                            </div>

                            {/* Visibility */}
                            <label className="flex items-center gap-3 p-4 bg-slate-900/30 rounded-lg cursor-pointer">
                                <input
                                    type="checkbox"
                                    checked={data.is_public}
                                    onChange={(e) => setData('is_public', e.target.checked)}
                                    className="w-5 h-5 text-teal-600 bg-slate-900 border-slate-600 rounded"
                                />
                                <div>
                                    <div className="text-white font-medium">Make this observation public</div>
                                    <div className="text-gray-400 text-sm">Public observations are visible on the results dashboard</div>
                                </div>
                            </label>
                        </div>

                        {/* Photos */}
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <h2 className="text-lg font-bold text-white mb-4">4. Supporting Photos (Optional)</h2>
                            <p className="text-gray-400 text-sm mb-4">Upload up to 5 photos as evidence. Max 5MB each.</p>

                            {/* Photo upload */}
                            <div className="border-2 border-dashed border-slate-600 rounded-lg p-6 text-center hover:border-teal-500/50 transition-colors">
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
                                    <p className="text-white font-semibold">Click to upload photos</p>
                                    <p className="text-gray-400 text-sm mt-1">PNG, JPG up to 5MB each · Max 5 photos</p>
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
                                                className="w-full h-32 object-cover rounded-lg border border-slate-600"
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

                        {/* GPS Location */}
                        <div className="bg-slate-800/40 rounded-xl p-6 border border-slate-700/50">
                            <h2 className="text-lg font-bold text-white mb-4">5. GPS Location (Optional)</h2>
                            <p className="text-gray-400 text-sm mb-4">
                                Your location helps verify where the observation was made.
                            </p>

                            <div className="flex gap-3 mb-4">
                                <button
                                    type="button"
                                    onClick={handleGetLocation}
                                    disabled={locationLoading}
                                    className="px-4 py-2 bg-blue-600 hover:bg-blue-700 disabled:opacity-50 text-white text-sm font-semibold rounded-lg flex items-center gap-2"
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
                                    <span className="text-teal-300 text-sm flex items-center gap-1">
                                        ✓ Location captured ({parseFloat(data.latitude).toFixed(4)}, {parseFloat(data.longitude).toFixed(4)})
                                    </span>
                                )}
                            </div>

                            <div className="grid grid-cols-2 gap-4">
                                <div>
                                    <label className="block text-gray-400 text-xs mb-1">Latitude</label>
                                    <input
                                        type="number"
                                        step="0.00000001"
                                        value={data.latitude}
                                        onChange={(e) => setData('latitude', e.target.value)}
                                        className="w-full px-3 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-white text-sm font-mono"
                                        placeholder="e.g. 13.4549"
                                    />
                                </div>
                                <div>
                                    <label className="block text-gray-400 text-xs mb-1">Longitude</label>
                                    <input
                                        type="number"
                                        step="0.00000001"
                                        value={data.longitude}
                                        onChange={(e) => setData('longitude', e.target.value)}
                                        className="w-full px-3 py-2 bg-slate-900/50 border border-slate-600 rounded-lg text-white text-sm font-mono"
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
                                className="flex-1 px-6 py-4 bg-teal-600 hover:bg-teal-700 disabled:opacity-50 disabled:cursor-not-allowed text-white font-bold rounded-lg text-lg"
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
                                className="px-6 py-4 bg-slate-700 hover:bg-slate-600 text-white font-bold rounded-lg"
                            >
                                Cancel
                            </Link>
                        </div>
                    </form>
                )}
            </div>
        </AppLayout>
    );
}