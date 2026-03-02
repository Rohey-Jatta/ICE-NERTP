import { useState, useRef } from 'react';

/**
 * PhotoCapture Component - Camera integration for result sheets.
 * 
 * From architecture: resources/js/Components/PhotoCapture.jsx
 * 
 * Features:
 * - Native camera access on tablets
 * - Image compression before upload (slow networks)
 * - Preview before submission
 * - File input fallback
 */
export default function PhotoCapture({ onPhotoCapture, required = true }) {
    const [preview, setPreview] = useState(null);
    const [error, setError] = useState('');
    const fileInputRef = useRef(null);

    const compressImage = async (file) => {
        return new Promise((resolve, reject) => {
            const reader = new FileReader();
            
            reader.onload = (e) => {
                const img = new Image();
                
                img.onload = () => {
                    const canvas = document.createElement('canvas');
                    let width = img.width;
                    let height = img.height;
                    
                    // Resize if too large (max 1920px width)
                    const maxWidth = 1920;
                    if (width > maxWidth) {
                        height = (height * maxWidth) / width;
                        width = maxWidth;
                    }
                    
                    canvas.width = width;
                    canvas.height = height;
                    
                    const ctx = canvas.getContext('2d');
                    ctx.drawImage(img, 0, 0, width, height);
                    
                    // Compress to JPEG (0.8 quality)
                    canvas.toBlob(
                        (blob) => {
                            const compressedFile = new File(
                                [blob],
                                file.name.replace(/\.\w+$/, '.jpg'),
                                { type: 'image/jpeg' }
                            );
                            resolve(compressedFile);
                        },
                        'image/jpeg',
                        0.8
                    );
                };
                
                img.onerror = reject;
                img.src = e.target.result;
            };
            
            reader.onerror = reject;
            reader.readAsDataURL(file);
        });
    };

    const handleFileChange = async (e) => {
        const file = e.target.files?.[0];
        if (!file) return;

        // Validate file type
        if (!file.type.startsWith('image/')) {
            setError('Please select an image file');
            return;
        }

        // Validate file size (max 10MB before compression)
        if (file.size > 10 * 1024 * 1024) {
            setError('Image too large (max 10MB)');
            return;
        }

        try {
            setError('');
            
            // Compress image
            const compressedFile = await compressImage(file);
            
            // Create preview
            const previewUrl = URL.createObjectURL(compressedFile);
            setPreview(previewUrl);
            
            // Convert to base64 for offline storage
            const reader = new FileReader();
            reader.onload = () => {
                onPhotoCapture({
                    file: compressedFile,
                    preview: previewUrl,
                    base64: reader.result,
                    name: compressedFile.name,
                    type: compressedFile.type,
                    size: compressedFile.size,
                });
            };
            reader.readAsDataURL(compressedFile);
            
        } catch (err) {
            setError('Failed to process image. Please try again.');
            console.error('Image processing error:', err);
        }
    };

    const handleCapture = () => {
        fileInputRef.current?.click();
    };

    const handleRemove = () => {
        setPreview(null);
        onPhotoCapture(null);
        if (fileInputRef.current) {
            fileInputRef.current.value = '';
        }
    };

    return (
        <div className="space-y-3">
            <label className="block text-sm font-medium text-gray-700">
                Result Sheet Photo {required && <span className="text-red-500">*</span>}
            </label>
            
            {error && (
                <div className="p-3 bg-red-50 border border-red-200 rounded-md">
                    <p className="text-sm text-red-700">{error}</p>
                </div>
            )}

            {!preview ? (
                <div className="border-2 border-dashed border-gray-300 rounded-lg p-6">
                    <div className="text-center">
                        <svg className="mx-auto h-12 w-12 text-gray-400" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M15 13a3 3 0 11-6 0 3 3 0 016 0z" />
                        </svg>
                        <p className="mt-2 text-sm text-gray-600">
                            Take a photo of the signed result sheet
                        </p>
                        <button
                            type="button"
                            onClick={handleCapture}
                            className="mt-4 inline-flex items-center gap-2 px-4 py-2 bg-blue-900 text-white rounded-md hover:bg-blue-800 transition-colors"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M3 9a2 2 0 012-2h.93a2 2 0 001.664-.89l.812-1.22A2 2 0 0110.07 4h3.86a2 2 0 011.664.89l.812 1.22A2 2 0 0018.07 7H19a2 2 0 012 2v9a2 2 0 01-2 2H5a2 2 0 01-2-2V9z" />
                            </svg>
                            Capture Photo
                        </button>
                        <input
                            ref={fileInputRef}
                            type="file"
                            accept="image/*"
                            capture="environment"
                            onChange={handleFileChange}
                            className="hidden"
                        />
                    </div>
                </div>
            ) : (
                <div className="relative border-2 border-gray-300 rounded-lg overflow-hidden">
                    <img
                        src={preview}
                        alt="Result sheet preview"
                        className="w-full h-auto"
                    />
                    <div className="absolute top-2 right-2 flex gap-2">
                        <button
                            type="button"
                            onClick={handleCapture}
                            className="p-2 bg-blue-900 text-white rounded-md hover:bg-blue-800"
                            title="Retake photo"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                            </svg>
                        </button>
                        <button
                            type="button"
                            onClick={handleRemove}
                            className="p-2 bg-red-600 text-white rounded-md hover:bg-red-700"
                            title="Remove photo"
                        >
                            <svg className="w-5 h-5" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                <path strokeLinecap="round" strokeLinejoin="round" strokeWidth={2} d="M6 18L18 6M6 6l12 12" />
                            </svg>
                        </button>
                    </div>
                </div>
            )}
            
            <p className="text-xs text-gray-500">
                í³¸ Photo will be compressed automatically â€¢ Image quality: High â€¢ Max size: 10MB
            </p>
        </div>
    );
}
