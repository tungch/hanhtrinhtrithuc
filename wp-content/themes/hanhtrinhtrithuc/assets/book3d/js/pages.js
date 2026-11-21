/*!
 * Vẽ trang sách lên canvas — dùng chung cho sách 3D (book3d.js) và trình soạn (editor.js).
 *
 * Một trang trong book.json có thể là:
 *   "assets/pages/p1.jpg"                                   → ảnh phủ kín trang
 *   { "type": "image", "src": "...", "fit": "full" | "framed", "caption": "..." }
 *   { "type": "text", "chapter": "CHƯƠNG I", "heading": "Tiêu đề", "body": "Đoạn 1\nĐoạn 2",
 *     "align": "justify" | "left" | "center", "valign": "top" | "middle" | "bottom",
 *     "header": true, "number": true }
 */
/**
 * Chữ của ruột sách.
 *
 * Georgia cố ý KHÔNG có trong danh sách: bản Georgia trên Windows thiếu glyph cho
 * những chữ cái tiếng Việt mang hai dấu (ắ, ề, ố, ằ...), nên trình duyệt phải lấy
 * chữ nền từ Georgia rồi chồng dấu từ một font khác — ra "nă´ng" thay vì "nắng".
 * Các font dưới đây đều dựng đủ dấu; Cambria và Constantia có sẵn trên Windows,
 * Palatino trên macOS, Noto Serif trên Linux.
 */
export const SERIF = "Cambria, Constantia, 'Palatino Linotype', Palatino, 'Noto Serif', 'Times New Roman', serif";
const INK = '#2b2a28';
const MUTED = '#6f6a60';

export function normalizePage(p) {
  if (typeof p === 'string') return { type: 'image', src: p, fit: 'full' };
  if (!p || typeof p !== 'object') return { type: 'text', body: '' };
  return { ...p, type: p.type || (p.src ? 'image' : 'text') };
}

export function loadImage(url) {
  return new Promise((ok, fail) => {
    const img = new Image();
    img.decoding = 'async';
    img.onload = () => ok(img);
    img.onerror = () => fail(new Error('Không tải được ảnh: ' + String(url).slice(0, 80)));
    img.src = url;
  });
}

/**
 * Màu chủ đạo của một ảnh (dùng cho cạnh bìa: lấy theo ảnh gáy sách).
 * Lấy nhóm màu xuất hiện nhiều nhất (bỏ qua chữ, hoạ tiết nhỏ) rồi tính trung bình trong nhóm đó.
 */
export function dominantColor(img) {
  const W = 16, H = 128;
  const cv = document.createElement('canvas');
  cv.width = W; cv.height = H;
  const ctx = cv.getContext('2d', { willReadFrequently: true });
  ctx.drawImage(img, 0, 0, W, H);
  const d = ctx.getImageData(0, 0, W, H).data;
  const buckets = new Map();
  for (let i = 0; i < d.length; i += 4) {
    if (d[i + 3] < 128) continue;
    const key = ((d[i] >> 4) << 8) | ((d[i + 1] >> 4) << 4) | (d[i + 2] >> 4);
    const b = buckets.get(key) || [0, 0, 0, 0];
    b[0] += d[i]; b[1] += d[i + 1]; b[2] += d[i + 2]; b[3]++;
    buckets.set(key, b);
  }
  let best = null;
  for (const b of buckets.values()) if (!best || b[3] > best[3]) best = b;
  if (!best) return null;
  const hex = (v) => Math.round(v / best[3]).toString(16).padStart(2, '0');
  return '#' + hex(best[0]) + hex(best[1]) + hex(best[2]);
}

export function paperFill(ctx, w, h, color) {
  ctx.fillStyle = color;
  ctx.fillRect(0, 0, w, h);
  // vân giấy rất nhẹ (cố định theo seed để trang không "nhảy" khi vẽ lại)
  let seed = 7;
  const rnd = () => ((seed = (seed * 16807) % 2147483647) / 2147483647);
  const n = Math.round((w * h) / 900);
  for (let i = 0; i < n; i++) {
    ctx.fillStyle = `rgba(120,100,70,${rnd() * 0.035})`;
    ctx.fillRect(rnd() * w, rnd() * h, 2, 2);
  }
}

function setSpacing(ctx, px) {
  if ('letterSpacing' in ctx) ctx.letterSpacing = `${px}px`;
}

