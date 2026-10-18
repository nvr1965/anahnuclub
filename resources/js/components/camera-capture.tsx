import { useT } from '@/lib/i18n';
import { type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { useEffect, useRef, useState } from 'react';

/** Longest clip, so a forgotten recording does not grow past the upload limit. */
const MAX_SECONDS = 5 * 60;

const css = `
.cam-ov{position:fixed;inset:0;background:rgba(0,0,0,.92);display:flex;flex-direction:column;align-items:center;justify-content:center;z-index:9999;padding:16px;box-sizing:border-box}
.cam-x,.cam-flip{position:absolute;top:max(14px,env(safe-area-inset-top));width:44px;height:44px;border-radius:50%;border:none;background:rgba(255,255,255,.16);color:#fff;display:flex;align-items:center;justify-content:center;cursor:pointer}
.cam-x{inset-inline-end:14px}
.cam-flip{inset-inline-start:14px}
.cam-fr{position:relative;width:min(84vw,480px)}
.cam-fr video{display:block;width:100%;max-height:58vh;object-fit:cover;border-radius:12px;background:#000}
.cam-fr video.mirror{transform:scaleX(-1)}
.cam-rec{position:absolute;top:10px;inset-inline-end:10px;background:#d63c3c;color:#fff;padding:4px 10px;border-radius:20px;font-weight:700;font-size:13px;font-variant-numeric:tabular-nums}
.cam-rec b{animation:cam-blink 1s infinite}
.cam-bar{margin-top:22px;display:flex;gap:28px;align-items:center;justify-content:center}
.cam-b{width:64px;height:64px;border-radius:50%;border:3px solid rgba(255,255,255,.85);display:flex;align-items:center;justify-content:center;cursor:pointer;color:#fff;padding:0}
.cam-b:disabled{opacity:.4;cursor:default}
.cam-b.ph{background:#1f7a4f}
.cam-b.vd{background:transparent}
.cam-b.vd i{width:24px;height:24px;border-radius:50%;background:#d63c3c}
.cam-b.st{background:transparent}
.cam-b.st i{width:22px;height:22px;border-radius:4px;background:#d63c3c}
.cam-err{color:#d63c3c;padding:20px;background:#fff;border-radius:8px;max-width:420px;text-align:center}
@keyframes cam-blink{50%{opacity:0}}
`;

/** The format the browser records in: MP4 where it can (Safari, new Chrome), WebM otherwise. */
function recordingType(): string {
    for (const type of ['video/mp4;codecs=avc1,mp4a', 'video/mp4', 'video/webm;codecs=vp9,opus', 'video/webm;codecs=vp8,opus', 'video/webm']) {
        if (typeof MediaRecorder !== 'undefined' && MediaRecorder.isTypeSupported(type)) return type;
    }
    return '';
}

/**
 * Full-screen camera: a still photo or a video with sound, handed back as a file.
 * Without HTTPS browsers do not open the camera; callers then fall back to the phone's own camera.
 */
export function CameraCapture({
    onPhoto,
    onVideo,
    onClose,
    video: allowVideo = true,
}: {
    onPhoto: (file: File) => void;
    onVideo: (file: File) => void;
    onClose: () => void;
    video?: boolean;
}) {
    const t = useT();
    const { dir } = usePage<SharedData>().props;
    const videoRef = useRef<HTMLVideoElement>(null);
    const recorderRef = useRef<MediaRecorder | null>(null);
    const chunksRef = useRef<Blob[]>([]);
    const streamRef = useRef<MediaStream | null>(null);

    const [error, setError] = useState('');
    const [facing, setFacing] = useState<'environment' | 'user'>('environment');
    const [ready, setReady] = useState(false);
    const [recording, setRecording] = useState(false);
    const [seconds, setSeconds] = useState(0);

    useEffect(() => {
        let cancelled = false;
        setReady(false);
        navigator.mediaDevices
            .getUserMedia({ video: { facingMode: facing, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: allowVideo })
            .then((stream) => {
                // Closed while the browser was still asking: let the camera go at once.
                if (cancelled) return stream.getTracks().forEach((track) => track.stop());
                streamRef.current = stream;
                if (videoRef.current) videoRef.current.srcObject = stream;
                setReady(true);
            })
            .catch(() => !cancelled && setError('denied'));

        return () => {
            cancelled = true;
            streamRef.current?.getTracks().forEach((track) => track.stop());
            streamRef.current = null;
        };
    }, [facing, allowVideo]);

    useEffect(() => {
        if (!recording) return;
        const timer = window.setInterval(() => setSeconds((s) => s + 1), 1000);
        return () => window.clearInterval(timer);
    }, [recording]);

    useEffect(() => {
        if (recording && seconds >= MAX_SECONDS) stopRecording();
    });

    useEffect(() => {
        const onKey = (e: KeyboardEvent) => e.key === 'Escape' && !recorderRef.current && onClose();
        window.addEventListener('keydown', onKey);
        return () => window.removeEventListener('keydown', onKey);
    }, [onClose]);

    const takePhoto = () => {
        const video = videoRef.current;
        if (!video || !video.videoWidth) return;
        const canvas = document.createElement('canvas');
        canvas.width = video.videoWidth;
        canvas.height = video.videoHeight;
        const context = canvas.getContext('2d')!;
        // The front camera is shown mirrored; the photo is saved the way others see you.
        context.drawImage(video, 0, 0, canvas.width, canvas.height);
        canvas.toBlob((blob) => blob && onPhoto(new File([blob], `photo-${Date.now()}.jpg`, { type: 'image/jpeg' })), 'image/jpeg', 0.92);
    };

    const startRecording = () => {
        const stream = streamRef.current;
        if (!stream) return;
        const type = recordingType();
        chunksRef.current = [];
        const recorder = new MediaRecorder(stream, { ...(type ? { mimeType: type } : {}), videoBitsPerSecond: 2_500_000 });
        recorder.ondataavailable = (e) => e.data.size > 0 && chunksRef.current.push(e.data);
        recorder.onstop = () => {
            const mime = (recorder.mimeType || type || 'video/webm').split(';')[0];
            const blob = new Blob(chunksRef.current, { type: mime });
            recorderRef.current = null;
            onVideo(new File([blob], `video-${Date.now()}.${mime === 'video/mp4' ? 'mp4' : 'webm'}`, { type: mime }));
        };
        recorder.start(1000);
        recorderRef.current = recorder;
        setSeconds(0);
        setRecording(true);
    };

    const stopRecording = () => {
        recorderRef.current?.stop();
        setRecording(false);
    };

    const time = `${Math.floor(seconds / 60)}:${String(seconds % 60).padStart(2, '0')}`;

    return (
        <div className="cam-ov" dir={dir} role="dialog" aria-modal="true" aria-label={t('Camera')}>
            <style>{css}</style>
            {!recording && (
                <button type="button" className="cam-x" aria-label={t('Close')} title={t('Close')} onClick={onClose}>
                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2.4"
                        strokeLinecap="round"
                        aria-hidden="true"
                    >
                        <path d="M6 6l12 12M18 6L6 18" />
                    </svg>
                </button>
            )}
            {!recording && !error && (
                <button
                    type="button"
                    className="cam-flip"
                    aria-label={t('Switch camera')}
                    title={t('Switch camera')}
                    onClick={() => setFacing(facing === 'environment' ? 'user' : 'environment')}
                >
                    <svg
                        width="22"
                        height="22"
                        viewBox="0 0 24 24"
                        fill="none"
                        stroke="currentColor"
                        strokeWidth="2"
                        strokeLinecap="round"
                        strokeLinejoin="round"
                        aria-hidden="true"
                    >
                        <path d="M4 8h3l2-3h6l2 3h3v11H4z" />
                        <path d="M9.5 13.5a2.5 2.5 0 0 0 4.6 1.2M14.5 12.5a2.5 2.5 0 0 0-4.6-1.2M14.6 15.6v-1.2h-1.2M9.4 10.4v1.2h1.2" />
                    </svg>
                </button>
            )}
            {error ? (
                <div className="cam-err">{t('No access to the camera or microphone.')}</div>
            ) : (
                <div className="cam-fr">
                    <video ref={videoRef} autoPlay playsInline muted className={facing === 'user' ? 'mirror' : ''} />
                    {recording && (
                        <div className="cam-rec">
                            <b>●</b> REC {time}
                        </div>
                    )}
                </div>
            )}

            {!error && (
                <div className="cam-bar">
                    {!recording && (
                        <button type="button" className="cam-b ph" aria-label={t('Photo')} title={t('Photo')} onClick={takePhoto} disabled={!ready}>
                            <svg
                                width="28"
                                height="28"
                                viewBox="0 0 24 24"
                                fill="none"
                                stroke="currentColor"
                                strokeWidth="2"
                                strokeLinejoin="round"
                                aria-hidden="true"
                            >
                                <path d="M4 8h3l2-3h6l2 3h3v11H4z" />
                                <circle cx="12" cy="13" r="3.5" />
                            </svg>
                        </button>
                    )}
                    {allowVideo &&
                        (recording ? (
                            <button type="button" className="cam-b st" aria-label={t('Stop')} title={t('Stop')} onClick={stopRecording}>
                                <i />
                            </button>
                        ) : (
                            <button
                                type="button"
                                className="cam-b vd"
                                aria-label={t('Video')}
                                title={t('Video')}
                                onClick={startRecording}
                                disabled={!ready || !recordingType()}
                            >
                                <i />
                            </button>
                        ))}
                </div>
            )}
        </div>
    );
}

/** Browsers open the camera inside a page only over HTTPS (or on localhost). */
export const cameraAvailable = () => typeof window !== 'undefined' && window.isSecureContext && Boolean(navigator.mediaDevices?.getUserMedia);
