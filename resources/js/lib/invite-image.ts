/**
 * Draws the invitation picture a shared link shows (1200×630) from a language's interface texts:
 * the slogan, three lines on what the club offers, a "join" button, and a post and a chat with a
 * translated message beside them. Right-to-left languages get the whole picture mirrored.
 * The admin Languages page draws it for every language that has none and uploads it
 * (app/Support/InviteImage.php keeps the list of texts).
 */

export interface InviteTexts {
    slogan: string;
    b1: string;
    b2: string;
    b3: string;
    cta: string;
    reply: string;
    from_ru: string;
    from_he: string;
    answer: string;
    online: string;
    post: string;
    like: string;
    comment: string;
}

const W = 1200;
const H = 630;
const FONT = "Rubik, 'Noto Sans Arabic', 'Noto Sans', system-ui, sans-serif";

type Ctx = CanvasRenderingContext2D;

const font = (size: number, weight = 400) => `${weight} ${size}px ${FONT}`;

/** Splits a text into lines no wider than max; a word too long for a line (or a language without spaces) is cut by letters. */
function wrap(ctx: Ctx, text: string, max: number): string[] {
    const lines: string[] = [];
    let line = '';
    for (const word of text.split(/\s+/).filter(Boolean)) {
        const next = line ? `${line} ${word}` : word;
        if (ctx.measureText(next).width <= max) {
            line = next;
            continue;
        }
        if (line) lines.push(line);
        line = '';
        if (ctx.measureText(word).width <= max) {
            line = word;
            continue;
        }
        for (const ch of Array.from(word)) {
            if (ctx.measureText(line + ch).width > max && line) {
                lines.push(line);
                line = '';
            }
            line += ch;
        }
    }
    if (line) lines.push(line);
    return lines;
}

function roundRect(ctx: Ctx, x: number, y: number, w: number, h: number, r: number) {
    ctx.beginPath();
    ctx.roundRect(x, y, w, h, r);
}

function circle(ctx: Ctx, x: number, y: number, r: number, color: string) {
    ctx.beginPath();
    ctx.arc(x, y, r, 0, Math.PI * 2);
    ctx.fillStyle = color;
    ctx.fill();
}

/**
 * Writes a line starting at the logical start x of a box: its left edge in left-to-right
 * languages, its right edge in right-to-left ones. `box` is the box's left and width.
 */
function say(ctx: Ctx, text: string, rtl: boolean, box: [number, number], start: number, y: number) {
    ctx.direction = rtl ? 'rtl' : 'ltr';
    ctx.textAlign = 'start';
    ctx.fillText(text, rtl ? box[0] + box[1] - start : box[0] + start, y);
}

function loadImage(src: string): Promise<HTMLImageElement | null> {
    return new Promise((resolve) => {
        const img = new Image();
        img.onload = () => resolve(img);
        img.onerror = () => resolve(null);
        img.src = src;
    });
}

