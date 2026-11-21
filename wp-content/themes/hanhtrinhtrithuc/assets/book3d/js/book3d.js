/*!
 * Book3D — sách 3D lật trang (Three.js r160)
 * Cấu hình: book.json (hoặc ?config=duong-dan.json)
 */
import * as THREE from '../vendor/three.module.min.js';
import { drawPage, buildPages, bookThickness, autoThickness, paperFill, loadImage, SERIF } from './pages.js';
import { idbGet } from './store.js';

// ============================================================ tiện ích
const $ = (s) => document.querySelector(s);
const clamp = (v, a, b) => Math.min(b, Math.max(a, v));
const lerp = (a, b, t) => a + (b - a) * t;
const smooth = (a, b, x) => { const t = clamp((x - a) / (b - a), 0, 1); return t * t * (3 - 2 * t); };
const easeInOut = (t) => (t < 0.5 ? 4 * t * t * t : 1 - Math.pow(-2 * t + 2, 3) / 2);
const easeOut = (t) => 1 - Math.pow(1 - t, 3);
const damp = (cur, target, rate, dt) => lerp(cur, target, 1 - Math.exp(-rate * dt));

const SEG_X = 40;        // số đoạn chia theo chiều ngang trang (độ mịn khi cong)
const SEG_Y = 10;        // số đoạn theo chiều dọc (cho hiệu ứng vặn góc)
const UNIT_H = 2;        // chiều cao sách trong không gian 3D
const CLOSED_ROT = 0.5;  // góc xoay khi sách đứng (rad) — thấy gáy bên trái như ảnh mẫu
const OPEN_TILT = -0.035; // nghiêng rất nhẹ khi mở — gần như nhìn thẳng từ trên xuống
const FOV = 18;           // góc nhìn hẹp → ít méo phối cảnh, hai trang đều nhau

const IS_TOUCH = matchMedia('(pointer: coarse)').matches;
const TEX_H = IS_TOUCH ? 1400 : 2048;
const REDUCED = matchMedia('(prefers-reduced-motion: reduce)').matches;
const VIS = 4;            // số tờ hiển thị mỗi bên (các tờ sâu hơn được khối ruột thay thế)
const GUTTER_LINE_SHADE = 0.55; // độ đậm đường kẻ giữa hai trang (0 = đen, 1 = không có)
const KEEP = 6;          // giữ texture cho các tờ cách tờ đang mở tối đa KEEP tờ

// ============================================================ khởi động
const params = new URLSearchParams(location.search);
const configUrl = new URL(params.get('config') || 'book.json', location.href);
const resolve = (p) => (p ? new URL(p, configUrl).href : null);

// ?embed=1 → sách nằm trong iframe của một trang khác (ví dụ popup đặt in).
// Trang chứa đã có nút mua riêng, nên khối mời mua của sách phải tắt đi,
// và trang chứa cần biết sách đang gập hay đang mở để xử lý phím Esc.
const EMBED = params.has('embed');
if (EMBED) document.documentElement.dataset.embed = '1';

/** Nhắn cho trang chứa iframe. Không làm gì khi sách chạy một mình. */
function tell(type, extra) {
  if (!EMBED || window.parent === window) return;
  try {
    window.parent.postMessage({ source: 'book3d', type, ...extra }, '*');
  } catch (e) { /* trang chứa khác tên miền và đã chặn: không sao */ }
}

main().catch((err) => {
  console.error('[book3d]', err);
  $('#loader-text').textContent = 'Không tải được sách. Kiểm tra lại book.json.';
  tell('failed', { error: String(err && err.message || err) });
});

async function main() {
  // ?draft=1 → dùng bản nháp từ trình soạn (editor.html), cùng tên miền
  let cfg = params.has('draft') ? await idbGet('draft').catch(() => null) : null;
  if (!cfg) {
    cfg = await fetch(configUrl, { credentials: 'same-origin' }).then((r) => {
      if (!r.ok) throw new Error('Không đọc được ' + configUrl);
      return r.json();
    });
  }
  applyTheme(cfg);
  if (!hasWebGL()) {
    tell('failed', { error: 'no-webgl' });
    return fallback2D(cfg);
  }
  const app = (window.book3d = new BookApp(cfg));
  await app.init();

  // Trang chứa gập sách lại khi người đọc đóng popup, để lần mở sau
  // luôn bắt đầu từ bìa thay vì giữa trang đang đọc dở. Gập tức thì khi
  // được yêu cầu: iframe lúc đó đã khuất, hiệu ứng chẳng ai thấy.
  window.addEventListener('message', (e) => {
    if (e.source !== window.parent || !e.data || e.data.target !== 'book3d') return;
    if (e.data.type === 'close') app.close(!!e.data.instant);
  });

  tell('ready', { state: app.state, pages: app.N });
}

function applyTheme(cfg) {
  const stage = $('#stage');
  const bg = cfg.background || {};
  if (bg.color) {
    // Đặt lên <html> chứ không lên #stage: html và body cũng cần màu này,
    // mà biến khai trên #stage thì chúng không với tới được.
    document.documentElement.style.setProperty('--bg-color', bg.color);
    // Nền mặc định là một dải màu chuyển nằm ở lớp background-image, phủ kín
    // lớp màu bên dưới. Đặt màu phẳng mà không tắt nó thì màu không hiện ra.
    if (!bg.image) stage.style.setProperty('--bg-image', 'none');
    document.documentElement.dataset.bg = '1';
  }
  if (bg.image) stage.style.setProperty('--bg-image', `url("${resolve(bg.image)}")`);
  const t = cfg.texts || {};
  if (t.hint) $('#hint-text').textContent = t.hint;
  if (t.loading) $('#loader-text').textContent = t.loading;
  const cta = cfg.cta || {};
  $('#cta-title').textContent = cta.title || 'Bạn đã đọc hết phần đọc thử';
  $('#cta-text').textContent = cta.text || '';
  const btn = $('#cta-btn');
  if (cta.url) {
    btn.href = cta.url;
    btn.target = cta.target || '_top';
    btn.textContent = cta.button || 'Mua sách';
  } else btn.remove();
  if (cfg.title) document.title = `${cfg.title} – Đọc thử`;
}

function hasWebGL() {
  try {
    const c = document.createElement('canvas');
    return !!(window.WebGLRenderingContext && (c.getContext('webgl2') || c.getContext('webgl')));
  } catch { return false; }
}

// ============================================================ texture
function makeCanvas(w, h) {
  const cv = document.createElement('canvas');
  cv.width = Math.round(w);
  cv.height = Math.round(h);
  return [cv, cv.getContext('2d')];
}

function centerText(ctx, lines, w, y0, gap) {
  ctx.textAlign = 'center';
  lines.forEach(([text, font, color], i) => {
    ctx.font = font;
    ctx.fillStyle = color;
    ctx.fillText(text, w / 2, y0 + i * gap);
  });
}

