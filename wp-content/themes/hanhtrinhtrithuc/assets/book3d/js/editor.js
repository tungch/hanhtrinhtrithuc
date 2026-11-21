/*!
 * Trình soạn sách 3D — nhập ảnh bìa, gáy, nội dung dài (tự phân trang); xem trước trực tiếp; xuất book.json.
 * Bản nháp lưu trong IndexedDB của trình duyệt; khung xem trước đọc nó qua index.html?draft=1.
 */
import { drawPage, normalizePage, loadImage, buildPages, dominantColor, bookThickness, autoThickness } from './pages.js';
import { idbGet, idbSet, idbDel } from './store.js';

const $ = (s, r = document) => r.querySelector(s);
const $$ = (s, r = document) => [...r.querySelectorAll(s)];
const base = new URL('book.json', location.href);
const resolve = (u) => (u ? new URL(u, base).href : '');
const getPath = (o, p) => p.split('.').reduce((a, k) => (a == null ? a : a[k]), o);
const setPath = (o, p, v) => {
  const ks = p.split('.');
  let a = o;
  for (const k of ks.slice(0, -1)) a = a[k] ??= {};
  a[ks.at(-1)] = v;
};
const debounce = (fn, ms) => { let t; return (...a) => { clearTimeout(t); t = setTimeout(() => fn(...a), ms); }; };

let state;
let selected = 0;                 // 0 = bìa, n = trang thứ n (đánh số từ 1)
let pages = [];                   // các trang đã tự phân trang từ state.content
const THUMB_W = 156;              // px thật của canvas thumbnail

init().catch((e) => { console.error(e); setStatus('Lỗi: ' + e.message); });

async function init() {
  state = (await idbGet('draft').catch(() => null)) || (await loadDefault());
  normalize(state);
  bindStatic();
  bindCovers();
  bindActions();
  bindContent();
  bindThickness();
  repaginate();
  await savePreview();
  setStatus('Sẵn sàng');
}

async function loadDefault() {
  const r = await fetch('book.json', { cache: 'no-store' });
  if (!r.ok) throw new Error('Không đọc được book.json');
  return r.json();
}

function normalize(s) {
  s.size = { width: 14, height: 20.5, thickness: 2.4, ...(s.size || {}) };
  s.cover ??= {};
  s.cover.color ??= '#4a6a9e';
  s.paperColor ??= '#fbf9f3';
  s.pageNumberStart ??= 1;
  s.cta ??= {};
  s.texts ??= {};
  s.fontScale ??= 1;
  s.autoThickness ??= true;
  s.paperThickness ??= 0.1;
  s.media ??= {};
  if (typeof s.content !== 'string') s.content = pagesToContent(s);
  delete s.pages;
}

/** Chuyển book.json kiểu cũ (từng trang riêng) sang một nội dung dài. */
function pagesToContent(s) {
  const out = [];
  let prevText = false, n = 0;
  for (const raw of s.pages || []) {
    const p = normalizePage(raw);
    if (p.type === 'image') {
      while (s.media['anh' + ++n]);
      s.media['anh' + n] = { src: p.src || '', caption: p.caption || '', fit: p.fit || 'full' };
      out.push(`[ảnh: anh${n}]`);
      prevText = false;
      continue;
    }
    if (p.chapter || p.heading) out.push(`# ${p.chapter ? p.chapter + ' | ' : ''}${p.heading || ''}`.trim());
    else if (prevText) out.push('---');
    out.push(String(p.body || '').trim());
    prevText = true;
  }
  return out.join('\n');
}

const bookRatio = () => state.size.width / state.size.height;

// ================================================================ lưu & xem trước
function setStatus(t) { $('#status').textContent = t; }

function toast(msg) {
  const el = $('#toast');
  el.textContent = msg;
  el.classList.add('show');
  clearTimeout(toast.t);
  toast.t = setTimeout(() => el.classList.remove('show'), 2600);
}

async function savePreview() {
  setStatus('Đang lưu…');
  await idbSet('draft', state);
  const q = selected > 0 ? `&page=${selected}` : '';
  $('#preview').src = `index.html?draft=1${q}&_=${Date.now()}`;
  updateWhere();
  setStatus('Đã lưu bản nháp');
}
const savePreviewSoon = debounce(savePreview, 700);

function changed() {
  setStatus('Có thay đổi…');
  savePreviewSoon();
}

function updateWhere() {
  $('#preview-where').textContent = selected > 0 ? `· đang xem trang ${selected}` : '· bìa sách';
}