/** Ngắt dòng theo độ rộng thật của chữ. */
function wrapParagraphs(ctx, paragraphs, width, indent) {
  const space = ctx.measureText(' ').width;
  const lines = [];
  paragraphs.forEach((para, pi) => {
    const words = para.split(/\s+/).filter(Boolean);
    if (!words.length) { lines.push({ blank: true, para: pi }); return; }
    let cur = [], curW = 0, first = true;
    const avail = () => width - (first ? indent : 0);
    for (const word of words) {
      const ww = ctx.measureText(word).width;
      if (cur.length && curW + space + ww > avail()) {
        lines.push({ words: cur, indent: first ? indent : 0, last: false, para: pi, width: avail() });
        first = false; cur = [word]; curW = ww;
      } else {
        curW += (cur.length ? space : 0) + ww;
        cur.push(word);
      }
    }
    lines.push({ words: cur, indent: first ? indent : 0, last: true, para: pi, width: avail() });
  });
  return { lines, space };
}

function drawLine(ctx, line, x, y, align, space, boxW) {
  const text = line.words.join(' ');
  if (align === 'center') {
    ctx.textAlign = 'center';
    ctx.fillText(text, x + boxW / 2, y);
    return;
  }
  ctx.textAlign = 'left';
  const x0 = x + line.indent;
  if (align !== 'justify' || line.last || line.words.length < 2) {
    ctx.fillText(text, x0, y);
    return;
  }
  const widths = line.words.map((w) => ctx.measureText(w).width);
  const gap = (line.width - widths.reduce((a, b) => a + b, 0)) / (line.words.length - 1);
  if (gap > space * 3) { ctx.fillText(text, x0, y); return; }
  let cx = x0;
  line.words.forEach((w, i) => { ctx.fillText(w, cx, y); cx += widths[i] + gap; });
}

function wrapCentered(ctx, text, maxW) {
  const words = text.split(/\s+/).filter(Boolean);
  const out = [];
  let cur = '';
  for (const w of words) {
    const t = cur ? cur + ' ' + w : w;
    if (cur && ctx.measureText(t).width > maxW) { out.push(cur); cur = w; } else cur = t;
  }
  if (cur) out.push(cur);
  return out;
}

/**
 * Vẽ một trang.
 * info: { number, isLeft, headLeft, headRight, paper, resolve }
 * headLeft / headRight: tiêu đề chạy ở đầu trang chẵn / trang lẻ. Để trống
 * cả hai thì đầu trang bỏ trắng và số trang chuyển xuống chân trang.
 */
export async function drawPage(ctx, w, h, rawPage, info) {
  const page = normalizePage(rawPage);
  const paper = info.paper || '#fbf9f3';
  const resolve = info.resolve || ((u) => u);
  const mx = w * 0.115;
  ctx.textBaseline = 'alphabetic';

  // ---------------- trang ảnh
  if (page.type === 'image') {
    let img = null;
    try { if (page.src) img = await loadImage(resolve(page.src)); } catch (e) { console.warn('[book3d]', e.message); }
    if (page.fit === 'framed') {
      paperFill(ctx, w, h, paper);
      if (img) {
        const boxW = w - 2 * mx, boxH = h * (page.caption ? 0.7 : 0.78);
        const s = Math.min(boxW / img.width, boxH / img.height);
        const iw = img.width * s, ih = img.height * s;
        const ix = (w - iw) / 2, iy = h * 0.11 + (boxH - ih) / 2;
        ctx.drawImage(img, ix, iy, iw, ih);
        if (page.caption) {
          ctx.font = `${h * 0.0165}px ${SERIF}`;
          ctx.fillStyle = INK;
          setSpacing(ctx, h * 0.0012);
          const lines = wrapCentered(ctx, page.caption.toUpperCase(), w - 2 * mx);
          ctx.textAlign = 'center';
          lines.forEach((l, i) => ctx.fillText(l, w / 2, iy + ih + h * 0.045 + i * h * 0.026));
          setSpacing(ctx, 0);
        }
      } else missing(ctx, w, h, paper);
    } else if (img) {
      // phủ kín trang, giữ tỉ lệ (cắt phần thừa)
      const s = Math.max(w / img.width, h / img.height);
      const iw = img.width * s, ih = img.height * s;
      ctx.drawImage(img, (w - iw) / 2, (h - ih) / 2, iw, ih);
    } else missing(ctx, w, h, paper);
    return;
  }

  // ---------------- trang văn bản
  paperFill(ctx, w, h, paper);
  const f = textFrame(ctx, w, h, page, info, true);
  if (page.flow) {
    drawFlowLines(ctx, w, h, page, f);
  } else {
    const boxW = w - 2 * f.mx;
    const paragraphs = String(page.body || '').replace(/\r/g, '').split('\n');
    const align = page.align || 'justify';
    const baseSize = h * BASE_SIZE;
    let size = baseSize;
    let layout, lh, paraGap, total;
    for (let tries = 0; tries < 30; tries++) {
      ctx.font = `${size}px ${SERIF}`;
      lh = size * LINE_H;
      paraGap = lh * PARA_GAP;
      layout = wrapParagraphs(ctx, paragraphs, boxW, align === 'center' ? 0 : size * INDENT);
      const paraCount = new Set(layout.lines.map((l) => l.para)).size;
      total = layout.lines.length * lh + Math.max(0, paraCount - 1) * paraGap;
      if (f.top + total <= f.bottom || size < h * 0.011) break;
      size *= 0.95;                           // tự thu nhỏ chữ cho vừa trang
    }
    let y0 = f.top + size;
    const free = f.bottom - f.top - total;
    if (page.valign === 'middle') y0 += Math.max(0, free / 2);
    else if (page.valign === 'bottom') y0 += Math.max(0, free);
    ctx.fillStyle = INK;
    ctx.font = `${size}px ${SERIF}`;
    let y = y0, prevPara = -1;
    for (const line of layout.lines) {
      if (prevPara !== -1 && line.para !== prevPara) y += paraGap;
      prevPara = line.para;
      if (!line.blank) drawLine(ctx, line, f.mx, y, align, layout.space, boxW);
      y += lh;
    }
    f.shrink = 1 - size / baseSize;
  }

  // Số trang cuối trang (trang mở chương hoặc trang không có tiêu đề chạy)
  if (f.showNumber && !f.showHeader) {
    ctx.font = `${h * 0.016}px ${SERIF}`;
    ctx.fillStyle = MUTED;
    ctx.textAlign = 'center';
    ctx.fillText(String(info.number), w / 2, h * 0.945);
  }
  return { shrink: f.shrink || 0 };   // >0 nếu chữ đã bị thu nhỏ cho vừa trang
}