// ============================================================ vật liệu
/** Vật liệu giấy có bóng tối ở gáy (gutter) — như sách thật khi mở. */
function paperMaterial(map, { side = THREE.FrontSide, invert = false, color = 0xffffff } = {}) {
  const m = new THREE.MeshStandardMaterial({ map, color, roughness: 0.9, metalness: 0, side });
  m.onBeforeCompile = (sh) => {
    sh.vertexShader = 'varying float vGut;\n' + sh.vertexShader.replace(
      '#include <uv_vertex>',
      `#include <uv_vertex>\n  vGut = ${invert ? '1.0 - uv.x' : 'uv.x'};`);
    sh.fragmentShader = 'varying float vGut;\n' + sh.fragmentShader.replace(
      '#include <map_fragment>',
      `#include <map_fragment>
  diffuseColor.rgb *= mix(0.694, 1.0, smoothstep(0.0, 0.09, vGut))
                    * mix(0.95, 1.0, smoothstep(0.0, 0.02, 1.0 - vGut));
  // Đường kẻ mảnh ở giữa hai trang: mỗi trang tô ~0,5px sát gáy → ghép lại thành đường ~1px,
  // tính theo điểm ảnh màn hình nên luôn mảnh dù phóng to hay thu nhỏ.
  float gutPx = vGut / max(fwidth(vGut), 1e-6);
  diffuseColor.rgb *= mix(${GUTTER_LINE_SHADE.toFixed(2)}, 1.0, smoothstep(0.0, 0.75, gutPx));`);
  };
  m.customProgramCacheKey = () => 'gutter-' + invert;
  return m;
}

// ============================================================ viền bìa
/**
 * Vật liệu viền cho một ảnh bìa/gáy. Viền lấy dải điểm ảnh sát mép (lùi vào ~1,5%) nên ảnh nhiều màu
 * thì viền cũng đổi màu đúng vị trí, như bìa in liền của sách thật.
 * Hai bản thu nhỏ: "dọc" (hẹp × cao) cho các cạnh đứng, "ngang" (rộng × thấp) cho mép trên/dưới.
 * Mỗi bản chỉ làm mịn theo chiều VUÔNG GÓC với mép (để chữ/hoạ tiết sát mép không thành vệt),
 * còn dọc theo mép giữ độ nét cao → ranh giới giữa các mảng màu không bị nhoè/lem.
 */
function edgeMaterial(tex) {
  const src = tex.image;
  const make = (w, h) => {
    const [cv, ctx] = makeCanvas(w, h);
    ctx.imageSmoothingQuality = 'high';
    ctx.drawImage(src, 0, 0, w, h);
    const t = new THREE.CanvasTexture(cv);
    t.colorSpace = THREE.SRGBColorSpace;
    t.generateMipmaps = false;
    t.minFilter = t.magFilter = THREE.LinearFilter;
    const m = new THREE.MeshStandardMaterial({ map: t, roughness: 0.75 });
    m.userData.inset = [Math.max(0.015, 0.5 / w), Math.max(0.015, 0.5 / h)];
    return m;
  };
  const along = (n) => clamp(n, 16, 2048);
  const v = make(32, along(src.height));     // cạnh đứng: nét theo chiều cao
  const h = make(along(src.width), 32);      // mép trên/dưới: nét theo chiều rộng
  // thứ tự mặt BoxGeometry: px, nx, py, ny, pz, nz
  return { v, h, faces: [v, v, h, h, v, v] };
}

/**
 * Gán toạ độ ảnh cho các mặt viền của một BoxGeometry.
 * map(x, y, z) → [u, v] là vị trí trên ảnh tương ứng với điểm đó; giá trị được kẹp vào trong mép ảnh
 * nên mặt viền nhận đúng dải màu sát mép (mặt trên ← hàng trên cùng, cạnh ngoài ← cột ngoài cùng…).
 */
function edgeUV(geo, faces, edge, map) {
  const pos = geo.attributes.position, uv = geo.attributes.uv;
  for (const f of faces) {
    const [eu, ev] = edge.faces[f].userData.inset;
    const pts = [];
    for (let i = f * 4; i < f * 4 + 4; i++) {   // BoxGeometry 1×1×1: mỗi mặt 4 đỉnh liên tiếp
      pts.push(map(pos.getX(i), pos.getY(i), pos.getZ(i)));
    }
    // Chỉ lùi vào trong theo chiều VUÔNG GÓC với mép (trục có giá trị không đổi trên mặt viền).
    // Dọc theo mép giữ nguyên 0…1 — nếu kẹp cả chiều này, ảnh trên viền bị co lại và lệch
    // so với ảnh bìa/gáy, làm ranh giới màu bị "lem" sang vùng bên cạnh.
    const flatU = Math.abs(pts[0][0] - pts[3][0]) < 1e-6 && Math.abs(pts[0][0] - pts[1][0]) < 1e-6;
    const flatV = Math.abs(pts[0][1] - pts[3][1]) < 1e-6 && Math.abs(pts[0][1] - pts[1][1]) < 1e-6;
    pts.forEach(([u, v], k) => {
      uv.setXY(f * 4 + k,
        flatU ? clamp(u, eu, 1 - eu) : clamp(u, 0, 1),
        flatV ? clamp(v, ev, 1 - ev) : clamp(v, 0, 1));
    });
  }
  uv.needsUpdate = true;
}

/**
 * Cắt sách xuống số trang đọc thử (cfg.previewPages; 0 hoặc thiếu = để nguyên cả cuốn).
 *
 * Phải cắt ở đây chứ không ở phía máy chủ: trang nào bắt đầu ở đâu chỉ biết được sau khi
 * paginate() đo chiều rộng chữ thật, mà việc đo đó chỉ trình duyệt làm được.
 *
 * Trang cuối luôn được chốt bằng một trang kết, và số trang luôn được kéo thành số chẵn:
 * khi tổng số trang chẵn, book3d vẽ dòng cfg.texts.endPage lên mặt trên khối ruột sách,
 * nên lật hết phần đọc thử là thấy lời nhắn, không phải một mặt giấy trắng.
 */
function previewCut(pages, cfg) {
  const limit = Math.max(0, Math.floor(Number(cfg.previewPages) || 0));
  const cut = limit > 0 && limit < pages.length;
  const out = cut ? pages.slice(0, limit) : pages.slice();

  // Sách rỗng (bài chưa có chữ nào) thì không có gì để chốt.
  if (!out.length) return out;

  if (out.length % 2 === 1) {
    // Chỉ sách bị cắt mới cần nói vì sao hết; sách trọn vẹn thì tờ cuối
    // là một trang trắng bình thường, như mọi cuốn sách in.
    const body = cut ? (cfg.texts?.endPage || 'Hết phần đọc thử') : '';
    out.push({ type: 'text', body, align: 'center', valign: 'middle', header: false, number: false });
  }
  return out;
}

// ============================================================ tờ giấy (2 mặt, uốn cong được)
class Sheet {
  constructor(W, H, frontMat, backMat, zR, zL) {
    this.W = W; this.H = H; this.zR = zR; this.zL = zL;
    this.geo = new THREE.PlaneGeometry(W, H, SEG_X, SEG_Y);
    this.geo.translate(W / 2, 0, 0);             // gáy nằm ở x = 0
    this.geo.attributes.position.setUsage(THREE.DynamicDrawUsage);
    this.meshes = [new THREE.Mesh(this.geo, frontMat), new THREE.Mesh(this.geo, backMat)];
    for (const m of this.meshes) {
      m.castShadow = m.receiveShadow = true;
      m.frustumCulled = false;
    }
    this.t = 0; this.dir = 1; this.peek = 0; this._last = -1; this._lastDir = 0;
  }