/** Draws the picture onto the canvas; the logo is loaded from the site. */
export async function drawInvite(
    canvas: HTMLCanvasElement,
    code: string,
    t: InviteTexts,
    rtl: boolean,
    logoUrl = '/images/logo-mark.png',
): Promise<void> {
    canvas.width = W;
    canvas.height = H;
    const ctx = canvas.getContext('2d')!;
    ctx.textBaseline = 'alphabetic';
    const fonts = (document as Document & { fonts?: FontFaceSet }).fonts;
    if (fonts) {
        const sample = [t.slogan, t.b1, t.post].join(' ');
        await Promise.all([400, 500, 600, 700].map((w) => fonts.load(font(20, w), sample).catch(() => null)));
    }
    const logo = await loadImage(logoUrl);
    // x of a box of width w in the mirrored picture
    const mx = (x: number, w = 0) => (rtl ? W - x - w : x);

    // Background: a blue-violet gradient and two soft lights.
    const bg = ctx.createLinearGradient(0, 0, W, H);
    bg.addColorStop(0, '#0e6f88');
    bg.addColorStop(0.55, '#2b5fb8');
    bg.addColorStop(1, '#6d4ad8');
    ctx.fillStyle = bg;
    ctx.fillRect(0, 0, W, H);
    for (const [x, y, r, color] of [
        [130, 90, 330, '125,211,252'],
        [950, 600, 300, '196,181,253'],
    ] as const) {
        const glow = ctx.createRadialGradient(mx(x), y, 0, mx(x), y, r);
        glow.addColorStop(0, `rgba(${color},0.35)`);
        glow.addColorStop(1, `rgba(${color},0)`);
        ctx.fillStyle = glow;
        ctx.fillRect(0, 0, W, H);
    }

    // The text column: logo and name, slogan, three lines and the button.
    const col: [number, number] = [mx(56, 488), 488];
    ctx.fillStyle = '#fff';
    roundRect(ctx, mx(56, 64), 64, 64, 64, 16);
    ctx.fill();
    if (logo) ctx.drawImage(logo, mx(56, 64) + 6, 70, 52, 52);
    ctx.font = font(34, 700);
    ctx.fillStyle = '#fff';
    // The site's name is Latin in every language, so it reads left to right next to the logo.
    ctx.direction = 'ltr';
    ctx.textAlign = rtl ? 'right' : 'left';
    ctx.fillText('anahnu.club', rtl ? W - 56 - 64 - 16 : 56 + 64 + 16, 108);

    const ctaTop = 506;
    let size = t.slogan.length > 30 || rtl ? 46 : 54;
    let list = 23;
    let slogan: string[] = [];
    let bullets: string[][] = [];
    // Long texts shrink until the column fits above the button.
    for (;;) {
        ctx.font = font(size, 700);
        slogan = wrap(ctx, t.slogan, 488);
        ctx.font = font(list);
        bullets = [t.b1, t.b2, t.b3].map((b) => wrap(ctx, b, 488 - 40));
        const height = 168 + slogan.length * size * 1.08 + 30 + bullets.flat().length * list * 1.5 + bullets.length * 12;
        if (height <= ctaTop - 20 || size <= 28) break;
        size -= 2;
        list = Math.max(17, list - 1);
    }
    let y = 168;
    ctx.fillStyle = '#fff';
    ctx.font = font(size, 700);
    for (const line of slogan) {
        y += size * 1.08;
        say(ctx, line, rtl, col, 0, y - size * 0.2);
    }
    y += 30;
    for (const lines of bullets) {
        y += 6;
        const first = y + list * 0.75;
        circle(ctx, rtl ? col[0] + col[1] - 14 : col[0] + 14, first - list * 0.35, 14, 'rgba(255,255,255,0.2)');
        ctx.font = font(16, 700);
        ctx.textAlign = 'center';
        ctx.fillStyle = '#fff';
        ctx.fillText('✓', rtl ? col[0] + col[1] - 14 : col[0] + 14, first - list * 0.35 + 6);
        ctx.font = font(list);
        ctx.fillStyle = 'rgba(255,255,255,0.95)';
        for (const line of lines) {
            y += list * 1.5;
            say(ctx, line, rtl, col, 40, y - list * 0.4);
        }
        y += 6;
    }
    ctx.font = font(24, 700);
    ctx.direction = rtl ? 'rtl' : 'ltr';
    const ctaW = Math.min(488, ctx.measureText(t.cta).width + 60);
    ctx.save();
    ctx.shadowColor = 'rgba(0,0,0,0.2)';
    ctx.shadowBlur = 30;
    ctx.shadowOffsetY = 10;
    ctx.fillStyle = '#fff';
    roundRect(ctx, mx(56, ctaW), ctaTop, ctaW, 60, 30);
    ctx.fill();
    ctx.restore();
    ctx.fillStyle = '#0e6f88';
    ctx.textAlign = 'center';
    ctx.fillText(t.cta, mx(56, ctaW) + ctaW / 2, ctaTop + 39, ctaW - 40);

    drawPost(ctx, t, rtl, mx);
    drawChat(ctx, t, rtl, mx, code === 'ru');
}

/** A card turned a little, drawn in its own coordinates (0,0 its top left corner). */
function card(ctx: Ctx, x: number, y: number, w: number, h: number, angle: number, paint: () => void) {
    ctx.save();
    ctx.translate(x + w / 2, y + h / 2);
    ctx.rotate((angle * Math.PI) / 180);
    ctx.translate(-w / 2, -h / 2);
    ctx.save();
    ctx.shadowColor = 'rgba(10,20,60,0.35)';
    ctx.shadowBlur = 60;
    ctx.shadowOffsetY = 30;
    ctx.fillStyle = '#fff';
    roundRect(ctx, 0, 0, w, h, 22);
    ctx.fill();
    ctx.restore();
    roundRect(ctx, 0, 0, w, h, 22);
    ctx.clip();
    paint();
    ctx.restore();
}