// Thông số chữ dùng chung cho vẽ trang và tự phân trang
const BASE_SIZE = 0.0205;   // cỡ chữ so với chiều cao trang
const LINE_H = 1.62;        // giãn dòng
const PARA_GAP = 0.22;      // khoảng cách giữa hai đoạn (theo chiều cao dòng)
const INDENT = 1.6;         // thụt đầu dòng (em)

/**
 * Tính (và nếu draw = true thì vẽ) phần đầu trang: tiêu đề chạy, nhãn chương, tiêu đề chương.
 * Trả về vùng dành cho thân bài { top, bottom, mx, showHeader, showNumber }.
 */
function textFrame(ctx, w, h, page, info, draw) {
  const mx = w * 0.115;
  const isChapter = !!(page.heading || page.chapter);
  // Chưa khai tiêu đề chạy thì đầu trang bỏ trắng hẳn, và số trang tự
  // xuống chân trang - như trang mở chương. Một dải trống chỉ để đựng
  // con số thì phí mất một phần tám chiều cao trang giấy.
  const hasLabel = !!(info.isLeft ? info.headLeft : info.headRight);
  const showHeader = page.header !== false && !isChapter && hasLabel;
  const showNumber = page.number !== false;

  if (showHeader && draw) {
    const headY = h * 0.085;
    const hs = h * 0.0155;
    ctx.fillStyle = MUTED;
    ctx.font = `italic ${hs * 1.05}px ${SERIF}`;
    if (showNumber) {
      ctx.textAlign = info.isLeft ? 'left' : 'right';
      ctx.fillText(String(info.number), info.isLeft ? mx : w - mx, headY);
    }
    const label = (info.isLeft ? info.headLeft : info.headRight) || '';
    if (label) {
      ctx.font = `${hs}px ${SERIF}`;
      setSpacing(ctx, hs * 0.18);
      ctx.textAlign = 'center';
      ctx.fillText(label.toUpperCase(), w / 2, headY);
      setSpacing(ctx, 0);
    }
  }

  // Không có tiêu đề chạy thì thân bài nhích lên, lấy lại khoảng giấy
  // vốn dành cho nó - lề trên vẫn rộng rãi như sách in.
  let top = showHeader || isChapter ? h * 0.125 : h * 0.095;
  if (isChapter) {
    let y = h * 0.2;
    ctx.textAlign = 'center';
    if (page.chapter) {
      const cs = h * 0.0185;
      if (draw) {
        ctx.font = `${cs}px ${SERIF}`;
        ctx.fillStyle = '#7a756b';
        setSpacing(ctx, cs * 0.35);
        ctx.fillText(page.chapter.toUpperCase(), w / 2, y);
        setSpacing(ctx, 0);
      }
      y += h * 0.06;
    }
    if (page.heading) {
      const hs = h * 0.04;
      ctx.font = `${hs}px ${SERIF}`;
      ctx.fillStyle = INK;
      const lines = wrapCentered(ctx, page.heading, w - 2 * mx);
      if (draw) lines.forEach((l, i) => ctx.fillText(l, w / 2, y + i * hs * 1.2));
      y += (lines.length - 1) * hs * 1.2 + h * 0.035;
    }
    if (draw) {
      ctx.strokeStyle = '#a8a294';
      ctx.lineWidth = Math.max(1, h * 0.001);
      ctx.beginPath(); ctx.moveTo(w / 2 - w * 0.06, y); ctx.lineTo(w / 2 + w * 0.06, y); ctx.stroke();
    }
    top = y + h * 0.065;
  }
  const bottom = h * (isChapter && showNumber ? 0.885 : 0.91);
  return { top, bottom, mx, showHeader, showNumber };
}

