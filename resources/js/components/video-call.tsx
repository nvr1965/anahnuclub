import { UserAvatar } from '@/components/user-avatar';
import { useT } from '@/lib/i18n';
import { type Person, type SharedData } from '@/types';
import { usePage } from '@inertiajs/react';
import { Mic, MicOff, Phone, PhoneOff, SwitchCamera, Video, VideoOff } from 'lucide-react';
import { useCallback, useEffect, useRef, useState } from 'react';

export interface CallInfo {
    id: number;
    conversation_id: number;
    status: 'ringing' | 'active' | 'ended' | 'declined' | 'missed';
    video: boolean;
    outgoing: boolean;
    with: Person;
    answered_at: string | null;
    ice: RTCIceServer[];
}

type Phase = 'incoming' | 'outgoing' | 'connecting' | 'connected' | 'over';

/** Asks the call window to call the other member of a chat. */
export function startCall(conversationId: number, video: boolean, name: string) {
    window.dispatchEvent(new CustomEvent('club:call', { detail: { conversationId, video, name } }));
}

/** How often an idle page asks whether someone is calling (there is no live connection on shared hosting). */
const RING_POLL_MS = 4000;

/** Laravel's XSRF cookie, fresh after every response (the page's meta token goes stale on sign-in). */
function headers() {
    const xsrf = decodeURIComponent(document.cookie.match(/(?:^|; )XSRF-TOKEN=([^;]*)/)?.[1] ?? '');
    return { Accept: 'application/json', 'Content-Type': 'application/json', 'X-XSRF-TOKEN': xsrf, 'X-Requested-With': 'XMLHttpRequest' };
}

async function api<T>(url: string, method = 'GET', body?: object, keepalive = false): Promise<{ status: number; data: T | null }> {
    try {
        const response = await fetch(url, {
            method,
            credentials: 'same-origin',
            keepalive,
            headers: headers(),
            body: body ? JSON.stringify(body) : undefined,
        });
        return { status: response.status, data: await response.json().catch(() => null) };
    } catch {
        return { status: 0, data: null };
    }
}

/**
 * One call's connection. The browsers talk directly (WebRTC); the offer, answer and network
 * candidates travel through the site, which each side asks for every second until connected.
 */
class CallSession {
    pc: RTCPeerConnection;
    remote = new MediaStream();
    private after = 0;
    private timer = 0;
    private stopped = false;
    private remoteSet = false;
    private queued: RTCIceCandidateInit[] = [];
    private offerAgain = false;
    connected = false;

    constructor(
        public call: CallInfo,
        public local: MediaStream,
        private onChange: (call: CallInfo) => void,
        private onOver: (reason: string) => void,
    ) {
        this.pc = new RTCPeerConnection({ iceServers: call.ice });
        local.getTracks().forEach((track) => this.pc.addTrack(track, local));
        // Even without a camera of its own, the caller still receives the other's picture.
        // (The answering side gets its video line from the caller's offer.)
        if (!local.getVideoTracks().length && call.video && call.outgoing) this.pc.addTransceiver('video', { direction: 'recvonly' });
        this.pc.ontrack = (event) => {
            if (!this.remote.getTracks().includes(event.track)) this.remote.addTrack(event.track);
            this.onChange(this.call);
        };
        this.pc.onicecandidate = (event) => {
            if (event.candidate) this.send('candidate', event.candidate.toJSON());
        };
        this.pc.onconnectionstatechange = () => {
            const state = this.pc.connectionState;
            if (state === 'connected') {
                this.connected = true;
                this.onChange(this.call);
            } else if (state === 'failed') {
                this.hangUp('failed');
            }
        };
    }

    async callOut() {
        const offer = await this.pc.createOffer();
        await this.pc.setLocalDescription(offer);
        await this.send('offer', offer);
        this.poll();
    }

    answerIn() {
        this.poll();
    }

    /**
     * Starts sending a camera that was not on when the call began (answered without video, or an
     * audio call): the picture goes into the call's video line, or a new one, and the connection
     * is offered again so the other side starts showing it.
     */
    async addCamera(track: MediaStreamTrack) {
        this.local.addTrack(track);
        const line = this.pc.getTransceivers().find((t) => t.mid !== null && t.receiver.track.kind === 'video' && !t.sender.track);
        if (line) {
            await line.sender.replaceTrack(track);
            line.direction = 'sendrecv';
        } else {
            this.pc.addTrack(track, this.local);
        }
        await this.offer();
    }