  /** t: 0 = nằm bên phải, 1 = đã lật sang trái. dir: chiều lật (+1 / -1). */
  apply(t, dir) {
    if (Math.abs(t - this._last) < 1e-5 && dir === this._lastDir &&
        this.zR === this._zR && this.zL === this._zL) return;
    this._last = t; this._lastDir = dir; this._zR = this.zR; this._zL = this.zL;
    const p = this.geo.attributes.position.array;
    const ds = this.W / SEG_X;
    const zh = lerp(this.zR, this.zL, smooth(0.15, 0.85, t));
    const bend = 0.27 * Math.sin(Math.PI * t);    // mép giấy đi trước, giấy cong lên khi lật
    for (let j = 0; j <= SEG_Y; j++) {
      const row = j * (SEG_X + 1);
      const yn = p[row * 3 + 1] / (this.H / 2);   // -1..1 — góc trên đi trước một chút
      let x = 0, z = 0;
      for (let i = 0; i <= SEG_X; i++) {
        if (i > 0) {
          const s = (i - 0.5) / SEG_X;
          const f = Math.pow(s, 1.35) * (1 + 0.2 * yn);
          const th = Math.PI * clamp(t + dir * bend * f, 0, 1);
          x += Math.cos(th) * ds;
          z += Math.sin(th) * ds;
        }
        const k = (row + i) * 3;
        p[k] = x;
        p[k + 2] = zh + z;
      }
    }
    this.geo.attributes.position.needsUpdate = true;
    this.geo.computeVertexNormals();
  }
}

/** Bìa trước + nửa khối ruột: lật cùng nhau quanh gáy (như mở sách ở giữa). */
class Cover {
  constructor(pivot) {
    this.pivot = pivot;
    this.t = 0; this.dir = 1; this.peek = 0;
  }
  apply(t) {
    this.pivot.rotation.y = -Math.PI * t;
  }
}

// ============================================================ ứng dụng
class BookApp {
  constructor(cfg) {
    this.cfg = cfg;
    const size = { width: 14, height: 20.5, thickness: 2.4, ...(cfg.size || {}) };
    this.pages = buildPages(cfg);      // tự phân trang từ cfg.content (hoặc cfg.pages kiểu cũ)
    const Nfull = this.pages.length;   // số trang của cả cuốn, trước khi cắt phần đọc thử
    this.pages = previewCut(this.pages, cfg);
    this.N = this.pages.length;
    // Độ dày gáy tự tính theo số trang (tắt bằng "autoThickness": false để dùng size.thickness).
    // Tính theo cả cuốn, không theo phần đọc thử: người xem cần thấy sách thật dày bao nhiêu.
    if (autoThickness(cfg)) size.thickness = bookThickness(Nfull, cfg.paperThickness);
    this.H = UNIT_H;
    this.W = (UNIT_H * size.width) / size.height;
    this.T = clamp((UNIT_H * size.thickness) / size.height, 0.012, 0.9);

    this._state = 'closed';     // closed | opening | open | closing
    this.current = 0;           // số "tờ" (kể cả bìa) đã lật sang trái
    this.focus = 'right';       // trang đang xem trên điện thoại
    this.zoom = 1;
    this.pan = { x: 0, y: 0 };
    this.spin = 0;              // xoay tay khi sách đang gập
    this.hover = { x: 0, y: 0, inside: false };
    this.peekItem = null;
    this.tweens = new Map();
    this.clock = 0;
    this.cam = { x: 0, y: 0, d: 10 };
    this.intro = 0;
    this.visible = true;
  }

  // Trạng thái được gán ở nhiều chỗ (mở, gập, nhảy tới trang). Gói lại ở đây
  // để trang chứa iframe luôn nhận được tin, không phải nhớ nhắn ở từng chỗ.
  get state() { return this._state; }

  set state(value) {
    if (this._state === value) return;
    this._state = value;
    tell('state', { state: value });
  }

  // ---------------------------------------------------------- init
  async init() {
    const canvas = $('#book-canvas');
    this.canvas = canvas;
    const r = (this.renderer = new THREE.WebGLRenderer({ canvas, antialias: true, alpha: true }));
    r.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
    r.shadowMap.enabled = true;
    r.shadowMap.type = THREE.PCFSoftShadowMap;
    r.outputColorSpace = THREE.SRGBColorSpace;

    this.scene = new THREE.Scene();
    this.camera = new THREE.PerspectiveCamera(FOV, 1, 0.1, 100);

    // Ánh sáng
    this.scene.add(new THREE.HemisphereLight(0xffffff, 0xc9d6ea, 1.9));
    const sun = (this.sun = new THREE.DirectionalLight(0xfff6e8, 1.5));
    sun.position.set(-2.2, 3.2, 6);
    sun.castShadow = true;
    sun.shadow.mapSize.set(2048, 2048);
    Object.assign(sun.shadow.camera, { left: -3.5, right: 3.5, top: 3, bottom: -3, near: 1, far: 16 });
    sun.shadow.bias = -0.0004;
    sun.shadow.normalBias = 0.012;
    sun.shadow.radius = 4;
    this.scene.add(sun);

    // Phông nền nhận bóng (cho cảm giác sách nổi trên nền như ảnh mẫu)
    const backdrop = new THREE.Mesh(
      new THREE.PlaneGeometry(40, 40),
      new THREE.ShadowMaterial({ opacity: 0.2 }));
    backdrop.position.z = -0.45;
    backdrop.receiveShadow = true;
    this.scene.add(backdrop);

    await this.buildBook();
    this.bindUI();
    this.onResize();
    new ResizeObserver(() => this.onResize()).observe($('#stage'));
    if ('IntersectionObserver' in window) {
      new IntersectionObserver(([e]) => { this.visible = e.isIntersecting; }).observe(canvas);
    }

    $('#loader').classList.add('done');
    canvas.classList.add('ready');
    if (params.has('open') || params.has('page')) this.showPage(Number(params.get('page')) || 1);
    else setTimeout(() => { if (this.state === 'closed') $('#hint').classList.remove('hidden'); }, 700);

    this.last = performance.now();
    const loop = (now) => {
      requestAnimationFrame(loop);
      const dt = Math.min((now - this.last) / 1000, 1 / 20);
      this.last = now;
      if (!this.visible || document.hidden) return;
      this.update(dt);
      this.renderer.render(this.scene, this.camera);
    };
    requestAnimationFrame(loop);
  }