/** Vẽ các dòng đã được phân trang sẵn (page.flow). Dòng: { t: chữ, s: đầu đoạn, e: cuối đoạn }. */
function drawFlowLines(ctx, w, h, page, f) {
  const size = h * BASE_SIZE * (page.scale || 1);
  const lh = size * LINE_H, gap = lh * PARA_GAP, boxW = w - 2 * f.mx;
  ctx.font = `${size}px ${SERIF}`;
  ctx.fillStyle = INK;
  ctx.textAlign = 'left';
  const space = ctx.measureText(' ').width;
  let y = f.top + size;
  page.lines.forEach((ln, i) => {
    if (ln.s && i > 0) y += gap;
    const indent = ln.s ? size * INDENT : 0;
    const words = ln.t.split(' ');
    const avail = boxW - indent;
    if (ln.e || words.length < 2) {
      ctx.fillText(ln.t, f.mx + indent, y);
    } else {
      const widths = words.map((wd) => ctx.measureText(wd).width);
      let g = (avail - widths.reduce((a, b) => a + b, 0)) / (words.length - 1);
      g = Math.max(g, space * 0.6);
      let x = f.mx + indent;
      words.forEach((wd, k) => { ctx.fillText(wd, x, y); x += widths[k] + g; });
    }
    y += lh;
  });
}

// ================================================================ tự phân trang

const CHAPTER_RE = /^((?:chương|chuong|phần|phan|hồi|quyển|chapter|part)\s+[^\s:.|–—-]+)\s*(?:[:.|–—-]\s*(.*))?$/i;
const BREAK_RE = /^(?:-{3,}|\[\s*ngắt trang\s*\])$/i;
const IMG_RE = /^\[\s*(?:ảnh|anh|hình|img)\s*:\s*([^\]]+?)\s*\]$/i;