/** Chuyển khung xem trước tới trang đang chọn mà không tải lại (nếu được). */
function goPreview() {
  updateWhere();
  const book = $('#preview').contentWindow?.book3d;
  if (!book) return;
  if (selected > 0) (book.jumpTo || book.showPage).call(book, selected);
  else if (book.state === 'open') book.close();
}

function select(n) {
  if (selected === n) return;
  selected = n;
  $$('.pg').forEach((c) => c.classList.toggle('selected', Number(c.dataset.n) === n));
  goPreview();
}

// ================================================================ ô nhập tĩnh
function bindStatic() {
  $$('[data-bind]').forEach((el) => {
    const p = el.dataset.bind;
    el.addEventListener('input', () => {
      let v = el.value;
      if (el.type === 'number') v = el.value === '' ? undefined : parseFloat(el.value);
      setPath(state, p, v);
      if (p.startsWith('size.')) updateRatios();
      if (p.startsWith('size.') || p === 'fontScale') repaginateSoon();
      if (p === 'paperThickness') updateThickness();
      else if (['title', 'author', 'paperColor', 'pageNumberStart'].includes(p)) redrawThumbsSoon();
      changed();
    });
    el.addEventListener('focus', () => { if (p.startsWith('cover.')) select(0); });
  });
  fillStatic();
}

function fillStatic() {
  $$('[data-bind]').forEach((el) => {
    const v = getPath(state, el.dataset.bind);
    el.value = v ?? (el.type === 'color' ? '#ffffff' : '');
  });
  document.documentElement.style.setProperty('--cover-color', state.cover.color);
  updateRatios();
}

function updateRatios() {
  const r = bookRatio();
  document.documentElement.style.setProperty('--ratio', r.toFixed(4));
  const s = state.size;
  const px = (cm) => Math.round(cm * 100);
  $$('.imgfield[data-kind="cover"] .hint-size').forEach((el) => {
    el.textContent = `Gợi ý: ${px(s.width)} × ${px(s.height)} px (tỉ lệ ${s.width} × ${s.height} cm)`;
  });
  $$('.imgfield[data-kind="spine"] .hint-size').forEach((el) => {
    el.textContent = `Gợi ý: ${px(s.thickness)} × ${px(s.height)} px — ảnh dọc, chữ xoay theo gáy`;
  });
  $$('.imgfield[data-kind="spine"] .thumb').forEach((el) => {
    el.style.aspectRatio = 'auto';
    el.style.height = '112px';
    el.style.width = Math.max(6, Math.min(40, (112 * s.thickness) / s.height)).toFixed(1) + 'px';
  });
}

// ================================================================ ô ảnh
async function processImage(file, maxH = 2048) {
  const url = URL.createObjectURL(file);
  try {
    const img = await loadImage(url);
    let w = img.naturalWidth || img.width, h = img.naturalHeight || img.height;
    if (!w || !h) { w = 1400; h = 2050; }
    const k = Math.min(1, maxH / h);
    const cv = document.createElement('canvas');
    cv.width = Math.round(w * k);
    cv.height = Math.round(h * k);
    const ctx = cv.getContext('2d');
    ctx.fillStyle = '#fff';
    ctx.fillRect(0, 0, cv.width, cv.height);
    ctx.drawImage(img, 0, 0, cv.width, cv.height);
    return { dataUrl: cv.toDataURL('image/jpeg', 0.88), w, h };
  } finally {
    URL.revokeObjectURL(url);
  }
}

/**
 * Gắn hành vi cho một ô ảnh: chọn file, kéo thả, ảnh mẫu.
 * opts: { path, expectedRatio(), stretchNote, defaultSrc, onChange }
 */
function setupImgField(el, opts) {
  const img = $('img', el), input = $('input[type=file]', el), warn = $('.warn', el);
  const show = () => {
    const v = getPath(state, opts.path);
    if (v) img.src = resolve(v); else img.removeAttribute('src');
    el.classList.toggle('empty', !v);
  };
  const take = async (file) => {
    if (!file) return;
    if (!file.type.startsWith('image/')) return toast('Vui lòng chọn file ảnh (JPG, PNG, WebP, SVG).');
    setStatus('Đang xử lý ảnh…');
    try {
      const { dataUrl, w, h } = await processImage(file);
      setPath(state, opts.path, dataUrl);
      const exp = opts.expectedRatio();
      const diff = Math.abs(w / h / exp - 1);
      warn.textContent = diff > 0.04
        ? `Ảnh ${w}×${h}px có tỉ lệ khác khổ sách — ảnh sẽ bị ${opts.stretchNote}.`
        : '';
      show();
      opts.onChange?.();
      changed();
    } catch (e) {
      toast('Không đọc được ảnh này.');
      setStatus('Sẵn sàng');
    }
  };
  input.addEventListener('change', () => { take(input.files[0]); input.value = ''; });
  el.addEventListener('dragover', (e) => { e.preventDefault(); el.classList.add('drag'); });
  el.addEventListener('dragleave', () => el.classList.remove('drag'));
  el.addEventListener('drop', (e) => {
    e.preventDefault();
    el.classList.remove('drag');
    take(e.dataTransfer.files[0]);
  });
  $('[data-act="default"]', el)?.addEventListener('click', () => {
    setPath(state, opts.path, opts.defaultSrc || '');
    warn.textContent = '';
    show();
    opts.onChange?.();
    changed();
  });
  show();
}