  // ---------------------------------------------------------- dựng sách
  async buildBook() {
    const { W, H, T, N, cfg } = this;
    const r = this.renderer;
    const paper = cfg.paperColor || '#fbf9f3';
    const coverColor = new THREE.Color(cfg.cover?.color || '#4a6a9e');
    const texW = Math.round((TEX_H * W) / H);
    const aniso = r.capabilities.getMaxAnisotropy();
    const loaderText = $('#loader-text');
    let loaded = 0;
    const total = 3;

    const toTex = (cv, mirror = false) => {
      const t = new THREE.CanvasTexture(cv);
      t.colorSpace = THREE.SRGBColorSpace;
      t.anisotropy = aniso;
      if (mirror) { t.repeat.x = -1; t.offset.x = 1; }   // mặt sau tờ giấy
      return t;
    };
    const imageTex = async (url, w, h, fallback, mirror) => {
      const [cv, ctx] = makeCanvas(w, h);
      try {
        if (!url) throw new Error('no url');
        ctx.drawImage(await loadImage(url), 0, 0, w, h);
      } catch (e) {
        if (url) console.warn('[book3d]', e.message);
        fallback(ctx, w, h);
      }
      loaded++;
      loaderText.textContent = `${cfg.texts?.loading || 'Đang tải sách…'} ${Math.round((loaded / total) * 100)}%`;
      return toTex(cv, mirror);
    };
    const makePageTex = async (page, i) => {
      const [cv, ctx] = makeCanvas(texW, TEX_H);
      await drawPage(ctx, texW, TEX_H, page, pageInfo(cfg, i, resolve));
      return toTex(cv, i % 2 === 1);
    };
    this.makePageTex = makePageTex;
    const coverFallback = (label) => (ctx, w, h) => {
      ctx.fillStyle = '#' + coverColor.getHexString();
      ctx.fillRect(0, 0, w, h);
      centerText(ctx, [[label, `${Math.min(w, h) * 0.08}px ${SERIF}`, '#fff']], w, h / 2, 0);
    };

    // --- tải ảnh song song
    const spineW = Math.max(16, Math.round((TEX_H * T) / H));
    const [front, back, spine, lastPageTex] = await Promise.all([
      imageTex(resolve(cfg.cover?.front), texW, TEX_H, coverFallback(cfg.title || 'Bìa trước')),
      imageTex(resolve(cfg.cover?.back), texW, TEX_H, coverFallback('Bìa sau')),
      imageTex(resolve(cfg.cover?.spine), spineW, TEX_H, coverFallback('')),
      N % 2 === 1 ? makePageTex(this.pages[N - 1], N - 1) : null,
    ]);

    // --- texture sinh bằng canvas
    // Mặt trong bìa trước: chỉ một dòng nhắn, không nhắc lại tên sách -
    // người đọc vừa nhìn thấy nó to đùng trên bìa, lật ra gặp lại ngay
    // thì thành thừa. Để trống texts.insideCover thì mặt này bỏ trắng.
    const [icv, ictx] = makeCanvas(texW / 2, TEX_H / 2);
    paperFill(ictx, icv.width, icv.height, paper);
    const insideText = cfg.texts?.insideCover ?? 'Bản đọc thử';
    if (insideText) {
      centerText(ictx, [[insideText, `italic ${icv.height * 0.03}px ${SERIF}`, '#8a8170']],
        icv.width, icv.height * 0.5, 0);
    }
    const insideCover = toTex(icv);

    const [pcv, pctx] = makeCanvas(64, 64);
    paperFill(pctx, 64, 64, paper);
    const plain = toTex(pcv);
    this.plainTex = plain;

    const edge = (vertical) => {
      const [cv, ctx] = makeCanvas(vertical ? 512 : 8, vertical ? 8 : 512);
      ctx.fillStyle = paper; ctx.fillRect(0, 0, cv.width, cv.height);
      for (let i = 0; i < 512; i += 2) {
        const g = 200 + Math.random() * 30;
        ctx.fillStyle = `rgb(${g},${g - 4},${g - 12})`;
        if (vertical) ctx.fillRect(i, 0, 1, 8); else ctx.fillRect(0, i, 8, 1);
      }
      return toTex(cv);
    };
    const foreEdge = edge(true), topEdge = edge(false);

    // --- bố cục theo trục z (z dương hướng về người xem)
    // sách mỏng: thu nhỏ bìa và khe giữa các tờ để tổng độ dày vẫn đúng như tính toán
    const c = Math.min(0.007, T * 0.15);     // độ dày bìa mềm
    const g = Math.min(0.0035, (T * 0.3) / Math.max(1, Math.min(Math.floor(N / 2), VIS)));
    const m = Math.floor(N / 2);             // số tờ lật được (mỗi tờ 2 trang)
    const vis = Math.min(m, VIS);            // chỉ vài tờ trên cùng mỗi bên là tờ thật
    const B = Math.max(0.002, T - 2 * c - vis * g);   // khối ruột còn lại
    const half = B / 2;                      // nửa khối ruột mỗi bên khi mở
    this.zTop = c + half + vis * g;          // mặt trang khi sách mở (hai bên bằng nhau)
    Object.assign(this, { zBase: c + half, gap: g, vis, m });

    this.root = new THREE.Group();             // xoay quanh tâm sách
    const group = (this.group = new THREE.Group());
    this.root.add(group);
    this.scene.add(this.root);
    // Viền bìa/gáy "in liền" như sách thật: mỗi mặt viền lấy dải màu sát mép của ảnh kề nó
    const frontEdge = edgeMaterial(front), backEdge = edgeMaterial(back), spineEdge = edgeMaterial(spine);
    const EDGE = [0, 1, 2, 3];               // các mặt bên của hộp: px, nx, py, ny
    const edgeFore = new THREE.MeshStandardMaterial({ map: foreEdge, roughness: 1 });
    const edgeTop = new THREE.MeshStandardMaterial({ map: topEdge, roughness: 1 });
    const shadowed = (mesh) => { mesh.castShadow = mesh.receiveShadow = true; return mesh; };

    // Bìa sau (cố định)
    const backGeo = new THREE.BoxGeometry(W, H, c); backGeo.translate(W / 2, 0, c / 2);
    edgeUV(backGeo, EDGE, backEdge, (x, y) => [1 - x / W, y / H + 0.5]);   // ảnh bìa sau nhìn từ phía sau
    group.add(shadowed(new THREE.Mesh(backGeo, [
      ...backEdge.faces.slice(0, 4),
      paperMaterial(plain),
      new THREE.MeshStandardMaterial({ map: back, roughness: 0.75 }),
    ])));

    // Khối ruột sách (mặt trên là trang cuối cùng của phần đọc thử)
    let lastTex;
    if (N % 2 === 1) lastTex = lastPageTex;
    else {
      const [ecv, ectx] = makeCanvas(texW / 2, TEX_H / 2);
      paperFill(ectx, ecv.width, ecv.height, paper);
      centerText(ectx, [[cfg.texts?.endPage || 'Hết phần đọc thử', `italic ${ecv.height * 0.03}px ${SERIF}`, '#8a8170']],
        ecv.width, ecv.height * 0.5, 0);
      lastTex = toTex(ectx.canvas);
    }
    // Nửa khối ruột bên phải (cố định). Mặt trên là trang cuối của phần đọc thử.
    const blockGeo = new THREE.BoxGeometry(W, H, half); blockGeo.translate(W / 2, 0, c + half / 2);
    edgeUV(blockGeo, [1], spineEdge, (x, y, z) => [z / T, y / H + 0.5]);   // mặt dính vào gáy
    group.add(shadowed(new THREE.Mesh(blockGeo, [
      edgeFore, spineEdge.v, edgeTop, edgeTop, paperMaterial(lastTex), paperMaterial(plain),
    ])));

    // Gáy sách (chỉ hiện khi gập)
    const sw = 0.006;
    const spineGeo = new THREE.BoxGeometry(sw, H, T); spineGeo.translate(-sw / 2, 0, T / 2);
    edgeUV(spineGeo, [0, 2, 3, 4, 5], spineEdge, (x, y, z) => [z / T, y / H + 0.5]);
    this.spine = shadowed(new THREE.Mesh(spineGeo, [
      spineEdge.faces[0], new THREE.MeshStandardMaterial({ map: spine, roughness: 0.75 }),
      ...spineEdge.faces.slice(2),
    ]));
    group.add(this.spine);

    // Bìa trước + nửa khối ruột còn lại: xoay quanh trục ở giữa độ dày sách (z = T/2).
    // Gập: nằm trên cùng bên phải. Mở: lật sang trái, bìa nằm dưới đáy, mặt trong lộ lên trên.
    const pivot = new THREE.Group();
    pivot.position.z = T / 2;
    group.add(pivot);
    const coverGeo = new THREE.BoxGeometry(W, H, c); coverGeo.translate(W / 2, 0, T / 2 - c / 2);
    edgeUV(coverGeo, EDGE, frontEdge, (x, y) => [x / W, y / H + 0.5]);
    pivot.add(shadowed(new THREE.Mesh(coverGeo, [
      ...frontEdge.faces.slice(0, 4),
      new THREE.MeshStandardMaterial({ map: front, roughness: 0.7 }),
      paperMaterial(plain),
    ])));
    const z0 = c + half + vis * g - T / 2;   // đáy khối (toạ độ cục bộ)
    const bundleGeo = new THREE.BoxGeometry(W, H, half); bundleGeo.translate(W / 2, 0, z0 + half / 2);
    edgeUV(bundleGeo, [1], spineEdge, (x, y, z) => [(z + T / 2) / T, y / H + 0.5]);
    pivot.add(shadowed(new THREE.Mesh(bundleGeo, [
      edgeFore, spineEdge.v, edgeTop, edgeTop,
      paperMaterial(plain),
      paperMaterial(insideCover, { invert: true }),   // trang trái đầu tiên khi mở
    ])));
    const items = [new Cover(pivot)];

    // Các tờ giấy. Texture được vẽ khi tờ sắp được xem và giải phóng khi đã đi xa,
    // nên sách dài bao nhiêu trang cũng không tốn thêm bộ nhớ.
    for (let k = 1; k <= m; k++) {
      const sheet = new Sheet(W, H,
        paperMaterial(plain),
        paperMaterial(plain, { side: THREE.BackSide }),
        0, 0);
      sheet.k = k;
      sheet.tex = [null, null];
      sheet.meshes.forEach((mm) => group.add(mm));
      items.push(sheet);
    }
    items.forEach((it, i) => { it.key = 'item' + i; });
    this.items = items;
    this.cover = items[0];
    this.placeSheets();
    items.forEach((it) => it.apply(0, 1));
    this.pickables = [];
    group.traverse((o) => o.isMesh && this.pickables.push(o));
    this.raycaster = new THREE.Raycaster();
    await this.ensureTextures(true);
  }