/** Tách nội dung dài thành các khối: đoạn văn, tiêu đề chương, ngắt trang, ảnh. */
export function parseContent(text) {
  const blocks = [];
  const re = /[^\n]*(?:\n|$)/g;
  let m;
  while ((m = re.exec(text)) && m[0] !== '') {
    const pos = m.index;
    const raw = m[0].replace(/\r?\n$/, '');
    const line = raw.trim();
    if (!line) continue;
    const lead = pos + raw.indexOf(line[0]);
    let mm;
    if (line.startsWith('#')) {
      const t = line.replace(/^#+\s*/, '');
      let chapter = '', heading = t;
      if (t.includes('|')) [chapter, heading] = t.split('|').map((x) => x.trim());
      else if ((mm = t.match(CHAPTER_RE))) { chapter = mm[1]; heading = (mm[2] || '').trim(); }
      blocks.push({ k: 'h', chapter, heading, pos: lead });
    } else if (BREAK_RE.test(line)) blocks.push({ k: 'br', pos: lead });
    else if ((mm = line.match(IMG_RE))) blocks.push({ k: 'img', key: mm[1], pos: lead });
    else blocks.push({ k: 'p', text: line, pos: lead });
  }
  return blocks;
}

/**
 * Chia nội dung dài thành các trang vừa khổ sách.
 * opts: { ratio (rộng/cao), scale (cỡ chữ), media: { key: { src, caption, fit } } }
 * Mỗi trang có thêm .pos = vị trí ký tự trong nội dung gốc (để trình soạn biết con trỏ ở trang nào).
 */
export function paginate(text, { ratio = 14 / 20.5, scale = 1, media = {} } = {}) {
  const h = 2000, w = Math.round(h * ratio);
  const cv = document.createElement('canvas');
  cv.width = 8; cv.height = 8;
  const ctx = cv.getContext('2d');
  const size = h * BASE_SIZE * scale;
  const lh = size * LINE_H, gap = lh * PARA_GAP;
  const indent = size * INDENT;
  const font = `${size}px ${SERIF}`;
  const cache = new Map();
  const measure = (s) => {
    let v = cache.get(s);
    if (v === undefined) { ctx.font = font; v = ctx.measureText(s).width; cache.set(s, v); }
    return v;
  };

  const pages = [];
  let cur = null, used = 0, avail = 0;
  const open = (head, pos) => {
    cur = { type: 'text', flow: true, scale, lines: [], pos, ...head };
    const f = textFrame(ctx, w, h, cur, {}, false);
    avail = f.bottom - f.top;
    used = 0;
    pages.push(cur);
  };
  const close = () => { cur = null; };

  for (const b of parseContent(String(text || ''))) {
    if (b.k === 'h') {
      open(b.chapter || b.heading ? { chapter: b.chapter, heading: b.heading } : {}, b.pos);
    } else if (b.k === 'br') {
      if (!cur) open({}, b.pos);   // hai lần ngắt liên tiếp → một trang trắng
      close();
    } else if (b.k === 'img') {
      const md = media[b.key] || { src: b.key };
      close();
      pages.push({ type: 'image', src: md.src, fit: md.fit || 'framed', caption: md.caption || '', pos: b.pos });
    } else {
      // ngắt dòng cả đoạn
      const words = [...b.text.matchAll(/\S+/g)];
      const space = measure(' ');
      const lines = [];
      let start = 0, lineW = 0;
      for (let i = 0; i < words.length; i++) {
        const ww = measure(words[i][0]);
        const max = (w - 2 * w * 0.115) - (lines.length === 0 ? indent : 0);
        if (i > start && lineW + space + ww > max) {
          lines.push([start, i]);
          start = i; lineW = ww;
        } else lineW += (i > start ? space : 0) + ww;
      }
      lines.push([start, words.length]);

      lines.forEach(([a, z], li) => {
        const first = li === 0;
        if (!cur) open({}, b.pos + words[a].index);
        let need = lh + (first && cur.lines.length ? gap : 0);
        // tránh để dòng đầu của đoạn nằm một mình cuối trang
        const orphan = first && lines.length > 1 && cur.lines.length && used + need + lh > avail;
        if (used + need > avail || orphan) {
          open({}, b.pos + words[a].index);
          need = lh;
        }
        const ln = { t: words.slice(a, z).map((x) => x[0]).join(' ') };
        if (first) ln.s = 1;
        if (li === lines.length - 1) ln.e = 1;
        cur.lines.push(ln);
        used += need;
      });
    }
  }
  return pages;
}

/**
 * Độ dày gáy sách (cm) tính theo số trang.
 * paperMm: độ dày một tờ giấy (= 2 trang), mặc định 0,1 mm (giấy in sách 70–80 g/m²).
 * Cộng thêm hai bìa (mỗi bìa ~0,3 mm).
 */
export function bookThickness(pageCount, paperMm = 0.1, coverMm = 0.3) {
  const mm = Math.ceil(Math.max(0, pageCount) / 2) * (Number(paperMm) || 0.1) + 2 * coverMm;
  return Math.round(mm) / 10 || 0.1;
}

/** Sách có tự tính độ dày gáy theo số trang không (mặc định: có). */
export const autoThickness = (cfg) => cfg.autoThickness !== false;

/** Danh sách trang của một cuốn sách: tự phân trang từ cfg.content, hoặc dùng cfg.pages (kiểu cũ). */
export function buildPages(cfg) {
  if (typeof cfg.content === 'string') {
    const size = cfg.size || {};
    return paginate(cfg.content, {
      ratio: (size.width || 14) / (size.height || 20.5),
      scale: Number(cfg.fontScale) || 1,
      media: cfg.media || {},
    });
  }
  return (cfg.pages || []).map(normalizePage);
}

function missing(ctx, w, h, paper) {
  paperFill(ctx, w, h, paper);
  ctx.font = `${h * 0.022}px ${SERIF}`;
  ctx.fillStyle = '#aaa';
  ctx.textAlign = 'center';
  ctx.fillText('Chưa có ảnh', w / 2, h / 2);
}