function bindCovers() {
  $$('.covers .imgfield').forEach((el) => {
    const spine = el.dataset.kind === 'spine';
    setupImgField(el, {
      path: el.dataset.path,
      defaultSrc: el.dataset.default,
      expectedRatio: () => (spine ? state.size.thickness / state.size.height : bookRatio()),
      stretchNote: 'kéo giãn cho vừa',
      onChange: () => { select(0); if (spine) updateEdgeColor(); },
    });
    el.addEventListener('pointerdown', () => select(0));
  });
  updateEdgeColor();
}

/** Màu dự phòng của bìa (khi chưa có ảnh) = màu chủ đạo của ảnh gáy. Viền thật lấy theo mép ảnh. */
async function updateEdgeColor() {
  const src = state.cover.spine;
  let hex = null;
  if (src) {
    try { hex = dominantColor(await loadImage(resolve(src))); } catch { /* giữ màu cũ */ }
  }
  if (hex && src === state.cover.spine) {
    if (hex !== state.cover.color) { state.cover.color = hex; redrawThumbsSoon(); }
  }
  document.documentElement.style.setProperty('--cover-color', state.cover.color);

}

// ================================================================ nội dung dài
function bindContent() {
  const ta = $('#content');
  ta.value = state.content;
  ta.addEventListener('input', () => {
    state.content = ta.value;
    repaginateSoon();
    changed();
  });
  // con trỏ ở đâu → khung xem trước mở tới trang đó
  const follow = debounce(() => {
    if (document.activeElement !== ta) return;
    const n = pageAt(ta.selectionStart);
    if (n) select(n);
  }, 250);
  ['click', 'keyup', 'select'].forEach((ev) => ta.addEventListener(ev, follow));

  $$('[data-ins]').forEach((b) => b.addEventListener('click', () => {
    if (b.dataset.ins === 'chapter') insertLine('# Chương  | Tên chương', 2, 8);
    else insertLine('---');
  }));
  $('#img-insert').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    if (!file.type.startsWith('image/')) return toast('Vui lòng chọn file ảnh.');
    setStatus('Đang xử lý ảnh…');
    try {
      const { dataUrl, w, h } = await processImage(file);
      let n = 0;
      while (state.media['anh' + ++n]);
      const key = 'anh' + n;
      const full = Math.abs(w / h / bookRatio() - 1) < 0.04;   // cùng tỉ lệ khổ sách → phủ kín trang
      state.media[key] = { src: dataUrl, caption: '', fit: full ? 'full' : 'framed' };
      insertLine(`[ảnh: ${key}]`);
      renderMedia();
    } catch {
      toast('Không đọc được ảnh này.');
      setStatus('Sẵn sàng');
    }
  });
}

/** Chèn một dòng riêng tại vị trí con trỏ, chọn đoạn [selA, selB) của dòng vừa chèn (nếu có). */
function insertLine(text, selA, selB) {
  const ta = $('#content');
  const v = ta.value;
  let i = ta.selectionStart ?? v.length;
  if (document.activeElement !== ta && ta.selectionStart === 0 && ta.selectionEnd === 0) i = v.length;
  const before = v.slice(0, i), after = v.slice(i);
  const pre = before && !before.endsWith('\n') ? '\n' : '';
  const post = after.startsWith('\n') ? '' : '\n';
  ta.value = before + pre + text + post + after;
  const start = before.length + pre.length;
  ta.focus();
  if (selA != null) ta.setSelectionRange(start + selA, start + selB);
  else ta.setSelectionRange(start + text.length + post.length, start + text.length + post.length);
  ta.dispatchEvent(new Event('input'));
}