  /** Độ cao của từng tờ theo vị trí đọc hiện tại: tờ trên cùng mỗi bên nằm sát mặt trang. */
  placeSheets() {
    const s = this.current, { zBase, gap, vis } = this;
    for (let k = 1; k <= this.m; k++) {
      const it = this.items[k];
      const rr = Math.max(0, k - Math.max(s, 1));     // thứ hạng khi nằm bên phải (0 = trên cùng)
      const rl = Math.max(0, s - 1 - k);              // thứ hạng khi nằm bên trái
      it.zR = zBase + (vis - Math.min(rr, vis - 1) - 0.5) * gap;
      it.zL = zBase + (vis - Math.min(rl, vis - 1) - 0.5) * gap + 0.0015;
      it.rank = k < s ? rl : rr;
    }
  }

  /** Vẽ texture cho các tờ gần trang đang mở, giải phóng các tờ ở xa. */
  async ensureTextures(initial = false) {
    const s = Math.max(this.current, 1);
    const want = [];
    for (let k = 1; k <= this.m; k++) {
      const it = this.items[k];
      const d = k >= s ? k - s : s - 1 - k;
      if (d <= KEEP) { if (!it.tex[0]) want.push([d, it]); }
      else if (it.tex[0] && d > KEEP + 2) this.dropTex(it);
    }
    want.sort((a, b) => a[0] - b[0]);
    if (initial) await Promise.all(want.filter(([d]) => d < VIS).map(([, it]) => this.loadTex(it)));
    for (const [, it] of want) if (!it.tex[0]) await this.loadTex(it);
  }

  async loadTex(it) {
    if (it.loading) return it.loading;
    it.loading = (async () => {
      const i = 2 * it.k - 2;
      const tex = await Promise.all([
        this.makePageTex(this.pages[i], i),
        this.pages[i + 1] ? this.makePageTex(this.pages[i + 1], i + 1) : null,
      ]);
      it.tex = [tex[0], tex[1] || this.plainTex];
      it.meshes[0].material.map = it.tex[0];
      it.meshes[1].material.map = it.tex[1];
      it.loading = null;
    })();
    return it.loading;
  }

  dropTex(it) {
    for (const [j, t] of it.tex.entries()) {
      if (t && t !== this.plainTex) t.dispose();
      it.meshes[j].material.map = this.plainTex;
    }
    it.tex = [null, null];
  }

  // ---------------------------------------------------------- tween
  tween(key, from, to, dur, set, { delay = 0, ease = easeInOut, done } = {}) {
    if (REDUCED) dur *= 0.5;
    this.tweens.set(key, { from, to, dur: Math.max(dur, 0.001), set, ease, done, start: this.clock + delay });
  }
  runTweens() {
    for (const [key, tw] of this.tweens) {
      const p = (this.clock - tw.start) / tw.dur;
      if (p < 0) continue;
      tw.set(lerp(tw.from, tw.to, tw.ease(clamp(p, 0, 1))));
      if (p >= 1) { this.tweens.delete(key); tw.done?.(); }
    }
  }
  flipTo(item, target, opts = {}) {
    const from = item.t;
    const dist = Math.abs(target - from);
    if (dist < 1e-4 && !opts.done) return;
    item.dir = target > from ? 1 : -1;
    const dur = opts.dur ?? lerp(0.3, 0.95, dist);
    this.tween(item.key, from, target, dur, (v) => { item.t = v; }, {
      delay: opts.delay || 0,
      ease: dist > 0.85 ? easeInOut : easeOut,
      done: opts.done,
    });
  }

  // ---------------------------------------------------------- điều hướng
  /**
   * Xem từng trang một (true) hay cả hai trang đối nhau (false)?
   *
   * cfg.spread quyết định: "single" luôn một trang, "double" luôn hai trang,
   * còn thiếu hoặc "auto" thì tự xoay theo khung - khung hẹp hơn cao thì
   * hai trang sẽ nhỏ đến mức không đọc nổi, nên tách ra từng trang.
   *
   * Mọi chỗ khác trong lớp này đều hỏi qua đây, nên đổi một chỗ là đủ.
   */
  get portrait() {
    const mode = this.cfg.spread;
    if (mode === 'single') return true;
    if (mode === 'double') return false;
    return this.aspect < 0.82;
  }

  open() {
    if (this.state !== 'closed') return;
    this.state = 'opening';
    $('#hint').classList.add('hidden');
    this.current = 1;
    this.focus = 'right';
    this.flipTo(this.cover, 1, {
      delay: 0.3, dur: 1.2,
      done: () => { this.state = 'open'; $('#toolbar').classList.remove('hidden'); this.updateUI(); },
    });
  }