    /** A new offer on a call that is already up; the other side answers it in take(). */
    private async offer() {
        if (this.stopped) return;
        if (this.pc.signalingState !== 'stable') {
            this.offerAgain = true;
            return;
        }
        try {
            const offer = await this.pc.createOffer();
            await this.pc.setLocalDescription(offer);
            await this.send('offer', offer);
        } catch (error) {
            console.warn('call renegotiate', error);
        }
    }

    private send(type: string, payload: object) {
        return api(route('calls.signal', this.call.id), 'POST', { type, payload: JSON.stringify(payload) });
    }

    private poll = async () => {
        if (this.stopped) return;
        const { status, data } = await api<{ call: CallInfo; signals: { id: number; type: string; payload: string }[] }>(
            route('calls.show', { call: this.call.id, after: this.after }),
        );
        if (this.stopped) return;
        if (data?.call) {
            this.call = data.call;
            for (const signal of data.signals) {
                this.after = signal.id;
                await this.take(signal.type, JSON.parse(signal.payload));
            }
            this.onChange(this.call);
            if (!['ringing', 'active'].includes(this.call.status)) return this.finish(this.call.status);
        } else if (status === 404) {
            return this.finish('ended');
        }
        this.timer = window.setTimeout(this.poll, this.connected ? 2500 : 1000);
    };

    private async take(type: string, payload: RTCSessionDescriptionInit & RTCIceCandidateInit) {
        try {
            if (type === 'offer' && !this.call.outgoing && !this.remoteSet) {
                await this.pc.setRemoteDescription(payload);
                await this.ready();
                const answer = await this.pc.createAnswer();
                await this.pc.setLocalDescription(answer);
                await this.send('answer', answer);
            } else if (type === 'offer' && this.remoteSet) {
                // The other side changed what it sends (a camera turned on mid-call).
                if (this.pc.signalingState !== 'stable') {
                    // Both offered at once: the caller's offer wins, the answering side takes it back and offers again after.
                    if (this.call.outgoing) return;
                    await this.pc.setLocalDescription({ type: 'rollback' });
                    this.offerAgain = true;
                }
                await this.pc.setRemoteDescription(payload);
                const answer = await this.pc.createAnswer();
                await this.pc.setLocalDescription(answer);
                await this.send('answer', answer);
            } else if (type === 'answer' && this.call.outgoing && !this.remoteSet) {
                await this.pc.setRemoteDescription(payload);
                await this.ready();
            } else if (type === 'answer' && this.remoteSet && this.pc.signalingState === 'have-local-offer') {
                await this.pc.setRemoteDescription(payload);
            } else if (type === 'candidate') {
                if (this.remoteSet) await this.pc.addIceCandidate(payload);
                else this.queued.push(payload);
            }
        } catch (error) {
            console.warn('call signal', type, error);
        }
        if (this.offerAgain && this.pc.signalingState === 'stable') {
            this.offerAgain = false;
            await this.offer();
        }
    }

    private async ready() {
        this.remoteSet = true;
        for (const candidate of this.queued.splice(0)) await this.pc.addIceCandidate(candidate).catch(() => undefined);
    }

    hangUp(reason = 'ended') {
        if (this.stopped) return;
        api(route('calls.end', this.call.id), 'POST');
        this.finish(reason);
    }

    /** For a page that is closing: keepalive lets the request finish after the page is gone. */
    hangUpOnLeave() {
        api(route('calls.end', this.call.id), 'POST', undefined, true);
    }

    private finish(reason: string) {
        if (this.stopped) return;
        this.stopped = true;
        window.clearTimeout(this.timer);
        this.local.getTracks().forEach((track) => track.stop());
        this.pc.close();
        this.onOver(reason);
    }
}

/** A soft repeating tone while a call rings: two-tone for incoming, a single beep for outgoing. */
function ringTone(incoming: boolean) {
    let context: AudioContext | null = null;
    try {
        context = new AudioContext();
    } catch {
        return () => undefined;
    }
    const ring = () => {
        if (!context || context.state === 'closed') return;
        context.resume().catch(() => undefined);
        const tones = incoming ? [880, 660, 880, 660] : [440];
        tones.forEach((freq, i) => {
            const osc = context!.createOscillator();
            const gain = context!.createGain();
            osc.frequency.value = freq;
            gain.gain.value = incoming ? 0.12 : 0.06;
            osc.connect(gain).connect(context!.destination);
            const start = context!.currentTime + i * 0.25;
            osc.start(start);
            osc.stop(start + (incoming ? 0.2 : 1));
        });
        if (incoming) navigator.vibrate?.([300, 200, 300]);
    };
    ring();
    const timer = window.setInterval(ring, incoming ? 2500 : 3000);
    return () => {
        window.clearInterval(timer);
        context?.close().catch(() => undefined);
    };
}