function pageAt(pos) {
  let n = 0;
  for (let i = 0; i < pages.length; i++) if (pages[i].pos <= pos) n = i + 1; else break;
  return n || (pages.length ? 1 : 0);
}

function repaginate() {
  pages = buildPages(state);
  const n = pages.length;
  const words = (state.content.match(/\S+/g) || []).length;
  $('#page-count').textContent = `${n} trang`;
  $('#page-count-2').textContent = n;
  $('#content-stats').textContent = words
    ? `${words.toLocaleString('vi-VN')} chữ · ${n} trang · khổ ${state.size.width} × ${state.size.height} cm`
    : 'Chưa có nội dung.';
  if (selected > n) selected = n;
  updateThickness();
  renderGrid();
  renderMedia();
}
const repaginateSoon = debounce(repaginate, 350);

// ---------------------------------------------------------------- độ dày gáy
function bindThickness() {
  const cb = $('#auto-thick');
  cb.addEventListener('change', () => {
    state.autoThickness = cb.checked;
    updateThickness();
    changed();
  });
}

/** Tự tính độ dày gáy theo số trang (nếu bật) và cập nhật ô nhập + gợi ý ảnh gáy. */
function updateThickness() {
  const auto = autoThickness(state);
  const inp = $('#thickness');
  $('#auto-thick').checked = auto;
  $('#paper-wrap').hidden = !auto;
  inp.readOnly = auto;
  if (auto) {
    const t = bookThickness(pages.length, state.paperThickness);
    if (t !== state.size.thickness) {
      state.size.thickness = t;
      if (document.activeElement !== inp) inp.value = t;
    }
    $('#thick-note').textContent =
      `${pages.length} trang = ${Math.ceil(pages.length / 2)} tờ × ${String(state.paperThickness).replace('.', ',')} mm + 2 bìa → gáy dày ${String(t).replace('.', ',')} cm.`;
  } else {
    $('#thick-note').textContent = 'Đang dùng độ dày nhập tay.';
  }
  updateRatios();
}

// ---------------------------------------------------------------- lưới trang
let thumbObserver;
function renderGrid() {
  const grid = $('#pages');
  thumbObserver?.disconnect();
  thumbObserver = new IntersectionObserver((entries) => {
    for (const e of entries) {
      if (!e.isIntersecting) continue;
      thumbObserver.unobserve(e.target);
      drawThumb(e.target);
    }
  }, { root: window.innerWidth > 900 ? $('.panel') : null, rootMargin: '300px 0px' });

  const want = pages.length + 1;
  while (grid.children.length > want) grid.lastChild.remove();
  while (grid.children.length < want) {
    const b = document.createElement('button');
    b.type = 'button';
    b.className = 'pg';
    b.innerHTML = '<canvas></canvas><span></span>';
    b.addEventListener('click', () => select(Number(b.dataset.n)));
    grid.append(b);
  }
  [...grid.children].forEach((b, n) => {
    b.dataset.n = n;
    b.classList.toggle('cover', n === 0);
    b.classList.toggle('selected', n === selected);
    b.querySelector('span').textContent = n === 0 ? 'Bìa' : String((Number(state.pageNumberStart) || 1) + n - 1);
    b.dataset.dirty = '1';
    thumbObserver.observe(b);
  });
}

async function drawThumb(b) {
  if (b.dataset.dirty !== '1') return;
  b.dataset.dirty = '';
  const n = Number(b.dataset.n);
  const cv = b.querySelector('canvas');
  const w = THUMB_W, h = Math.round(THUMB_W / bookRatio());
  const ctx = cv.getContext('2d');
  if (n === 0) {
    cv.width = w; cv.height = h;
    const src = state.cover.front;
    if (!src) return;
    try { ctx.drawImage(await loadImage(resolve(src)), 0, 0, w, h); } catch { /* giữ màu bìa */ }
    return;
  }
  const page = pages[n - 1];
  if (!page) return;
  const ocv = document.createElement('canvas');
  ocv.width = w; ocv.height = h;
  await drawPage(ocv.getContext('2d'), w, h, page, {
    number: (Number(state.pageNumberStart) || 1) + n - 1,
    isLeft: (n - 1) % 2 === 1,
    // Tiêu đề chạy: trình soạn chưa có ô khai riêng, nên lấy tên tác giả
    // và tên sách như trước.
    headLeft: state.author,
    headRight: state.title,
    paper: state.paperColor,
    resolve,
  });
  if (pages[n - 1] !== page) return;     // đã phân trang lại trong lúc vẽ
  cv.width = w; cv.height = h;
  ctx.drawImage(ocv, 0, 0);
}