  /** Mở ngay (không hiệu ứng) tới trang p (đánh số từ 1) — dùng cho xem trước trong trình soạn. */
  showPage(p) {
    p = clamp(Math.round(p), 1, Math.max(1, this.N));
    const s = clamp(p % 2 === 1 ? (p + 1) / 2 : p / 2 + 1, 1, this.items.length);
    this.items.forEach((it, i) => { this.tweens.delete(it.key); it.t = i < s ? 1 : 0; it.dir = 1; });
    this.current = s;
    this.state = 'open';
    this.focus = p % 2 === 0 ? 'left' : 'right';
    this.root.rotation.set(OPEN_TILT, 0, 0);
    $('#hint').classList.add('hidden');
    $('#toolbar').classList.remove('hidden');
    this.updateUI();
  }

  /** Nhảy tới trang p khi sách đang mở: các tờ ở giữa đổi tức thì, chỉ tờ cuối được lật có hiệu ứng. */
  jumpTo(p) {
    if (this.state !== 'open') return this.showPage(p);
    p = clamp(Math.round(p), 1, Math.max(1, this.N));
    const s = clamp(p % 2 === 1 ? (p + 1) / 2 : p / 2 + 1, 1, this.items.length);
    if (this.portrait) this.focus = p % 2 === 0 ? 'left' : 'right';
    if (s === this.current) return this.updateUI();
    const fwd = s > this.current;
    const anim = fwd ? this.items[s - 1] : this.items[s];
    this.items.forEach((it, i) => {
      if (i === 0 || it === anim) return;
      this.tweens.delete(it.key);
      it.t = i < s ? 1 : 0;
    });
    anim.t = fwd ? 0 : 1;
    this.current = s;
    this.flipTo(anim, fwd ? 1 : 0);
    this.updateUI();
  }

  /**
   * Gập sách lại.
   *
   * instant = true thì bỏ hiệu ứng, về bìa ngay. Dùng khi trang chứa đóng
   * popup: lúc đó iframe đã khuất nên không ai nhìn thấy hiệu ứng, mà
   * một hiệu ứng chạy trong khung hình đã co về 0×0 thì vừa phí vừa dễ
   * để sách kẹt lại ở trạng thái "đang gập".
   */
  close(instant = false) {
    // Gập tức thì được phép cắt ngang cả lúc sách đang mở dở hoặc đang
    // gập dở: nó dùng để dọn về trạng thái sạch, không phải diễn hiệu ứng.
    if (instant ? this.state === 'closed' : this.state !== 'open') return;
    this.state = 'closing';
    this.setZoom(1);
    $('#toolbar').classList.add('hidden');
    $('#cta').classList.add('hidden');

    if (instant) {
      this.tweens.clear();
      this.items.forEach((it) => { it.t = 0; it.dir = 1; it.peek = 0; });
      this.current = 0;
      this.focus = 'right';
      this.placeSheets();
      this.ensureTextures();
      this.state = 'closed';
      $('#hint').classList.remove('hidden');
      return;
    }

    let delay = 0;
    // chỉ vài tờ trên cùng lật có hiệu ứng; các tờ còn lại về chỗ ngay (đang bị che)
    for (let i = this.current - 1; i >= 1; i--) {
      if (this.current - 1 - i < 3) {
        this.flipTo(this.items[i], 0, { delay, dur: 0.75 });
        delay += 0.12;
      } else { this.tweens.delete(this.items[i].key); this.items[i].t = 0; }
    }
    this.current = 0;
    this.placeSheets();
    this.ensureTextures();
    this.flipTo(this.cover, 0, {
      delay: delay + 0.1, dur: 1.05,
      done: () => { this.state = 'closed'; $('#hint').classList.remove('hidden'); },
    });
  }

  next() {
    if (this.state === 'closed') return this.open();
    if (this.state !== 'open') return;
    if (this.portrait && this.focus === 'left') { this.focus = 'right'; return this.updateUI(); }
    if (this.current >= this.items.length) return;
    this.flipTo(this.items[this.current], 1);
    this.current++;
    if (this.portrait) this.focus = 'left';
    this.updateUI();
  }

  prev() {
    if (this.state !== 'open') return;
    if (this.portrait && this.focus === 'right' && this.current > 1) { this.focus = 'left'; return this.updateUI(); }
    if (this.current <= 1) return this.close();
    this.current--;
    this.flipTo(this.items[this.current], 0);
    if (this.portrait) this.focus = 'right';
    this.updateUI();
  }

  setZoom(z) {
    this.zoom = z;
    this.pan.x = this.pan.y = 0;
    $('#btn-zoom').classList.toggle('active', z > 1);
  }

  /** Số trang đang hiện ở hai bên (null = trang không đánh số). */
  spreadPages() {
    const s = this.current;
    const m = this.items.length - 1;
    const left = s <= 1 ? null : 2 * (s - 1);
    const right = s <= m ? 2 * s - 1 : (this.N % 2 === 1 ? this.N : null);
    return { left, right };
  }

  updateUI() {
    const { left, right } = this.spreadPages();
    let nums;
    if (this.portrait) nums = [this.focus === 'left' ? left : right];
    else nums = [left, right];
    nums = nums.filter((n) => n != null);
    $('#page-label').textContent = nums.length ? `Trang ${nums.join('–')} / ${this.N}` : `Bìa · ${this.N} trang`;
    const seek = $('#seek');
    if (seek && document.activeElement !== seek) seek.value = String(nums[0] ?? 1);
    if (this.items.length > 1) { this.placeSheets(); this.ensureTextures(); }
    const atEnd = this.current >= this.items.length && (!this.portrait || this.focus === 'right');
    $('#btn-next').disabled = atEnd;
    $('#btn-prev').disabled = false;
    clearTimeout(this._ctaTimer);
    if (atEnd && this.state === 'open') {
      this._ctaTimer = setTimeout(() => $('#cta').classList.remove('hidden'), 650);
    } else $('#cta').classList.add('hidden');
  }

  // ---------------------------------------------------------- toạ độ màn hình
  toScreen(x, y, z) {
    const v = this.group.localToWorld(new THREE.Vector3(x, y, z)).project(this.camera);
    return { x: ((v.x + 1) / 2) * this.cw, y: ((1 - v.y) / 2) * this.ch };
  }
  pageScreen() {
    const z = this.zTop;
    const sp = this.toScreen(0, 0, z), re = this.toScreen(this.W, 0, z);
    const top = this.toScreen(0, this.H / 2, z), bot = this.toScreen(0, -this.H / 2, z);
    return { spineX: sp.x, pageW: re.x - sp.x, top: top.y, bottom: bot.y };
  }
  hitBook(cx, cy) {
    const rect = this.canvas.getBoundingClientRect();
    const ndc = new THREE.Vector2(((cx - rect.left) / rect.width) * 2 - 1, -((cy - rect.top) / rect.height) * 2 + 1);
    this.raycaster.setFromCamera(ndc, this.camera);
    return this.raycaster.intersectObjects(this.pickables, false).length > 0;
  }