function getMedia(video: boolean, facingMode: 'user' | 'environment' = 'user') {
    return navigator.mediaDevices.getUserMedia({
        audio: { echoCancellation: true, noiseSuppression: true },
        video: video ? { facingMode, width: { ideal: 1280 }, height: { ideal: 720 } } : false,
    });
}

/**
 * The call window, mounted once next to the page so a call carries on while the member moves
 * around the club. It rings for incoming calls and starts the ones asked for with startCall().
 */
export function CallRoot() {
    const t = useT();
    const { auth } = usePage<SharedData>().props;
    const userId = auth.user?.id;
    const [call, setCall] = useState<CallInfo | null>(null);
    const [phase, setPhase] = useState<Phase | null>(null);
    const [note, setNote] = useState('');
    const [, rerender] = useState(0);
    const [micOn, setMicOn] = useState(true);
    const [camOn, setCamOn] = useState(true);
    const [facing, setFacing] = useState<'user' | 'environment'>('user');
    const [cameras, setCameras] = useState(0);
    const [since, setSince] = useState<number | null>(null);
    const [now, setNow] = useState(Date.now());
    const session = useRef<CallSession | null>(null);
    const ignored = useRef(new Set<number>());
    const localVideo = useRef<HTMLVideoElement>(null);
    const remoteVideo = useRef<HTMLVideoElement>(null);
    const remoteAudio = useRef<HTMLAudioElement>(null);
    const remotePictures = useRef(0);
    const busy = phase !== null;

    const close = useCallback((text: string) => {
        session.current = null;
        setNote(text);
        setPhase('over');
        window.setTimeout(() => {
            setPhase((p) => (p === 'over' ? null : p));
            setCall((c) => (session.current ? c : null));
        }, 2500);
    }, []);

    const reasonText = useCallback(
        (reason: string, name: string, outgoing: boolean, connectedAt: number | null) => {
            if (reason === 'busy') return t(':name is on another call.', { name });
            if (reason === 'media') return t('No access to the camera or microphone. Allow it in the browser and try again.');
            if (reason === 'failed') return t('Could not connect. Try again, or try another network.');
            if (reason === 'forbidden') return t('You can call :name once they accept your messages.', { name });
            if (reason === 'declined') return t(':name declined the call.', { name });
            if (reason === 'missed') return outgoing ? t(':name did not answer.', { name }) : t('Missed call');
            return connectedAt ? `${t('Call ended')} · ${duration(Date.now() - connectedAt)}` : t('Call ended');
        },
        [t],
    );

    const begin = useCallback(
        (info: CallInfo, local: MediaStream) => {
            let connectedAt: number | null = null;
            const s = new CallSession(
                info,
                local,
                (updated) => {
                    setCall({ ...updated });
                    if (s.connected && connectedAt === null) {
                        connectedAt = Date.now();
                        setSince(connectedAt);
                        setPhase('connected');
                    } else if (updated.status === 'active' && connectedAt === null) {
                        setPhase('connecting');
                    }
                    rerender((n) => n + 1);
                },
                (reason) => close(reasonText(reason, s.call.with.name, s.call.outgoing, connectedAt)),
            );
            session.current = s;
            setCall(info);
            setMicOn(true);
            setCamOn(info.video);
            setFacing('user');
            setSince(null);
            navigator.mediaDevices
                ?.enumerateDevices()
                .then((devices) => setCameras(devices.filter((d) => d.kind === 'videoinput').length))
                .catch(() => undefined);
            return s;
        },
        [close, reasonText],
    );

    // Calls started from a chat.
    useEffect(() => {
        const onCall = async (event: Event) => {
            const { conversationId, video, name } = (event as CustomEvent<{ conversationId: number; video: boolean; name: string }>).detail;
            if (session.current) return;
            setPhase('outgoing');
            setNote('');
            let local: MediaStream;
            try {
                local = await getMedia(video);
            } catch {
                return close(reasonText('media', name, true, null));
            }
            const { status, data } = await api<{ call?: CallInfo; busy?: boolean }>(route('calls.start', conversationId), 'POST', { video });
            if (!data?.call) {
                local.getTracks().forEach((track) => track.stop());
                setCall(null);
                return close(reasonText(status === 409 ? 'busy' : status === 403 ? 'forbidden' : 'failed', name, true, null));
            }
            begin(data.call, local).callOut();
        };
        window.addEventListener('club:call', onCall);
        return () => window.removeEventListener('club:call', onCall);
    }, [begin, close, reasonText]);

    // Is someone calling? Asked now and then while no call is on, and quicker while it rings.
    useEffect(() => {
        if (!userId || (busy && phase !== 'incoming')) return;
        let stop = false;
        const check = async () => {
            if (document.visibilityState !== 'visible') return;
            const { data } = await api<{ call: CallInfo | null }>(route('calls.ringing'));
            if (stop || session.current) return;
            const ringing = data?.call && !ignored.current.has(data.call.id) ? data.call : null;
            if (ringing) {
                setCall(ringing);
                setPhase('incoming');
            } else if (phase === 'incoming') {
                setPhase(null);
                setCall(null);
            }
        };
        check();
        const timer = window.setInterval(check, phase === 'incoming' ? 2000 : RING_POLL_MS);
        return () => {
            stop = true;
            window.clearInterval(timer);
        };
    }, [userId, busy, phase]);

    // Ringing sounds.
    useEffect(() => {
        if (phase === 'incoming') return ringTone(true);
        if (phase === 'outgoing' && call) return ringTone(false);
    }, [phase, call?.id]);

    // Call timer.
    useEffect(() => {
        if (phase !== 'connected') return;
        const timer = window.setInterval(() => setNow(Date.now()), 1000);
        return () => window.clearInterval(timer);
    }, [phase]);

    // Closing the tab hangs up.
    useEffect(() => {
        const leave = () => session.current?.hangUpOnLeave();
        window.addEventListener('pagehide', leave);
        return () => window.removeEventListener('pagehide', leave);
    }, []);

    // Streams into the video and audio elements.
    useEffect(() => {
        const s = session.current;
        if (localVideo.current && s && localVideo.current.srcObject !== s.local) localVideo.current.srcObject = s.local;
        if (remoteVideo.current && s) {
            // A picture that arrives mid-call (the other side turned the camera on) needs the stream set again.
            const pictures = s.remote.getVideoTracks().length;
            if (remoteVideo.current.srcObject !== s.remote || remotePictures.current !== pictures) {
                remoteVideo.current.srcObject = null;
                remoteVideo.current.srcObject = s.remote;
            }
            remotePictures.current = pictures;
        }
        if (remoteAudio.current && s && remoteAudio.current.srcObject !== s.remote) remoteAudio.current.srcObject = s.remote;
    });

    const accept = async (withVideo: boolean) => {
        if (!call) return;
        const info = call;
        setPhase('connecting');
        let local: MediaStream;
        try {
            local = await getMedia(withVideo);
        } catch {
            api(route('calls.decline', info.id), 'POST');
            return close(reasonText('media', info.with.name, false, null));
        }
        const { data } = await api<{ call: CallInfo }>(route('calls.accept', info.id), 'POST');
        if (!data?.call) {
            local.getTracks().forEach((track) => track.stop());
            return close(reasonText('missed', info.with.name, false, null));
        }
        begin(data.call, local).answerIn();
        setCamOn(withVideo);
    };

    const decline = () => {
        if (!call) return;
        ignored.current.add(call.id);
        api(route('calls.decline', call.id), 'POST');
        setPhase(null);
        setCall(null);
    };

    const toggleMic = () => {
        session.current?.local.getAudioTracks().forEach((track) => (track.enabled = !micOn));
        setMicOn(!micOn);
    };

    const toggleCam = async () => {
        const s = session.current;
        if (!s) return;
        if (s.local.getVideoTracks().length) {
            s.local.getVideoTracks().forEach((track) => (track.enabled = !camOn));
            setCamOn(!camOn);
            return;
        }
        // Answered without video, or an audio call: the camera is opened now and offered to the other side.
        let track: MediaStreamTrack | undefined;
        try {
            const stream = await navigator.mediaDevices.getUserMedia({
                audio: false,
                video: { facingMode: 'user', width: { ideal: 1280 }, height: { ideal: 720 } },
            });
            track = stream.getVideoTracks()[0];
        } catch {
            return;
        }
        if (!track) return;
        if (session.current !== s) return track.stop();
        setFacing('user');
        setCamOn(true);
        await s.addCamera(track);
        navigator.mediaDevices
            ?.enumerateDevices()
            .then((devices) => setCameras(devices.filter((d) => d.kind === 'videoinput').length))
            .catch(() => undefined);
        if (localVideo.current) {
            localVideo.current.srcObject = null;
            localVideo.current.srcObject = s.local;
        }
    };

    const flipCamera = async () => {
        const s = session.current;
        if (!s) return;
        const next = facing === 'user' ? 'environment' : 'user';
        const old = s.local.getVideoTracks()[0];
        const oldDevice = old?.getSettings().deviceId;
        // Phones open one camera at a time: the current one is let go before asking for the other.
        old?.stop();
        const video = (constraints: MediaTrackConstraints) =>
            navigator.mediaDevices.getUserMedia({ audio: false, video: { width: { ideal: 1280 }, height: { ideal: 720 }, ...constraints } });
        let stream: MediaStream | null = null;
        try {
            stream = await video({ facingMode: { exact: next } });
        } catch {
            // Some phones do not tell front from back: take the next camera in the list.
            try {
                const cams = (await navigator.mediaDevices.enumerateDevices()).filter((d) => d.kind === 'videoinput');
                const at = cams.findIndex((d) => d.deviceId === oldDevice);
                const other = cams[(at + 1) % cams.length];
                if (other && other.deviceId !== oldDevice) stream = await video({ deviceId: { exact: other.deviceId } });
            } catch {
                stream = null;
            }
        }
        try {
            // Nothing else worked: back to the camera that was on.
            stream ??= await video(oldDevice ? { deviceId: { exact: oldDevice } } : { facingMode: facing });
        } catch {
            return;
        }
        const fresh = stream.getVideoTracks()[0];
        const sender = s.pc.getSenders().find((x) => x.track?.kind === 'video' || x.track === old);
        await sender?.replaceTrack(fresh).catch(() => undefined);
        if (old) s.local.removeTrack(old);
        s.local.addTrack(fresh);
        fresh.enabled = camOn;
        const actual = fresh.getSettings().facingMode;
        setFacing(actual === 'environment' || actual === 'user' ? actual : next);
        // Let the preview pick up the new camera.
        if (localVideo.current) {
            localVideo.current.srcObject = null;
            localVideo.current.srcObject = s.local;
        }
    };

    if (!phase) return null;

    const s = session.current;
    const remoteHasVideo = Boolean(s?.remote.getVideoTracks().some((track) => track.readyState === 'live')) && phase === 'connected';
    const localHasVideo = Boolean(s?.local.getVideoTracks().length) && camOn;
    const control = 'flex size-14 items-center justify-center rounded-full transition';

    if (phase === 'incoming' && call) {
        return (
            <div className="fixed inset-0 z-[70] flex items-center justify-center bg-black/60 p-4" role="dialog" aria-modal="true">
                <div className="w-full max-w-sm rounded-2xl bg-white p-6 text-center shadow-2xl dark:bg-neutral-900">
                    <UserAvatar person={call.with} className="mx-auto size-24 animate-pulse" fallbackClassName="text-3xl" />
                    <div dir="auto" className="mt-3 text-xl font-bold">
                        {call.with.name}
                    </div>
                    <p className="text-muted-foreground mt-1">{call.video ? t('Incoming video call') : t('Incoming audio call')}</p>
                    <div className="mt-6 flex items-start justify-center gap-6">
                        <CallButton label={t('Decline')} onClick={decline} className={`${control} bg-red-600 text-white hover:bg-red-700`}>
                            <PhoneOff className="size-6" />
                        </CallButton>
                        {call.video && (
                            <CallButton
                                label={t('Answer without video')}
                                onClick={() => accept(false)}
                                className={`${control} bg-slate-200 hover:bg-slate-300 dark:bg-neutral-700`}
                            >
                                <Phone className="size-6" />
                            </CallButton>
                        )}
                        <CallButton
                            label={t('Answer')}
                            onClick={() => accept(call.video)}
                            className={`${control} bg-green-600 text-white hover:bg-green-700`}
                        >
                            {call.video ? <Video className="size-6" /> : <Phone className="size-6" />}
                        </CallButton>
                    </div>
                </div>
            </div>
        );
    }

    const status =
        phase === 'over'
            ? note
            : phase === 'connected' && since
              ? duration(now - since)
              : phase === 'outgoing'
                ? call
                    ? t('Calling…')
                    : t('Starting the camera…')
                : t('Connecting…');

    if (phase === 'over' && !note) return null;

    return (
        <div className="fixed inset-0 z-[70] flex flex-col bg-neutral-950 text-white" role="dialog" aria-modal="true">
            <audio ref={remoteAudio} autoPlay />
            <video
                ref={remoteVideo}
                autoPlay
                playsInline
                muted
                className={`absolute inset-0 size-full object-cover md:object-contain ${remoteHasVideo ? '' : 'hidden'}`}
            />
            {!remoteHasVideo && (
                <div className="flex flex-1 flex-col items-center justify-center gap-3 p-6 text-center">
                    {call && <UserAvatar person={call.with} className="size-28" fallbackClassName="text-4xl" />}
                    {call && (
                        <div dir="auto" className="text-2xl font-bold">
                            {call.with.name}
                        </div>
                    )}
                    <div className="text-white/70">{status}</div>
                </div>
            )}
            {remoteHasVideo && call && (
                <div className="relative z-10 bg-gradient-to-b from-black/60 to-transparent p-4">
                    <div dir="auto" className="font-semibold">
                        {call.with.name}
                    </div>
                    <div className="text-sm text-white/70">{status}</div>
                </div>
            )}
            <video
                ref={localVideo}
                autoPlay
                playsInline
                muted
                className={`absolute end-4 bottom-40 z-10 w-28 rounded-xl object-cover shadow-lg ring-2 ring-white/30 sm:w-44 ${facing === 'user' ? '-scale-x-100' : ''} ${localHasVideo && phase !== 'over' ? '' : 'hidden'}`}
            />
            {phase !== 'over' && (
                <div className="relative z-10 mt-auto flex items-start justify-center gap-4 bg-gradient-to-t from-black/70 to-transparent p-6 pb-8">
                    <CallButton
                        label={micOn ? t('Mute') : t('Unmute')}
                        onClick={toggleMic}
                        className={`${control} ${micOn ? 'bg-white/15 hover:bg-white/25' : 'bg-white text-neutral-900'}`}
                    >
                        {micOn ? <Mic className="size-6" /> : <MicOff className="size-6" />}
                    </CallButton>
                    {(Boolean(s?.local.getVideoTracks().length) || phase === 'connected') && (
                        <CallButton
                            label={camOn ? t('Turn camera off') : t('Turn camera on')}
                            onClick={toggleCam}
                            className={`${control} ${camOn ? 'bg-white/15 hover:bg-white/25' : 'bg-white text-neutral-900'}`}
                        >
                            {camOn ? <Video className="size-6" /> : <VideoOff className="size-6" />}
                        </CallButton>
                    )}
                    {cameras > 1 && Boolean(s?.local.getVideoTracks().length) && (
                        <CallButton label={t('Switch camera')} onClick={flipCamera} className={`${control} bg-white/15 hover:bg-white/25`}>
                            <SwitchCamera className="size-6" />
                        </CallButton>
                    )}
                    <CallButton
                        label={t('Hang up')}
                        onClick={() => (s ? s.hangUp() : close(''))}
                        className={`${control} bg-red-600 hover:bg-red-700`}
                    >
                        <PhoneOff className="size-6" />
                    </CallButton>
                </div>
            )}
        </div>
    );
}

function CallButton({ label, onClick, className, children }: { label: string; onClick: () => void; className: string; children: React.ReactNode }) {
    return (
        <div className="flex w-20 flex-col items-center gap-1.5">
            <button type="button" onClick={onClick} aria-label={label} title={label} className={className}>
                {children}
            </button>
            <span className="text-center text-xs leading-tight opacity-80">{label}</span>
        </div>
    );
}

function duration(ms: number) {
    const total = Math.max(0, Math.floor(ms / 1000));
    const h = Math.floor(total / 3600);
    const m = Math.floor((total % 3600) / 60);
    const s = String(total % 60).padStart(2, '0');
    return h ? `${h}:${String(m).padStart(2, '0')}:${s}` : `${m}:${s}`;
}