function avatar(ctx: Ctx, x: number, y: number, color: string, letters: string) {
    circle(ctx, x + 21, y + 21, 21, color);
    ctx.fillStyle = '#fff';
    ctx.font = font(16, 700);
    ctx.textAlign = 'center';
    ctx.direction = 'ltr';
    ctx.fillText(letters, x + 21, y + 27);
}

function drawPost(ctx: Ctx, t: InviteTexts, rtl: boolean, mx: (x: number, w?: number) => number) {
    const w = 360;
    const box: [number, number] = [16, w - 32];
    ctx.font = font(15);
    const text = wrap(ctx, t.post, box[1]).slice(0, 2);
    const h = 16 + 42 + 12 + 190 + 12 + text.length * 20 + 10 + 32 + 14;
    card(ctx, mx(640, w), 70, w, h, rtl ? 4 : -4, () => {
        const ax = rtl ? w - 16 - 42 : 16;
        avatar(ctx, ax, 16, '#f59e0b', 'MK');
        ctx.fillStyle = '#0f172a';
        ctx.font = font(16, 600);
        ctx.direction = 'ltr';
        ctx.textAlign = rtl ? 'right' : 'left';
        const nx = rtl ? ax - 10 : ax + 52;
        ctx.fillText('Maya K.', nx, 34);
        ctx.fillStyle = '#64748b';
        ctx.font = font(12);
        ctx.fillText('12 min', nx, 52);

        // The picture: sky, sun, sea, green shore and sand.
        const top = 70;
        ctx.save();
        roundRect(ctx, 16, top, w - 32, 190, 14);
        ctx.clip();
        const sky = ctx.createLinearGradient(0, top, 0, top + 190);
        sky.addColorStop(0, '#7cc6f2');
        sky.addColorStop(0.52, '#bfe6fb');
        sky.addColorStop(0.52, '#2a9dbf');
        sky.addColorStop(1, '#1d7fa3');
        ctx.fillStyle = sky;
        ctx.fillRect(16, top, w - 32, 190);
        circle(ctx, rtl ? 16 + (w - 32) * 0.25 : 16 + (w - 32) * 0.75, top + 190 * 0.28, 34, '#ffe08a');
        // Green hills on the shore, then the sand in front of them.
        const shore = top + 190 * 0.7;
        for (const [at, rx, ry, color] of [
            [0.3, 70, 22, '#2e8b57'],
            [0.8, 90, 26, '#3aa36a'],
        ] as const) {
            ctx.fillStyle = color;
            ctx.beginPath();
            ctx.ellipse(16 + (w - 32) * (rtl ? 1 - at : at), shore, rx, ry, 0, 0, Math.PI * 2);
            ctx.fill();
        }
        const sand = ctx.createLinearGradient(0, shore, 0, top + 190);
        sand.addColorStop(0, '#f3d7a1');
        sand.addColorStop(1, '#e9c487');
        ctx.fillStyle = sand;
        ctx.fillRect(16, shore, w - 32, top + 190 - shore);
        ctx.restore();

        let y = top + 190 + 12;
        ctx.fillStyle = '#0f172a';
        ctx.font = font(15);
        for (const line of text) {
            y += 20;
            say(ctx, line, rtl, [0, w], 16, y - 5);
        }
        y += 10;
        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(16, y, w - 32, 1);
        ctx.fillStyle = '#64748b';
        ctx.font = font(13);
        const like = `👍 ${t.like} · 48`;
        say(ctx, like, rtl, [0, w], 16, y + 24);
        say(ctx, `💬 ${t.comment} · 12`, rtl, [0, w], 16 + ctx.measureText(like).width + 18, y + 24);
    });
}