  // ---------------------------------------------------------- sự kiện
  bindUI() {
    const cv = this.canvas;
    $('#hint').addEventListener('click', () => this.open());
    $('#btn-next').addEventListener('click', () => this.next());
    $('#btn-prev').addEventListener('click', () => this.prev());
    $('#btn-close').addEventListener('click', () => this.close());
    $('#btn-zoom').addEventListener('click', () => this.setZoom(this.zoom > 1 ? 1 : 2));
    const seek = $('#seek');
    if (this.N > 6) {
      seek.max = String(this.N);
      seek.addEventListener('input', () => { $('#page-label').textContent = `Trang ${seek.value} / ${this.N}`; });
      seek.addEventListener('change', () => { this.jumpTo(Number(seek.value)); seek.blur(); });
      seek.addEventListener('pointerdown', (e) => e.stopPropagation());
    } else seek.remove();
    const full = $('#btn-full');
    if (!document.fullscreenEnabled) full.remove();
    else full.addEventListener('click', () => {
      if (document.fullscreenElement) document.exitFullscreen();
      else document.documentElement.requestFullscreen().catch(() => {});
    });

    window.addEventListener('keydown', (e) => {
      if (e.key === 'ArrowRight' || e.key === 'PageDown') { e.preventDefault(); this.next(); }
      else if (e.key === 'ArrowLeft' || e.key === 'PageUp') { e.preventDefault(); this.prev(); }
      else if (e.key === 'Escape') { if (this.zoom > 1) this.setZoom(1); else this.close(); }
      else if ((e.key === 'Enter' || e.key === ' ') && this.state === 'closed' && e.target === document.body) { e.preventDefault(); this.open(); }
    });

    cv.addEventListener('pointerdown', (e) => this.onDown(e));
    cv.addEventListener('pointermove', (e) => this.onMove(e));
    cv.addEventListener('pointerup', (e) => this.onUp(e, false));
    cv.addEventListener('pointercancel', (e) => this.onUp(e, true));
    cv.addEventListener('pointerleave', () => { this.hover.inside = false; this.peekItem = null; });
    cv.addEventListener('dblclick', () => { if (this.state === 'open') this.setZoom(this.zoom > 1 ? 1 : 2); });
  }

  onDown(e) {
    if (e.button !== undefined && e.button !== 0) return;
    this.ptr = { id: e.pointerId, x0: e.clientX, y0: e.clientY, x: e.clientX, y: e.clientY, lastT: performance.now(), vx: 0, mode: null };
  }

  onMove(e) {
    const rect = this.canvas.getBoundingClientRect();
    this.hover.x = ((e.clientX - rect.left) / rect.width) * 2 - 1;
    this.hover.y = ((e.clientY - rect.top) / rect.height) * 2 - 1;
    this.hover.inside = true;
    const p = this.ptr;
    if (!p || p.id !== e.pointerId) return this.updateHover(e);

    const now = performance.now();
    const ddx = e.clientX - p.x, dtm = Math.max(now - p.lastT, 1);
    p.vx = lerp(p.vx, ddx / dtm, 0.5);
    const mx = e.clientX - p.x, my = e.clientY - p.y;
    p.x = e.clientX; p.y = e.clientY; p.lastT = now;
    const dx = e.clientX - p.x0, dy = e.clientY - p.y0;

    if (!p.mode && Math.hypot(dx, dy) > 7) {
      if (this.state === 'closed') p.mode = 'spin';
      else if (this.state !== 'open') p.mode = 'none';
      else if (this.zoom > 1) p.mode = 'pan';
      else if (Math.abs(dx) > Math.abs(dy) * 0.8) p.mode = this.portrait ? 'swipe' : (this.startDrag(p) ? 'flip' : 'none');
      else p.mode = 'none';
      if (p.mode !== 'none') {
        try { this.canvas.setPointerCapture(e.pointerId); } catch {}
        this.canvas.className = 'ready grabbing';
      }
    }

    if (p.mode === 'spin') this.spin += mx * 0.008;
    else if (p.mode === 'pan') {
      const ppu = this.ch / (2 * this.cam.d * Math.tan((FOV * Math.PI) / 360));
      this.pan.x -= mx / ppu;
      this.pan.y += my / ppu;
      const lim = this.portrait ? this.W / 2 : this.W;
      this.pan.x = clamp(this.pan.x, -lim, lim);
      this.pan.y = clamp(this.pan.y, -this.H / 2, this.H / 2);
    } else if (p.mode === 'flip') {
      const u = (e.clientX - p.spineX) / p.pageW;
      p.item.t = Math.acos(clamp(u / p.u0, -1, 1)) / Math.PI;
    }
  }

  startDrag(p) {
    const ps = this.pageScreen();
    const u0 = (p.x0 - ps.spineX) / ps.pageW;
    let item = null, forward = true;
    if (u0 > 0 && this.current < this.items.length) item = this.items[this.current];
    else if (u0 < 0 && this.current > 1) { item = this.items[this.current - 1]; forward = false; }
    if (!item) return false;
    this.tweens.delete(item.key);
    item.peek = 0;
    item.dir = forward ? 1 : -1;
    Object.assign(p, { item, forward, spineX: ps.spineX, pageW: ps.pageW, u0: Math.max(Math.abs(u0), 0.5) });
    return true;
  }

  onUp(e, cancelled) {
    const p = this.ptr;
    if (!p || p.id !== e.pointerId) return;
    this.ptr = null;
    this.canvas.className = 'ready';
    const dx = e.clientX - p.x0;

    if (p.mode === 'flip') {
      const { item, forward } = p;
      const v = p.vx; // px/ms
      if (forward) {
        if (!cancelled && (item.t > 0.35 || v < -0.45)) { this.flipTo(item, 1); this.current++; }
        else this.flipTo(item, 0);
      } else if (!cancelled && (item.t < 0.65 || v > 0.45)) { this.flipTo(item, 0); this.current--; }
      else this.flipTo(item, 1);
      return this.updateUI();
    }
    if (p.mode === 'swipe') {
      if (dx < -40) this.next(); else if (dx > 40) this.prev();
      return;
    }
    if (p.mode || cancelled) return;

    // Bấm (không kéo)
    if (this.state === 'closed') return this.open();
    if (this.state !== 'open' || this.zoom > 1) return;
    if (this.portrait) return e.clientX > this.cw / 2 ? this.next() : this.prev();
    const ps = this.pageScreen();
    if (e.clientX >= ps.spineX) this.next(); else this.prev();
  }

  updateHover(e) {
    let cls = 'ready';
    this.peekItem = null;
    if (this.state === 'closed') {
      if (this.hitBook(e.clientX, e.clientY)) cls += ' pointer';
    } else if (this.state === 'open') {
      if (this.zoom > 1) cls += ' grab';
      else if (!this.portrait) {
        const ps = this.pageScreen();
        const u = (e.clientX - ps.spineX) / ps.pageW;
        const inY = e.clientY > ps.top && e.clientY < ps.bottom;
        if (inY && Math.abs(u) < 1.02) cls += ' pointer';
        if (inY && u > 0.82 && u < 1.02 && this.current < this.items.length) this.peekItem = this.items[this.current];
        if (inY && u < -0.82 && u > -1.02 && this.current > 1) this.peekItem = this.items[this.current - 1];
      }
    }
    if (this.canvas.className !== cls) this.canvas.className = cls;
  }