const redrawThumbsSoon = debounce(() => {
  $$('.pg').forEach((b) => { b.dataset.dirty = '1'; thumbObserver.observe(b); });
}, 300);

// ---------------------------------------------------------------- ảnh trong nội dung
function renderMedia() {
  const box = $('#media');
  const keys = Object.keys(state.media);
  const used = new Set(pages.filter((p) => p.type === 'image').map((p) => p.src));
  const active = document.activeElement;
  if (active && box.contains(active)) return;   // đang gõ chú thích → không vẽ lại
  box.innerHTML = '';
  for (const key of keys) {
    const md = state.media[key];
    const el = document.createElement('div');
    el.className = 'media-item';
    el.innerHTML = `
      <img alt="">
      <div class="mi-body">
        <div class="mi-head"><code>[ảnh: ${key}]</code>
          ${used.has(md.src) ? '' : '<span class="unused">chưa dùng trong nội dung</span>'}
          <button class="icon-btn danger" type="button" title="Xoá ảnh">✕</button></div>
        <select>
          <option value="full">Phủ kín cả trang</option>
          <option value="framed">Ảnh minh hoạ trên nền giấy</option>
        </select>
        <input placeholder="Chú thích (không bắt buộc)">
      </div>`;
    $('img', el).src = resolve(md.src);
    const sel = $('select', el), cap = $('input', el);
    sel.value = md.fit || 'framed';
    cap.value = md.caption || '';
    cap.hidden = sel.value !== 'framed';
    sel.addEventListener('change', () => { md.fit = sel.value; cap.hidden = md.fit !== 'framed'; repaginateSoon(); changed(); });
    cap.addEventListener('input', () => { md.caption = cap.value; repaginateSoon(); changed(); });
    $('.icon-btn', el).addEventListener('click', () => {
      if (!confirm(`Xoá ảnh ${key} và các dòng [ảnh: ${key}] trong nội dung?`)) return;
      delete state.media[key];
      const re = new RegExp(`^[ \\t]*\\[\\s*(?:ảnh|anh|hình|img)\\s*:\\s*${key}\\s*\\][ \\t]*\\r?\\n?`, 'gmi');
      state.content = state.content.replace(re, '');
      $('#content').value = state.content;
      repaginate();
      changed();
    });
    box.append(el);
  }
}

// ================================================================ nút trên cùng
function bindActions() {
  $('#btn-show-cover').addEventListener('click', () => select(0));

  $('#btn-export').addEventListener('click', () => {
    // bỏ các ảnh không còn dùng trong nội dung
    const used = new Set(pages.filter((p) => p.type === 'image').map((p) => p.src));
    const out = structuredClone(state);
    for (const [k, md] of Object.entries(out.media)) if (!used.has(md.src)) delete out.media[k];
    const json = JSON.stringify(out, null, 2);
    const blob = new Blob([json], { type: 'application/json' });
    const a = document.createElement('a');
    a.href = URL.createObjectURL(blob);
    a.download = 'book.json';
    a.click();
    setTimeout(() => URL.revokeObjectURL(a.href), 2000);
    toast(`Đã tạo book.json (${(blob.size / 1048576).toFixed(1)} MB). Tải file này lên host để thay file cũ.`);
  });

  $('#import-file').addEventListener('change', async (e) => {
    const file = e.target.files[0];
    e.target.value = '';
    if (!file) return;
    try {
      const data = JSON.parse(await file.text());
      if (!data || (typeof data.content !== 'string' && !Array.isArray(data.pages))) throw new Error('thiếu nội dung sách');
      state = data;
      normalize(state);
      reloadForm();
      toast('Đã nhập ' + file.name);
    } catch (err) {
      toast('File không hợp lệ: ' + err.message);
    }
  });

  $('#btn-reset').addEventListener('click', async () => {
    if (!confirm('Bỏ toàn bộ bản nháp và quay về sách mẫu?')) return;
    await idbDel('draft');
    state = await loadDefault();
    normalize(state);
    reloadForm();
    toast('Đã khôi phục sách mẫu');
  });
}

function reloadForm() {
  selected = 0;
  fillStatic();
  $$('.covers .imgfield').forEach((el) => {
    const v = getPath(state, el.dataset.path);
    const img = $('img', el);
    if (v) img.src = resolve(v); else img.removeAttribute('src');
    el.classList.toggle('empty', !v);
    $('.warn', el).textContent = '';
  });
  $('#content').value = state.content;
  repaginate();
  fillStatic();
  updateEdgeColor();
  savePreview();
}
