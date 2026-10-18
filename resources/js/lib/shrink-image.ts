/**
 * Scales a picture down in the browser before uploading, so phone photos of several
 * megabytes go up quickly. Pictures the browser cannot read are sent as they are.
 */
export async function shrinkImage(file: File, maxSide = 2048, quality = 0.85): Promise<File> {
    if (!file.type.startsWith('image/') || file.type === 'image/gif') return file;
    try {
        const bitmap = await createImageBitmap(file, { imageOrientation: 'from-image' });
        const scale = Math.min(1, maxSide / Math.max(bitmap.width, bitmap.height));
        if (scale === 1 && file.size < 1.5 * 1024 * 1024) return file;
        const canvas = document.createElement('canvas');
        canvas.width = Math.round(bitmap.width * scale);
        canvas.height = Math.round(bitmap.height * scale);
        const context = canvas.getContext('2d')!;
        context.fillStyle = '#fff';
        context.fillRect(0, 0, canvas.width, canvas.height);
        context.drawImage(bitmap, 0, 0, canvas.width, canvas.height);
        const blob = await new Promise<Blob | null>((resolve) => canvas.toBlob(resolve, 'image/jpeg', quality));
        return blob ? new File([blob], file.name.replace(/\.\w+$/, '') + '.jpg', { type: 'image/jpeg' }) : file;
    } catch {
        return file;
    }
}

/** A still from an uploaded video, used as its picture in lists. */
export function videoFrame(file: File): Promise<File | null> {
    return new Promise((resolve) => {
        const video = document.createElement('video');
        const url = URL.createObjectURL(file);
        const done = (result: File | null) => {
            URL.revokeObjectURL(url);
            resolve(result);
        };
        video.muted = true;
        video.preload = 'metadata';
        video.src = url;
        video.onloadeddata = () => (video.currentTime = Math.min(1, video.duration / 3 || 0));
        video.onseeked = () => {
            const canvas = document.createElement('canvas');
            const scale = Math.min(1, 960 / video.videoWidth);
            canvas.width = Math.round(video.videoWidth * scale);
            canvas.height = Math.round(video.videoHeight * scale);
            canvas.getContext('2d')!.drawImage(video, 0, 0, canvas.width, canvas.height);
            canvas.toBlob((blob) => done(blob ? new File([blob], 'thumb.jpg', { type: 'image/jpeg' }) : null), 'image/jpeg', 0.8);
        };
        video.onerror = () => done(null);
        setTimeout(() => done(null), 8000);
    });
}