  onResize() {
    const stage = $('#stage');
    this.cw = stage.clientWidth;
    this.ch = stage.clientHeight;
    // Chặn cả hai chiều, không riêng chiều cao: khung có thể co về 0×0 khi
    // trang chứa ẩn iframe đi (đóng popup chẳng hạn), và aspect = 0 sẽ làm
    // phép tính khoảng cách camera chia cho 0 → Infinity → NaN, mà NaN thì
    // bám lại vĩnh viễn vì camera được nội suy từ chính giá trị cũ.
    this.aspect = Math.max(this.cw, 1) / Math.max(this.ch, 1);
    this.renderer.setSize(this.cw, this.ch, false);
    this.camera.aspect = this.aspect;
    this.camera.updateProjectionMatrix();
    if (!this.portrait) this.focus = 'right';
    if (this.state === 'open') this.updateUI();
  }

  // ---------------------------------------------------------- khung hình
  update(dt) {
    this.clock += dt;
    this.runTweens();
    const { W, H } = this;
    const dragging = this.ptr?.mode === 'flip' ? this.ptr.item : null;

    // Lật trang + hé góc khi rê chuột ở mép trang
    for (const it of this.items) {
      const target = it === this.peekItem && !this.tweens.has(it.key) && it !== dragging ? 1 : 0;
      it.peek = damp(it.peek, target, 9, dt);
      if (it.peek < 1e-3) it.peek = 0;
      let t = it.t, dir = it.dir;
      if (it.peek > 0 && it !== this.cover) {
        const side = it.t < 0.5 ? 1 : -1;
        t = clamp(it.t + side * 0.045 * it.peek, 0, 1);
        if (!this.tweens.has(it.key)) dir = side;
      }
      if (it !== this.cover) {
        const moving = (it.t > 0.001 && it.t < 0.999) || this.tweens.has(it.key);
        const show = moving || it.rank < this.vis || it === dragging;
        it.meshes[0].visible = it.meshes[1].visible = show;
        if (!show) continue;
      }
      it.apply(t, dir);
    }

    // Vị trí & tư thế cuốn sách
    const open = this.cover.t;             // 0 = gập, 1 = mở
    this.intro = damp(this.intro, 1, 2.2, dt);
    if (!this.ptr || this.ptr.mode !== 'spin') this.spin = damp(this.spin, 0, 1.6, dt);
    const closedLook = this.state === 'closed';
    const hx = this.hover.inside && closedLook ? this.hover.x : 0;
    const hy = this.hover.inside && closedLook ? this.hover.y : 0;
    const targetRotY = closedLook ? CLOSED_ROT + hx * 0.16 + this.spin : 0;
    const targetRotX = closedLook ? hy * 0.08 : OPEN_TILT * open;
    const rt = this.root, g = this.group;
    rt.rotation.y = damp(rt.rotation.y, targetRotY, closedLook ? 4 : 3, dt);
    rt.rotation.x = damp(rt.rotation.x, targetRotX, 4, dt);
    g.position.x = -(W / 2) * (1 - open);
    g.position.z = -this.T / 2 * (1 - open);
    g.position.y = (1 - this.intro) * -0.35 + Math.sin(this.clock * 1.1) * 0.012 * (1 - open);
    this.spine.visible = open < 0.5;

    // Camera
    const tanH = Math.tan((FOV * Math.PI) / 360);
    // aspect đã được onResize giữ dương, nhưng chốt lại ở đây nữa: một
    // phép chia cho 0 ở chỗ này sinh ra Infinity, rồi lerp hai Infinity
    // cho ra NaN, và camera sẽ hỏng vĩnh viễn.
    const ar = Math.max(this.aspect || 1, 1e-3);
    const fit = (w, h) => Math.max(h / 2 / tanH, w / 2 / (tanH * ar));
    const dClosed = fit(W * 1.9, H * 1.45);
    const dOpen = this.portrait ? fit(W * 1.12, H * 1.32) : fit(2 * W * 1.1, H * 1.36);
    const dist = lerp(dClosed, dOpen, open) / this.zoom;
    const fx = this.portrait ? (this.focus === 'right' ? W / 2 : -W / 2) * open : 0;
    const tx = fx + (this.zoom > 1 ? this.pan.x : 0);
    const ty = -H * 0.06 * (this.zoom > 1 ? 0 : 1) + (this.zoom > 1 ? this.pan.y : 0);
    const c = this.cam;
    const rate = this.ptr?.mode === 'pan' ? 30 : 5;
    c.x = damp(c.x, tx, rate, dt);
    c.y = damp(c.y, ty, rate, dt);
    c.d = damp(c.d, dist, 4, dt);
    // Nhảy thẳng tới vị trí đích thay vì nội suy, ở khung hình đầu tiên và
    // mỗi khi giá trị cũ không còn là số. Nội suy từ một giá trị hỏng thì
    // kết quả cũng hỏng, và camera sẽ không bao giờ tự về chỗ cũ được nữa.
    if (!this._camInit || !Number.isFinite(c.d) || !Number.isFinite(c.x) || !Number.isFinite(c.y)) {
      c.d = dist; c.x = tx; c.y = ty; this._camInit = true;
    }
    this.camera.position.set(c.x, c.y, c.d);
    this.camera.lookAt(c.x, c.y, 0);
  }
}

// ============================================================ dự phòng 2D
function pageInfo(cfg, i, resolveFn) {
  // Tiêu đề chạy ở đầu trang. Hai dòng riêng cho trang chẵn và trang lẻ,
  // như sách in: thường là tên sách một bên, tên chương hoặc tên tác giả
  // bên kia. Để trống cả hai thì đầu trang bỏ trắng — chỉ còn số trang.
  // Thiếu khai báo thì lùi về tên sách và tên tác giả, như bản gốc - để
  // book.json viết tay và trình soạn vẫn chạy y như trước.
  const heads = cfg.runningHead || { left: cfg.author, right: cfg.title };
  return {
    number: (Number(cfg.pageNumberStart) || 1) + i,
    isLeft: i % 2 === 1,
    headLeft: heads.left || '',
    headRight: heads.right || '',
    paper: cfg.paperColor || '#fbf9f3',
    resolve: resolveFn,
  };
}

async function fallback2D(cfg) {
  $('#loader').classList.add('done');
  const box = $('#fallback');
  box.classList.remove('hidden');
  const size = cfg.size || { width: 14, height: 20.5 };
  const w = 700, h = Math.round((700 * size.height) / size.width);
  const pages = buildPages(cfg);
  const render = async (k) => {
    const [cv, ctx] = makeCanvas(w, h);
    await drawPage(ctx, w, h, pages[k], pageInfo(cfg, k, resolve));
    return cv.toDataURL('image/jpeg', 0.9);
  };
  const list = [resolve(cfg.cover?.front) || '', ...pages.map(() => null)];
  let i = 0;
  box.innerHTML = '<img alt=""><nav><button type="button" data-d="-1">‹ Trước</button><span></span><button type="button" data-d="1">Sau ›</button></nav>';
  const img = box.querySelector('img'), label = box.querySelector('span');
  const show = async () => {
    const k = i;
    if (list[k] == null) list[k] = await render(k - 1);
    if (k !== i) return;
    img.src = list[i];
    label.textContent = i === 0 ? 'Bìa' : `Trang ${i} / ${list.length - 1}`;
    $('#cta').classList.toggle('hidden', i !== list.length - 1);
  };
  box.querySelectorAll('button').forEach((b) => b.addEventListener('click', () => {
    i = clamp(i + Number(b.dataset.d), 0, list.length - 1); show();
  }));
  show();
}