function drawChat(ctx: Ctx, t: InviteTexts, rtl: boolean, mx: (x: number, w?: number) => number, russian: boolean) {
    const w = 330;
    const inner = w - 32;
    // The message comes in another language: Russian, or Hebrew for a Russian reader.
    const original = russian ? 'שלום! מה שלומך?' : 'Привет! Как дела?';
    const from = russian ? t.from_he : t.from_ru;
    ctx.font = font(15);
    const reply = wrap(ctx, t.reply, inner * 0.85 - 28);
    const answer = wrap(ctx, `${t.answer} 🙂`, inner * 0.85 - 28);
    ctx.font = font(11);
    const note = wrap(ctx, `🌐 ${from}`, inner * 0.85 - 28);
    const inH = 20 + 16 + reply.length * 20 + note.length * 15 + 4;
    const outH = 20 + answer.length * 20;
    const h = 71 + 16 + inH + 10 + outH + 16 + 44;

    card(ctx, mx(830, w), 200, w, h, rtl ? -3 : 3, () => {
        const ax = rtl ? w - 16 - 42 : 16;
        avatar(ctx, ax, 14, '#8b5cf6', 'DL');
        circle(ctx, rtl ? ax + 6 : ax + 36, 50, 8, '#fff');
        circle(ctx, rtl ? ax + 6 : ax + 36, 50, 6, '#22c55e');
        const nx = rtl ? ax - 10 : ax + 52;
        ctx.fillStyle = '#0f172a';
        ctx.font = font(16, 600);
        ctx.direction = 'ltr';
        ctx.textAlign = rtl ? 'right' : 'left';
        ctx.fillText('David L.', nx, 33);
        ctx.fillStyle = '#16a34a';
        ctx.font = font(12, 500);
        ctx.direction = rtl ? 'rtl' : 'ltr';
        ctx.textAlign = 'start';
        ctx.fillText(`● ${t.online}`, nx, 51);
        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(0, 70, w, 1);
        ctx.fillStyle = '#f8fafc';
        ctx.fillRect(0, 71, w, h - 71 - 44);

        // The incoming message: the original in grey, its translation, and where it came from.
        // As wide as its widest line, whichever of the three it is.
        const widest = (size: number, lines: string[]) => {
            ctx.font = font(size);
            return Math.max(...lines.map((l) => ctx.measureText(l).width));
        };
        const inW = Math.min(inner * 0.85, Math.max(widest(15, reply), widest(11, note), widest(12, [original])) + 28);
        let y = 71 + 16;
        const inX = rtl ? w - 16 - inW : 16;
        ctx.fillStyle = '#fff';
        roundRect(ctx, inX, y, inW, inH, 18);
        ctx.fill();
        ctx.strokeStyle = '#e2e8f0';
        ctx.stroke();
        const bubble: [number, number] = [inX, inW];
        ctx.fillStyle = '#94a3b8';
        ctx.font = font(12);
        // The original keeps its own direction.
        ctx.direction = russian ? 'rtl' : 'ltr';
        ctx.textAlign = russian ? 'right' : 'left';
        ctx.fillText(original, russian ? inX + inW - 14 : inX + 14, y + 24, inW - 28);
        let ly = y + 26;
        ctx.fillStyle = '#0f172a';
        ctx.font = font(15);
        for (const line of reply) {
            ly += 20;
            say(ctx, line, rtl, bubble, 14, ly - 4);
        }
        ctx.fillStyle = '#94a3b8';
        ctx.font = font(11);
        ly += 4;
        for (const line of note) {
            ly += 15;
            say(ctx, line, rtl, bubble, 14, ly - 3);
        }
        y += inH + 10;

        // The answer, on the other side.
        ctx.font = font(15);
        const outW = Math.min(inner * 0.85, Math.max(...answer.map((l) => ctx.measureText(l).width)) + 28);
        const outX = rtl ? 16 : w - 16 - outW;
        ctx.fillStyle = '#117892';
        roundRect(ctx, outX, y, outW, outH, 18);
        ctx.fill();
        ctx.fillStyle = '#fff';
        let oy = y + 4;
        for (const line of answer) {
            oy += 20;
            say(ctx, line, rtl, [outX, outW], 14, oy);
        }

        ctx.fillStyle = '#e2e8f0';
        ctx.fillRect(0, h - 44, w, 1);
        ctx.fillStyle = '#94a3b8';
        ctx.font = font(14);
        say(ctx, '📎   🎥   …', rtl, [0, w], 16, h - 16);
    });
}

/** The picture as a JPEG file, ready to upload. */
export async function inviteBlob(code: string, t: InviteTexts, rtl: boolean): Promise<Blob> {
    const canvas = document.createElement('canvas');
    await drawInvite(canvas, code, t, rtl);
    return new Promise((resolve, reject) => canvas.toBlob((b) => (b ? resolve(b) : reject(new Error('toBlob'))), 'image/jpeg', 0.88));
}
