/* Provador de rodas: carro da biblioteca (PNG sem fundo) + roda + aro + cor, apresentado numa plataforma de vitrine. */
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const cv = $('pv-c'), ctx = cv.getContext('2d');
  const ANGULOS = window.PV_ANGULOS || ['Lateral direita'];
  const CARS = window.PV_CARS || [];
  const RODAS = window.PV_RODAS || [];
  const TOTAL = 17.5; // segundos de um ciclo da apresentacao

  const S = {
    car: null, frames: [], aro: 15, roda: null, paint: null, original: false, adjust: false,
    play: true, t: 0, last: performance.now(), yaw: 0, drag: null, rimCache: new Map(),
    dust: Array.from({ length: 46 }, () => ({ x: Math.random(), y: Math.random(), s: 0.4 + Math.random() * 1.4, v: 0.004 + Math.random() * 0.012 })),
  };

  /* ---------- pneus: o original do carro e o equivalente de cada aro (mesmo diametro) ---------- */
  function parseTire(t) { const m = /(\d{3})\/(\d{2})\s*R(\d{2})/i.exec(t || '') || [0, 185, 60, 15]; return { w: +m[1], p: +m[2], a: +m[3] }; }
  const basePneu = () => (S.car && S.car.pneu) || '185/60 R15';
  function tire(aro) {
    const o = parseTire(basePneu()), D = o.a * 25.4 + 2 * o.w * o.p / 100;
    let l = o.w, p = o.p;
    if (aro !== o.a) {
      l = o.w + 10 * (aro - o.a); l = Math.max(145, Math.min(335, Math.round((l - 5) / 10) * 10 + 5));
      p = Math.round(((D - aro * 25.4) / 2 / l * 100) / 5) * 5; p = Math.max(25, Math.min(85, p));
    }
    const flanco = l * p / 100, diam = aro * 25.4 + 2 * flanco;
    return { l, p, a: aro, flanco, diam, diff: (diam / D - 1) * 100, ratio: (aro * 25.4) / diam };
  }
  function aroRange() { const a = parseTire(basePneu()).a, r = []; for (let i = Math.max(13, a - 1); i <= Math.min(22, a + 4); i++) r.push(i); return r; }

  /* ---------- modelo de remocao de fundo (U2-Netp, 4,5 MB, roda no navegador) ---------- */
  let session = null;
  async function model() {
    if (session) return session;
    busy('Preparando o removedor de fundo (só na primeira vez)...');
    ort.env.wasm.wasmPaths = 'vendor/ort/';
    ort.env.wasm.numThreads = 1;
    session = await ort.InferenceSession.create('models/u2netp.onnx', { executionProviders: ['wasm'] });
    return session;
  }
  // remove o fundo: primeiro no servidor (funciona em qualquer celular), se falhar tenta no proprio aparelho
  async function cutout(src) {
    let errSrv = null;
    try { return await cutoutServer(src); } catch (e) { errSrv = e; console.warn('servidor', e); }
    try { return await cutoutLocal(src); } catch (e) {
      throw new Error((errSrv ? errSrv.message + ' / ' : '') + 'no aparelho: ' + (e && e.message ? e.message : e));
    }
  }
  async function cutoutServer(src) {
    const blob = await new Promise((ok) => src.toBlob(ok, 'image/jpeg', 0.92));
    if (!blob) throw new Error('não consegui ler a foto');
    const fd = new FormData(); fd.append('action', 'cutout'); fd.append('foto', blob, 'foto.jpg');
    const ctl = new AbortController(), to = setTimeout(() => ctl.abort(), 60000);
    let r;
    try { r = await fetch(location.pathname + location.search, { method: 'POST', body: fd, headers: { 'X-CSRF-TOKEN': window.PV_CSRF || '' }, credentials: 'same-origin', signal: ctl.signal }); } finally { clearTimeout(to); }
    if (!r.ok || !(r.headers.get('Content-Type') || '').includes('image/png')) {
      const j = await r.json().catch(() => ({})); throw new Error(j.error || 'servidor respondeu ' + r.status);
    }
    const box = (r.headers.get('X-Box') || '').split(',').map(Number);
    const url = URL.createObjectURL(await r.blob());
    try {
      const img = await loadImg(url), c = document.createElement('canvas'); c.width = img.width; c.height = img.height;
      c.getContext('2d').drawImage(img, 0, 0);
      const o = document.createElement('canvas'); o.width = c.width; o.height = c.height;
      const [x0, y0, x1, y1, W, H] = box.length >= 6 ? box : [0, 0, src.width, src.height, src.width, src.height];
      const kx = src.width / (W || src.width), ky = src.height / (H || src.height);
      o.getContext('2d').drawImage(src, x0 * kx, y0 * ky, (x1 - x0) * kx, (y1 - y0) * ky, 0, 0, o.width, o.height);
      return { canvas: c, orig: o };
    } finally { URL.revokeObjectURL(url); }
  }
  async function cutoutLocal(src) {
    const s = await model();
    const N = 320, t = document.createElement('canvas'); t.width = t.height = N;
    const tc = t.getContext('2d', { willReadFrequently: true }); tc.drawImage(src, 0, 0, N, N);
    const d = tc.getImageData(0, 0, N, N).data, x = new Float32Array(3 * N * N);
    const mean = [0.485, 0.456, 0.406], std = [0.229, 0.224, 0.225];
    for (let i = 0; i < N * N; i++) for (let c = 0; c < 3; c++) x[c * N * N + i] = (d[i * 4 + c] / 255 - mean[c]) / std[c];
    const out = await s.run({ [s.inputNames[0]]: new ort.Tensor('float32', x, [1, 3, N, N]) });
    const m = out[s.outputNames[0]].data;
    let mn = Infinity, mx = -Infinity; for (let i = 0; i < N * N; i++) { if (m[i] < mn) mn = m[i]; if (m[i] > mx) mx = m[i]; }
    // mascara 320 -> tamanho da foto (suavizada pelo redimensionamento)
    const mc = document.createElement('canvas'); mc.width = mc.height = N;
    const mctx = mc.getContext('2d'), md = mctx.createImageData(N, N);
    const v = new Float32Array(N * N);
    for (let i = 0; i < N * N; i++) v[i] = Math.max(0, Math.min(1, ((m[i] - mn) / (mx - mn + 1e-6) - 0.12) / 0.6));
    // fica so com a maior mancha (o carro): tira pessoas e objetos soltos ao redor
    const lab = new Int32Array(N * N), sizes = [0], stack = [];
    for (let i = 0; i < N * N; i++) {
      if (v[i] < 0.3 || lab[i]) continue;
      const id = sizes.length; let n = 0; lab[i] = id; stack.push(i);
      while (stack.length) {
        const q = stack.pop(); n++; const qx = q % N, qy = (q / N) | 0;
        for (const nb of [qx > 0 ? q - 1 : -1, qx < N - 1 ? q + 1 : -1, qy > 0 ? q - N : -1, qy < N - 1 ? q + N : -1]) {
          if (nb >= 0 && !lab[nb] && v[nb] >= 0.3) { lab[nb] = id; stack.push(nb); }
        }
      }
      sizes.push(n);
    }
    let keep = 1; for (let k = 2; k < sizes.length; k++) if (sizes[k] > sizes[keep]) keep = k;
    for (let i = 0; i < N * N; i++) {
      let a = v[i];
      if (a >= 0.3 && lab[i] !== keep) a = 0;
      else if (a < 0.3 && a > 0) { // borda suave: mantem so se encostar no carro
        const qx = i % N, qy = (i / N) | 0; let near = false;
        for (let dy = -2; dy <= 2 && !near; dy++) for (let dx = -2; dx <= 2; dx++) { const xx = qx + dx, yy = qy + dy; if (xx >= 0 && yy >= 0 && xx < N && yy < N && lab[yy * N + xx] === keep) { near = true; break; } }
        if (!near) a = 0;
      }
      md.data[i * 4 + 3] = a * 255;
    }
    mctx.putImageData(md, 0, 0);
    const W = src.width, H = src.height, out2 = document.createElement('canvas'); out2.width = W; out2.height = H;
    const oc = out2.getContext('2d', { willReadFrequently: true });
    oc.drawImage(mc, 0, 0, W, H); oc.globalCompositeOperation = 'source-in'; oc.drawImage(src, 0, 0);
    // recorta no tamanho do carro
    const a = oc.getImageData(0, 0, W, H).data;
    let x0 = W, y0 = H, x1 = 0, y1 = 0;
    for (let y = 0; y < H; y += 2) for (let xx = 0; xx < W; xx += 2) if (a[(y * W + xx) * 4 + 3] > 128) { if (xx < x0) x0 = xx; if (xx > x1) x1 = xx; if (y < y0) y0 = y; if (y > y1) y1 = y; }
    if (x1 <= x0) return { canvas: out2, orig: src, box: [0, 0, W, H] };
    const pad = Math.round((x1 - x0) * 0.02);
    x0 = Math.max(0, x0 - pad); y0 = Math.max(0, y0 - pad); x1 = Math.min(W, x1 + pad); y1 = Math.min(H, y1 + pad);
    const c = document.createElement('canvas'); c.width = x1 - x0; c.height = y1 - y0;
    c.getContext('2d').drawImage(out2, x0, y0, c.width, c.height, 0, 0, c.width, c.height);
    const o = document.createElement('canvas'); o.width = c.width; o.height = c.height;
    o.getContext('2d').drawImage(src, x0, y0, c.width, c.height, 0, 0, c.width, c.height);
    return { canvas: c, orig: o };
  }

  /* ---------- acha as rodas: pneus sao as manchas escuras na parte de baixo do carro ---------- */
  function findWheels(c) {
    const W = c.width, H = c.height, d = c.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, W, H).data;
    const y0 = Math.round(H * 0.5), col = new Float32Array(W), bottom = new Int32Array(W);
    for (let x = 0; x < W; x++) {
      let n = 0, b = 0;
      for (let y = y0; y < H; y++) {
        const i = (y * W + x) * 4; if (d[i + 3] < 128) continue; b = y;
        const l = 0.3 * d[i] + 0.59 * d[i + 1] + 0.11 * d[i + 2]; if (l < 70) n++;
      }
      col[x] = n; bottom[x] = b;
    }
    const half = (from, to) => {
      let best = -1, bx = 0;
      for (let x = from; x < to; x++) { const s = col[x]; if (s > best) { best = s; bx = x; } }
      if (best < H * 0.04) return null;
      const thr = best * 0.25; let l = bx, r = bx, gap = 0;
      for (let x = bx; x > from && gap < W * 0.04; x--) { if (col[x] > thr) { l = x; gap = 0; } else gap++; }
      gap = 0;
      for (let x = bx; x < to && gap < W * 0.04; x++) { if (col[x] > thr) { r = x; gap = 0; } else gap++; }
      const rad = (r - l) / 2; if (rad < W * 0.03 || rad > W * 0.2) return null;
      const cx = (l + r) / 2; let by = 0; for (let x = Math.round(l); x <= r; x++) by = Math.max(by, bottom[x]);
      return { x: cx, y: by - rad, r: rad, sq: 1 };
    };
    const a = half(0, Math.round(W * 0.5)), b = half(Math.round(W * 0.5), W);
    if (a && b) { const rr = Math.max(a.r, b.r) * 0.98; return [a, b].map((w) => ({ ...w, r: Math.max(w.r, rr * 0.85) })); }
    // nao achou: chute pelas proporcoes de um carro de lado
    const r = W * 0.085;
    return [{ x: W * 0.2, y: H - r * 1.02, r, sq: 1 }, { x: W * 0.8, y: H - r * 1.02, r, sq: 1 }];
  }

  /* ---------- pneu e roda desenhada (enquanto a foto da roda carrega) ---------- */
  function drawTire(g, R, ratio) {
    const rr = R * ratio, grd = g.createRadialGradient(0, 0, rr, 0, 0, R);
    grd.addColorStop(0, '#0b0b0b'); grd.addColorStop(0.5, '#232323'); grd.addColorStop(0.92, '#151515'); grd.addColorStop(1, '#070707');
    g.fillStyle = grd; g.beginPath(); g.arc(0, 0, R, 0, Math.PI * 2); g.fill();
    g.strokeStyle = 'rgba(255,255,255,.07)'; g.lineWidth = R * 0.012; g.beginPath(); g.arc(0, 0, (R + rr) / 2, 0, Math.PI * 2); g.stroke();
    return rr;
  }
  function sysRim(g, rr, id) {
    // desenho 2D simples: tambor escuro, aro claro, raios
    g.fillStyle = '#121212'; g.beginPath(); g.arc(0, 0, rr, 0, Math.PI * 2); g.fill();
    const face = g.createLinearGradient(-rr, -rr, rr, rr); face.addColorStop(0, '#f2f3f5'); face.addColorStop(0.5, '#a9adb2'); face.addColorStop(1, '#e6e7ea');
    g.strokeStyle = face; g.lineWidth = rr * 0.09; g.beginPath(); g.arc(0, 0, rr * 0.95, 0, Math.PI * 2); g.stroke();
    const spoke = (a, w, tw) => {
      g.save(); g.rotate(a); g.fillStyle = face; g.beginPath();
      g.moveTo(rr * 0.22, -w * 0.6); g.lineTo(rr * 0.92, -w * (tw || 1)); g.lineTo(rr * 0.92, w * (tw || 1) * 0.3); g.lineTo(rr * 0.22, w * 0.6); g.closePath(); g.fill();
      g.fillStyle = 'rgba(0,0,0,.35)'; g.fillRect(rr * 0.25, w * 0.15, rr * 0.62, w * 0.2); g.restore();
    };
    const n = { esportiva: 5, multi: 10, duplo: 5, turbina: 12 }[id] || 5;
    for (let i = 0; i < n; i++) {
      const a = i * Math.PI * 2 / n;
      if (id === 'esportiva') spoke(a, rr * 0.16);
      else if (id === 'multi') spoke(a, rr * 0.06);
      else if (id === 'duplo') { spoke(a - 0.14, rr * 0.07); spoke(a + 0.14, rr * 0.07); }
      else spoke(a, rr * 0.1, 1.8);
    }
    g.fillStyle = '#d7d9dc'; g.beginPath(); g.arc(0, 0, rr * 0.24, 0, Math.PI * 2); g.fill();
    g.fillStyle = '#1a1a1a'; g.beginPath(); g.arc(0, 0, rr * 0.11, 0, Math.PI * 2); g.fill();
    for (let i = 0; i < 4; i++) { const a = i * Math.PI / 2 + 0.78; g.fillStyle = '#777'; g.beginPath(); g.arc(Math.cos(a) * rr * 0.17, Math.sin(a) * rr * 0.17, rr * 0.025, 0, Math.PI * 2); g.fill(); }
  }
  function loadImg(url) {
    return new Promise((ok, fail) => { const i = new Image(); i.onload = () => ok(i); i.onerror = fail; i.src = url; });
  }
  function toCanvas(img, max) {
    const k = Math.min(1, max / Math.max(img.width || img.videoWidth, img.height || img.videoHeight));
    const w = Math.round((img.width || img.videoWidth) * k), h = Math.round((img.height || img.videoHeight) * k);
    const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(img, 0, 0, w, h); return c;
  }

  /* ---------- roda escolhida (foto ja limpa do catalogo, ou foto do estoque com fundo removido) ---------- */
  const rimImages = new Map();
  async function prepareRim(id, src) {
    const c = trimAlpha(src) || (await cutout(src)).canvas, side = Math.max(c.width, c.height), sq = document.createElement('canvas');
    sq.width = sq.height = side; sq.getContext('2d').drawImage(c, (side - c.width) / 2, (side - c.height) / 2);
    rimImages.set(id, sq); S.rimCache.clear();
  }
  function wheelSprite() {
    const key = S.aro + '|' + (S.roda ? S.roda.id : '-') + '|' + basePneu();
    if (S.rimCache.has(key)) return S.rimCache.get(key);
    const R = 256, cnv = document.createElement('canvas'); cnv.width = cnv.height = R * 2;
    const g = cnv.getContext('2d'); g.translate(R, R);
    const rr = drawTire(g, R - 1, tire(S.aro).ratio), rim = S.roda && rimImages.get(S.roda.id);
    if (!rim) { sysRim(g, rr, 'esportiva'); return cnv; }
    g.fillStyle = '#0d0d0d'; g.beginPath(); g.arc(0, 0, rr, 0, Math.PI * 2); g.fill();
    g.save(); g.beginPath(); g.arc(0, 0, rr, 0, Math.PI * 2); g.clip(); g.drawImage(rim, -rr, -rr, rr * 2, rr * 2); g.restore();
    S.rimCache.set(key, cnv); return cnv;
  }

  /* ---------- carro de um quadro: lataria (com a cor escolhida) + rodas girando + reflexo de luz passando ---------- */
  function frameImage(f, rot, sweep) {
    const W = f.canvas.width, H = f.canvas.height;
    if (!f._off) { f._off = document.createElement('canvas'); f._off.width = W; f._off.height = H; }
    const g = f._off.getContext('2d'); g.globalCompositeOperation = 'source-over'; g.clearRect(0, 0, W, H);
    g.drawImage(!S.original && S.paint ? painted(f, S.paint.hex) : f.canvas, 0, 0);
    if (!S.original && f.wheelsOn) {
      const sp = wheelSprite();
      f.wheels.forEach((w) => { g.save(); g.translate(w.x, w.y); g.scale(w.sq, 1); g.rotate(rot); g.drawImage(sp, -w.r * 1.04, -w.r * 1.04, w.r * 2.08, w.r * 2.08); g.restore(); });
    }
    if (sweep > 0 && sweep < 1) { // faixa de luz atravessando a lataria
      g.globalCompositeOperation = 'source-atop';
      const x = -W * 0.4 + sweep * W * 1.8, gr = g.createLinearGradient(x - W * 0.18, 0, x + W * 0.18, H * 0.4);
      gr.addColorStop(0, 'rgba(255,255,255,0)'); gr.addColorStop(0.5, 'rgba(255,255,255,0.55)'); gr.addColorStop(1, 'rgba(255,255,255,0)');
      g.fillStyle = gr; g.fillRect(0, 0, W, H);
    }
    return f._off;
  }
  function mirrorOf(img) {
    const m = img._m || (img._m = document.createElement('canvas'));
    m.width = img.width; m.height = img.height;
    const g = m.getContext('2d'); g.setTransform(-1, 0, 0, 1, img.width, 0); g.clearRect(0, 0, img.width, img.height); g.drawImage(img, 0, 0);
    return m;
  }

  /* ---------- palco ---------- */
  function layout(W, H) {
    const tall = H > W * 1.05, cx = W / 2, floor = H * (tall ? 0.6 : 0.76), rx = Math.min(W * (tall ? 0.47 : 0.43), H * 0.98), ry = rx * 0.17;
    return { W, H, cx, floor, rx, ry };
  }
  function carSize(L, f) {
    const k = Math.min((L.rx * 1.72) / f.canvas.width, (L.H * 0.5) / f.canvas.height);
    return { w: f.canvas.width * k, h: f.canvas.height * k, k };
  }
  function background(g, L, t) {
    const bg = g.createRadialGradient(L.cx, L.H * 0.38, 10, L.cx, L.H * 0.45, Math.max(L.W, L.H) * 0.8);
    bg.addColorStop(0, '#2d2b25'); bg.addColorStop(0.45, '#151411'); bg.addColorStop(1, '#050504');
    g.fillStyle = bg; g.fillRect(0, 0, L.W, L.H);
    // paredes de vitrine: faixas de luz verticais ao fundo
    for (let i = -3; i <= 3; i++) {
      const x = L.cx + i * L.W * 0.15, a = 0.05 - Math.abs(i) * 0.008;
      const gr = g.createLinearGradient(0, 0, 0, L.floor); gr.addColorStop(0, `rgba(255,214,90,${a})`); gr.addColorStop(1, 'rgba(255,214,90,0)');
      g.fillStyle = gr; g.fillRect(x - L.W * 0.004, 0, L.W * 0.008, L.floor);
    }
    g.save(); g.globalAlpha = 0.06; g.fillStyle = '#ffd21a'; g.font = `800 ${Math.round(Math.min(L.W * 0.06, L.H * 0.075))}px Inter, Arial, sans-serif`; g.textAlign = 'center';
    g.fillText('NETO RODAS E PNEUS', L.cx, L.H * 0.2); g.restore();
    // feixe de luz de cima + poeira no ar
    const beam = g.createLinearGradient(0, 0, 0, L.floor);
    beam.addColorStop(0, 'rgba(255,240,200,0.16)'); beam.addColorStop(1, 'rgba(255,240,200,0.01)');
    g.fillStyle = beam; g.beginPath(); g.moveTo(L.cx - L.rx * 0.18, 0); g.lineTo(L.cx + L.rx * 0.18, 0); g.lineTo(L.cx + L.rx * 1.05, L.floor); g.lineTo(L.cx - L.rx * 1.05, L.floor); g.fill();
    S.dust.forEach((p) => {
      const y = ((p.y - t * p.v) % 1 + 1) % 1, spread = 0.18 + y * 0.87, x = L.cx + (p.x - 0.5) * 2 * L.rx * spread;
      g.fillStyle = `rgba(255,236,190,${0.12 + 0.25 * Math.sin(t * 2 + p.x * 9) ** 2})`;
      g.beginPath(); g.arc(x, y * L.floor, p.s * L.W / 1200, 0, Math.PI * 2); g.fill();
    });
    // piso brilhante com linhas em perspectiva
    const fl = g.createLinearGradient(0, L.floor - L.ry, 0, L.H);
    fl.addColorStop(0, '#16150f'); fl.addColorStop(1, '#030303');
    g.fillStyle = fl; g.fillRect(0, L.floor - L.ry * 0.9, L.W, L.H);
    g.strokeStyle = 'rgba(255,255,255,0.035)'; g.lineWidth = 1;
    for (let i = -12; i <= 12; i++) { g.beginPath(); g.moveTo(L.cx + i * L.W * 0.02, L.floor - L.ry * 0.9); g.lineTo(L.cx + i * L.W * 0.2, L.H); g.stroke(); }
  }
  function platform(g, L, spin, flash) {
    g.save(); g.translate(L.cx, L.floor);
    g.fillStyle = '#080807'; g.beginPath(); g.ellipse(0, L.ry * 0.32, L.rx * 1.015, L.ry * 1.06, 0, 0, Math.PI * 2); g.fill();
    g.save(); g.scale(1, L.ry / L.rx);
    const top = g.createRadialGradient(0, 0, 4, 0, 0, L.rx); top.addColorStop(0, '#3a382f'); top.addColorStop(0.7, '#1d1c18'); top.addColorStop(1, '#121210');
    g.fillStyle = top; g.beginPath(); g.arc(0, 0, L.rx, 0, Math.PI * 2); g.fill();
    g.strokeStyle = 'rgba(255,255,255,0.05)'; g.lineWidth = L.rx * 0.005;
    for (let i = 0; i < 48; i++) { const a = spin + i * Math.PI / 24; g.beginPath(); g.moveTo(Math.cos(a) * L.rx * 0.25, Math.sin(a) * L.rx * 0.25); g.lineTo(Math.cos(a) * L.rx * 0.93, Math.sin(a) * L.rx * 0.93); g.stroke(); }
    // anel de LED com pontos correndo
    g.shadowColor = '#ffd21a'; g.shadowBlur = L.rx * 0.05; g.strokeStyle = '#ffd21a'; g.lineWidth = L.rx * 0.012;
    g.beginPath(); g.arc(0, 0, L.rx * 0.975, 0, Math.PI * 2); g.stroke();
    g.shadowBlur = 0; g.fillStyle = '#fff6c8';
    for (let i = 0; i < 24; i++) { const a = spin * 1.5 + i * Math.PI / 12; g.beginPath(); g.arc(Math.cos(a) * L.rx * 0.975, Math.sin(a) * L.rx * 0.975, L.rx * 0.008, 0, Math.PI * 2); g.fill(); }
    g.restore();
    if (flash > 0) { const fx = g.createRadialGradient(0, 0, 2, 0, 0, L.rx * 1.1); fx.addColorStop(0, `rgba(255,248,220,${flash})`); fx.addColorStop(1, 'rgba(255,248,220,0)'); g.fillStyle = fx; g.beginPath(); g.ellipse(0, -L.ry * 2, L.rx * 1.1, L.rx * 0.6, 0, 0, Math.PI * 2); g.fill(); }
    g.restore();
  }
  // desenha o carro girando na plataforma com perspectiva (fatias verticais: o lado mais perto fica maior)
  function project(g, img, L, size, yaw, dx) {
    const N = 56, cs = Math.cos(yaw), sn = Math.sin(yaw), F = size.w * 2.4, ox = L.cx + dx;
    const pts = [];
    for (let i = 0; i <= N; i++) {
      const x = (i / N - 0.5) * size.w, X = x * cs, Z = -x * sn * 0.9, p = F / (F + Z);
      pts.push({ sx: ox + X * p, p });
    }
    for (let i = 0; i < N; i++) {
      const a = pts[i], b = pts[i + 1], w = b.sx - a.sx; if (Math.abs(w) < 0.01) continue;
      const p = (a.p + b.p) / 2, h = size.h * p, base = L.floor + L.ry * 0.25 + (p - 1) * L.ry * 0.9;
      const sx0 = img.width * i / N, sw = img.width / N;
      g.drawImage(img, sx0, 0, sw, img.height, Math.min(a.sx, b.sx) - 0.4, base - h, Math.abs(w) + 0.8, h);
    }
    return { left: Math.min(pts[0].sx, pts[N].sx), right: Math.max(pts[0].sx, pts[N].sx) };
  }
  function drawCar(g, L, f, yaw, dx, rot, sweep, alpha) {
    const size = carSize(L, f); let img = frameImage(f, rot, sweep), y = yaw;
    const c = Math.cos(yaw);
    if (c < 0) { img = mirrorOf(img); y = yaw - Math.PI; }
    const cs = Math.max(0.06, Math.abs(Math.cos(y)));
    const yy = Math.sign(Math.sin(y)) * Math.acos(cs);
    // sombra no chao
    g.save(); g.globalAlpha = 0.6 * alpha;
    const sw = size.w * (0.5 * cs + 0.12), sh = g.createRadialGradient(L.cx + dx, L.floor + L.ry * 0.25, 2, L.cx + dx, L.floor + L.ry * 0.25, sw);
    sh.addColorStop(0, 'rgba(0,0,0,.95)'); sh.addColorStop(1, 'rgba(0,0,0,0)'); g.fillStyle = sh;
    g.beginPath(); g.ellipse(L.cx + dx, L.floor + L.ry * 0.25, sw, L.ry * 0.5, 0, 0, Math.PI * 2); g.fill(); g.restore();
    // reflexo no piso
    g.save(); g.globalAlpha = 0.2 * alpha; const fy = L.floor + L.ry * 0.25;
    g.translate(0, fy * 2); g.scale(1, -1); project(g, img, L, size, yy, dx); g.restore();
    const fade = g.createLinearGradient(0, fy, 0, fy + size.h * 0.6); fade.addColorStop(0, 'rgba(10,10,9,0)'); fade.addColorStop(1, 'rgba(10,10,9,0.95)');
    g.fillStyle = fade; g.fillRect(0, fy, L.W, size.h * 0.6);
    g.save(); g.globalAlpha = alpha; const r = project(g, img, L, size, yy, dx); g.restore();
    return { size, ...r };
  }

  /* ---------- roteiro da apresentacao ---------- */
  const ease = (x) => (x < 0 ? 0 : x > 1 ? 1 : x * x * (3 - 2 * x));
  const seg = (t, a, b) => ease((t - a) / (b - a));
  function scene(t) {
    // 0-2.6 entra rodando | 2.6-4.2 luz | 4.2-8.6 gira | 8.6-9.6 vira | 9.6-12 outro lado | 12-12.8 volta | 12.8-15.4 zoom na roda | 16-17.5 sai
    const s = { dx: 0, yaw: 0, zoom: 1, sweep: 0, flash: 0, callout: 0 };
    const inK = 1 - Math.pow(1 - Math.min(1, t / 2.6), 3);
    if (t < 2.6) s.dx = -(1 - inK) * 1.35;
    if (t > 16) s.dx = Math.pow((t - 16) / 1.5, 2) * 1.35;
    s.sweep = t > 2.6 && t < 4.2 ? (t - 2.6) / 1.6 : 0;
    if (t > 4.2 && t < 8.6) s.yaw = Math.sin((t - 4.2) / 4.4 * Math.PI * 2) * 0.62;
    if (t >= 8.6 && t < 9.6) { const k = seg(t, 8.6, 9.6); s.yaw = k * Math.PI; s.flash = Math.sin(k * Math.PI) * 0.5; }
    if (t >= 9.6 && t < 12) s.yaw = Math.PI + Math.sin((t - 9.6) / 2.4 * Math.PI * 2) * 0.45;
    if (t >= 12 && t < 12.8) { const k = seg(t, 12, 12.8); s.yaw = Math.PI + k * Math.PI; s.flash = Math.sin(k * Math.PI) * 0.5; }
    if (t >= 12.8 && t < 16) { s.zoom = 1 + 1.25 * (seg(t, 12.8, 13.8) - seg(t, 15.2, 16)); s.callout = seg(t, 13.4, 13.9) - seg(t, 15, 15.3); s.yaw = -0.18 * (seg(t, 12.8, 13.8) - seg(t, 15.2, 16)); }
    return s;
  }

  function resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2), w = cv.clientWidth, h = cv.clientHeight;
    if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(h * dpr)) { cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr); }
  }
  function render(g, W, H, t, opts) {
    const L = layout(W, H), f = S.frames[0];
    let sc = opts || (S.play && !S.adjust ? scene(t) : { dx: 0, yaw: S.yaw, zoom: 1, sweep: 0, flash: 0, wheelSpin: 0, callout: 0 });
    background(g, L, t);
    // camera: aproxima na roda da frente
    let focus = null;
    if (f && sc.zoom > 1) {
      const size = carSize(L, f), w = f.wheels[f.wheels.length - 1] || { x: f.canvas.width * 0.8, y: f.canvas.height * 0.75 };
      focus = { x: L.cx + (w.x / f.canvas.width - 0.5) * size.w, y: L.floor + L.ry * 0.25 - size.h + w.y / f.canvas.height * size.h };
    }
    g.save();
    if (focus) { const k = (sc.zoom - 1) / 1.25; const fx = L.cx + (focus.x - L.cx) * k, fy = L.H * 0.55 + (focus.y - L.H * 0.55) * k; g.translate(L.cx, L.H * 0.52); g.scale(sc.zoom, sc.zoom); g.translate(-fx, -fy); }
    platform(g, L, t * 0.35 + sc.yaw, sc.flash);
    let box = null;
    if (f) {
      // com varias fotos (angulos), usa a foto mais proxima do giro
      let fr = f, yaw = sc.yaw;
      if (S.frames.length > 1) { const n = S.frames.length, k = ((yaw / (Math.PI * 2)) * n % n + n) % n, i = Math.round(k) % n; fr = S.frames[i]; yaw = (k - Math.round(k)) * (Math.PI * 2 / n); }
      const size = carSize(L, fr), dxPx = sc.dx * L.W;
      const spin = S.play && !S.adjust ? -dxPx / Math.max(1, (fr.wheels[0] ? fr.wheels[0].r : 40) * size.k) : 0;
      box = drawCar(g, L, fr, yaw, dxPx, spin, sc.sweep, 1);
      if (S.adjust && fr.wheelsOn) marks(g, L, fr);
    }
    g.restore();
    if (sc.flash > 0) { g.fillStyle = `rgba(255,250,230,${sc.flash * 0.35})`; g.fillRect(0, 0, W, H); }
    // vinheta
    const vg = g.createRadialGradient(L.cx, L.H * 0.5, Math.min(W, H) * 0.35, L.cx, L.H * 0.5, Math.max(W, H) * 0.75);
    vg.addColorStop(0, 'rgba(0,0,0,0)'); vg.addColorStop(1, 'rgba(0,0,0,0.55)'); g.fillStyle = vg; g.fillRect(0, 0, W, H);
    if (sc.callout > 0 && f) callout(g, L, sc.callout);
    return box;
  }
  function callout(g, L, a) {
    const t = tire(S.aro), nome = S.roda ? S.roda.nome.replace(/^RODA\s+/i, '') : 'Roda';
    const fs = Math.round(Math.min(L.H * 0.04, L.W * 0.045)), x = L.W * 0.06, y = L.H * 0.27;
    g.save(); g.globalAlpha = a;
    g.fillStyle = 'rgba(10,10,9,0.78)'; g.fillRect(x - fs * 0.6, y - fs * 1.5, Math.max(L.W * 0.36, fs * 14), fs * 3.6);
    g.fillStyle = '#ffd21a'; g.fillRect(x - fs * 0.6, y - fs * 1.5, fs * 0.25, fs * 3.6);
    g.fillStyle = '#fff'; g.font = `800 ${fs}px Inter, Arial, sans-serif`; g.fillText(nome.toUpperCase(), x, y);
    g.fillStyle = '#ffd21a'; g.font = `700 ${Math.round(fs * 0.75)}px Inter, Arial, sans-serif`;
    g.fillText(`ARO ${t.a} · PNEU ${t.l}/${t.p} R${t.a}`, x, y + fs * 1.3);
    g.restore();
  }
  function loop(now) {
    resize();
    const dt = Math.min(0.05, (now - S.last) / 1000); S.last = now;
    if (S.play && !S.adjust && S.frames.length) S.t = (S.t + dt) % TOTAL;
    render(ctx, cv.width, cv.height, S.play ? S.t : now / 1000);
    info();
    requestAnimationFrame(loop);
  }

  /* ---------- arrastar: gira na mao (ou ajusta rodas, admin) ---------- */
  function carRect(L, f) {
    const size = carSize(L, f);
    return { x: L.cx - size.w / 2, y: L.floor + L.ry * 0.25 - size.h, k: size.k };
  }
  function marks(g, L, f) {
    const R = carRect(L, f);
    f.wheels.forEach((w) => {
      const x = R.x + w.x * R.k, y = R.y + w.y * R.k, r = w.r * R.k, lw = Math.max(2, L.W / 600);
      g.save(); g.strokeStyle = '#ffd21a'; g.lineWidth = lw; g.setLineDash([lw * 4, lw * 3]);
      g.beginPath(); g.ellipse(x, y, r * w.sq, r, 0, 0, Math.PI * 2); g.stroke(); g.setLineDash([]);
      g.fillStyle = '#ffd21a'; g.beginPath(); g.arc(x + r * w.sq, y, lw * 6, 0, Math.PI * 2); g.fill();
      g.beginPath(); g.arc(x, y - r, lw * 5, 0, Math.PI * 2); g.fill(); g.restore();
    });
  }
  function hit(e) {
    const b = cv.getBoundingClientRect(), dpr = cv.width / b.width, L = layout(cv.width, cv.height), f = S.frames[0]; if (!f) return null;
    const R = carRect(L, f), px = (e.clientX - b.left) * dpr, py = (e.clientY - b.top) * dpr;
    return { f, x: (px - R.x) / R.k, y: (py - R.y) / R.k, tol: 24 * dpr / R.k };
  }
  cv.addEventListener('pointerdown', (e) => {
    const h = hit(e); if (!h) return;
    if (S.adjust && h.f.wheelsOn) {
      for (const [i, w] of h.f.wheels.entries()) {
        if (Math.hypot(h.x - (w.x + w.r * w.sq), h.y - w.y) < h.tol) { S.drag = { i, m: 'w' }; break; }
        if (Math.hypot(h.x - w.x, h.y - (w.y - w.r)) < h.tol) { S.drag = { i, m: 'h' }; break; }
        if (Math.hypot((h.x - w.x) / w.sq, h.y - w.y) < w.r) { S.drag = { i, m: 'm', dx: h.x - w.x, dy: h.y - w.y }; break; }
      }
    }
    if (!S.drag && !S.adjust) { if (S.play) { S.yaw = scene(S.t).yaw; } S.drag = { m: 'spin', x0: e.clientX, y0: S.yaw }; setPlay(false); }
    if (S.drag) { cv.setPointerCapture(e.pointerId); e.preventDefault(); }
  });
  cv.addEventListener('pointermove', (e) => {
    const d = S.drag; if (!d) return;
    if (d.m === 'spin') { S.yaw = d.y0 + (e.clientX - d.x0) / cv.clientWidth * Math.PI * 2; return; }
    const h = hit(e), w = h.f.wheels[d.i];
    if (d.m === 'm') { w.x = h.x - d.dx; w.y = h.y - d.dy; }
    if (d.m === 'h') { w.r = Math.max(5, w.y - h.y); }
    if (d.m === 'w') { w.sq = Math.max(0.35, Math.min(1.2, Math.abs(h.x - w.x) / w.r)); }
    h.f.dirty = true; syncSave();
  });
  const end = () => { S.drag = null; };
  cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);

  function setPlay(on) {
    S.play = on; if (on) { S.t = 2.6; S.adjust = false; const a = $('pv-ajustar'); if (a) a.classList.remove('on'); syncSave(); }
    $('pv-play').classList.toggle('on', on); tvSync();
  }

  /* ---------- painel ---------- */
  const fmtMoney = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  function busy(t) { $('pv-busy').hidden = !t; if (t) $('pv-busy-t').textContent = t; }
  function alertMsg(t, ms) { const i = $('pv-info'); i.hidden = false; i.dataset.msg = '1'; i.textContent = t; clearTimeout(alertMsg.t); alertMsg.t = setTimeout(() => { delete i.dataset.msg; }, ms || 5000); }
  function info() {
    const el = $('pv-info'); if (el.dataset.msg) return;
    if (!S.car) { el.hidden = true; return; }
    const t = tire(S.aro); el.hidden = false;
    el.innerHTML = `<b>${esc(S.car.marca + ' ' + S.car.modelo)}</b> ${S.roda ? esc(S.roda.nome.replace(/^RODA\s+/i, '')) + ' · ' : ''}aro ${t.a} · ${t.l}/${t.p} R${t.a}`;
  }
  function syncBtns() {
    document.querySelectorAll('#pv-aros button').forEach((b) => b.classList.toggle('on', +b.dataset.v === S.aro));
    document.querySelectorAll('#pv-rodas button').forEach((b) => b.classList.toggle('on', !!S.roda && String(S.roda.id) === b.dataset.v));
    tvSync();
    const t = tire(S.aro), orig = parseTire(basePneu()).a === t.a;
    $('pv-aro-t').textContent = `Pneu ${t.l}/${t.p} R${t.a}${orig ? ' (original' + (S.car ? ' do ' + S.car.modelo : '') + ')' : ''}: flanco de ${Math.round(t.flanco)} mm, diâmetro total ${Math.round(t.diam)} mm${orig ? '' : ` (${t.diff >= 0 ? '+' : ''}${t.diff.toFixed(1).replace('.', ',')}% do original)`}.`;
  }
  function renderAros() {
    const aros = $('pv-aros'), list = aroRange(); aros.innerHTML = '';
    if (!list.includes(S.aro)) S.aro = list.includes(15) ? 15 : list[Math.min(2, list.length - 1)];
    S.rimCache.clear();
    list.forEach((a) => {
      const t = tire(a), b = document.createElement('button'); b.type = 'button'; b.className = 'pv-opt'; b.dataset.v = a;
      b.innerHTML = `${a}<small>${t.l}/${t.p}</small>`;
      b.onclick = () => { S.aro = a; S.rimCache.clear(); syncBtns(); }; aros.append(b);
    });
    syncBtns();
  }
  function renderRodas(q) {
    const box = $('pv-rodas'); box.innerHTML = ''; q = (q || '').toLowerCase();
    const list = RODAS.filter((r) => !q || (r.nome + ' ' + r.marca + ' aro ' + (r.aro || '')).toLowerCase().includes(q)).slice(0, 80);
    if (!list.length) { box.innerHTML = '<p class="small muted">Nenhuma roda encontrada.</p>'; return; }
    list.forEach((r) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-roda'; b.dataset.v = r.id;
      b.innerHTML = `<img src="${esc(r.img)}" alt="" loading="lazy"><span><b>${esc(r.nome.replace(/^RODA\s+/i, ''))}</b><small>${r.aro ? 'Aro ' + r.aro : ''}${r.estoque == null ? '' : ' · ' + r.estoque + ' em estoque · ' + fmtMoney(r.preco)}</small></span>`;
      b.onclick = () => pickRoda(r);
      box.append(b);
    });
    syncBtns();
  }
  async function pickRoda(r) {
    if (!rimImages.has(r.id)) {
      try { busy('Preparando a roda...'); await prepareRim(r.id, toCanvas(await loadImg(r.img), 900)); } catch (e) { alertMsg('Não consegui abrir a foto dessa roda.'); return; } finally { busy(null); }
    }
    S.roda = r; if (r.aro && aroRange().includes(+r.aro)) S.aro = +r.aro; tvSync();
    S.rimCache.clear(); syncBtns(); if (S.play) S.t = 12.8; // mostra a roda de perto
  }
  $('pv-q').oninput = (e) => renderRodas(e.target.value);
  $('pv-play').onclick = () => setPlay(!S.play);
  $('pv-antes').onclick = (e) => { S.original = !S.original; e.currentTarget.classList.toggle('on', S.original); e.currentTarget.textContent = S.original ? 'Depois' : 'Antes'; };
  if ($('pv-ajustar')) $('pv-ajustar').onclick = (e) => {
    S.adjust = !S.adjust; e.currentTarget.classList.toggle('on', S.adjust);
    if (S.adjust) { S.yaw = 0; setPlay(false); S.adjust = true; e.currentTarget.classList.add('on'); alertMsg('Arraste o círculo para o centro da roda. Bolinha de cima: tamanho. Bolinha do lado: deixa oval.'); }
    syncSave();
  };
  $('pv-save').onclick = () => {
    const c = document.createElement('canvas'); c.width = 1600; c.height = 1000;
    const g = c.getContext('2d'); render(g, 1600, 1000, 3, { dx: 0, yaw: -0.22, zoom: 1, sweep: 0, flash: 0, wheelSpin: 0, callout: 0 });
    callout(g, layout(1600, 1000), 1);
    $('pv-out-img').src = c.toDataURL('image/jpeg', 0.9); $('pv-out').hidden = false; $('pv-out-x').focus();
  };
  $('pv-out-x').onclick = () => { $('pv-out').hidden = true; };

  /* ---------- biblioteca de carros ---------- */
  const carFotos = (c) => c.fotos || [];
  function renderCars() {
    const box = $('pv-cars'), q = ($('pv-cq').value || '').toLowerCase().trim(); box.innerHTML = '';
    const list = CARS.filter((c) => !q || (c.marca + ' ' + c.modelo + ' ' + c.tipo).toLowerCase().includes(q))
      .sort((a, b) => (carFotos(b).length > 0) - (carFotos(a).length > 0) || (a.marca + a.modelo).localeCompare(b.marca + b.modelo));
    if (!list.length) { box.innerHTML = '<p class="small muted">Nenhum carro encontrado.</p>'; return; }
    list.slice(0, q ? 80 : 120).forEach((c) => {
      const f = carFotos(c)[0], b = document.createElement('button'); b.type = 'button';
      b.className = 'pv-car' + (S.car && S.car.id === c.id ? ' on' : '') + (f ? '' : ' sem');
      b.innerHTML = (f ? `<img src="${esc(f.src)}" alt="" loading="lazy">` : '<i>sem foto</i>') + `<span><b>${esc(c.modelo)}</b><small>${esc(c.marca)} · ${esc(c.pneu)}</small></span>`;
      b.onclick = () => loadCar(c);
      box.append(b);
    });
  }
  $('pv-cq').oninput = renderCars;
  async function loadCar(c) {
    S.car = c; S.paint = null; S.sets = null; S.realColor = null; S.adjust = false;
    { const r = aroRange(), ra = S.roda && +S.roda.aro; S.aro = ra && r.includes(ra) ? ra : parseTire(c.pneu).a; } renderCars(); renderAros(); adminCar(); renderCores();
    const fotos = carFotos(c);
    if (!fotos.length) {
      S.frames = []; $('pv-tools').hidden = true; $('pv-empty').hidden = false;
      $('pv-empty').querySelector('h2').textContent = c.marca + ' ' + c.modelo + ' ainda sem foto';
      $('pv-empty').querySelector('p').textContent = window.PV_ADMIN ? 'Envie o PNG sem fundo em "Biblioteca: fotos e cadastro de carros", no painel ao lado.' : 'Peça ao administrador para enviar a foto desse modelo.';
      return;
    }
    try {
      const frames = [];
      for (const [i, ft] of fotos.entries()) {
        busy(`Carregando ${c.modelo}${fotos.length > 1 ? ` (${i + 1} de ${fotos.length})` : ''}...`);
        const img = await loadImg(ft.src);
        const cnv = trimAlpha(img) || (await cutout(toCanvas(img, 1600))).canvas;
        const W = cnv.width, H = cnv.height, sv = ft.rodas;
        const wheels = sv && sv.list && sv.list.length ? sv.list.map((w) => ({ x: w.x * W, y: w.y * H, r: w.r * W, sq: w.sq || 1 })) : findWheels(cnv);
        frames.push({ canvas: cnv, label: ft.angulo, cor: ft.cor || '', wheels, wheelsOn: sv ? !!sv.on : /Lateral|3\/4/.test(ft.angulo), imgId: ft.id, carId: c.id });
      }
      if (S.car !== c) return;
      // fotos em cores reais: cada cor vira um conjunto (a branca e a base da simulacao das outras cores)
      S.sets = {}; frames.forEach((f) => { (S.sets[f.cor] = S.sets[f.cor] || []).push(f); });
      const nomes = Object.keys(S.sets);
      S.realColor = nomes.find((n) => /branc/i.test(n)) || nomes[0];
      S.frames = S.sets[S.realColor]; S.original = false; renderCores(); $('pv-antes').classList.remove('on'); $('pv-antes').textContent = 'Antes';
      $('pv-empty').hidden = true; $('pv-tools').hidden = false; S.t = 0; setPlay(true); S.t = 0; syncSave();
    } catch (e) {
      console.error(e); alertMsg('Não consegui abrir a foto desse carro (' + (e && e.message ? e.message : e) + ').', 9000);
    } finally { busy(null); }
  }

  // admin: fotos do carro (angulo, apagar) e posicao das rodas
  function adminCar() {
    const box = $('pv-admin-car'); if (!box) return;
    box.hidden = !S.car; if (!S.car) return;
    const c = S.car;
    box.querySelectorAll('.pv-car-id').forEach((i) => { i.value = c.id; });
    box.querySelector('.pv-car-nome').textContent = c.marca + ' ' + c.modelo;
    const ed = $('pv-car-edit'); ed.brand.value = c.marca; ed.model.value = c.modelo; ed.tire.value = c.pneu;
    const list = $('pv-admin-imgs'), tok = esc(window.PV_CSRF || ''); list.innerHTML = '';
    if (!carFotos(c).length) { list.innerHTML = '<p class="small muted">Nenhuma foto ainda.</p>'; return; }
    carFotos(c).forEach((f) => {
      const d = document.createElement('div'); d.className = 'pv-aimg';
      d.innerHTML = `<img src="${esc(f.src)}" alt="">
        <form method="post"><input type="hidden" name="_csrf" value="${tok}"><input type="hidden" name="action" value="car_img_angle"><input type="hidden" name="id" value="${c.id}"><input type="hidden" name="img" value="${f.id}">
          <select name="angle" aria-label="Ângulo da foto" onchange="this.form.submit()">${ANGULOS.map((a) => `<option${a === f.angulo ? ' selected' : ''}>${a}</option>`).join('')}</select></form>
        <form method="post" onsubmit="return confirm('Apagar esta foto?')"><input type="hidden" name="_csrf" value="${tok}"><input type="hidden" name="action" value="car_img_delete"><input type="hidden" name="id" value="${c.id}"><input type="hidden" name="img" value="${f.id}">
          <button class="btn ghost sm" type="submit">Apagar</button></form>`;
      list.append(d);
    });
  }
  function syncSave() {
    const b = $('pv-salvar-rodas'); if (!b) return;
    b.hidden = !(S.adjust && S.frames.some((f) => f.imgId && f.dirty));
  }
  if ($('pv-salvar-rodas')) $('pv-salvar-rodas').onclick = async (e) => {
    const btn = e.currentTarget; btn.disabled = true;
    try {
      for (const f of S.frames.filter((x) => x.imgId && x.dirty)) {
        const W = f.canvas.width, H = f.canvas.height;
        const body = new URLSearchParams({ action: 'car_img_wheels', id: f.carId, img: f.imgId,
          wheels: JSON.stringify({ on: f.wheelsOn, list: f.wheels.map((w) => ({ x: +(w.x / W).toFixed(4), y: +(w.y / H).toFixed(4), r: +(w.r / W).toFixed(4), sq: +w.sq.toFixed(3) })) }) });
        const r = await fetch(location.pathname + location.search, { method: 'POST', body, headers: { 'X-CSRF-TOKEN': window.PV_CSRF || '' }, credentials: 'same-origin' });
        const j = await r.json().catch(() => ({}));
        if (!r.ok || !j.ok) throw new Error(j.error || 'erro');
        f.dirty = false;
        const ft = carFotos(S.car || {}).find((x) => x.id === f.imgId);
        if (ft) ft.rodas = JSON.parse(body.get('wheels'));
      }
      alertMsg('Posição das rodas salva. Na próxima vez esse carro já abre certinho.');
    } catch (err) { alertMsg('Não consegui salvar: ' + err.message); }
    finally { btn.disabled = false; syncSave(); }
  };

  /* ---------- carro sem fundo: corta as bordas transparentes ---------- */
  function trimAlpha(img) {
    const c = toCanvas(img, 1800), W = c.width, H = c.height, d = c.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, W, H).data;
    let x0 = W, y0 = H, x1 = -1, y1 = -1, transp = 0;
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
      const a = d[(y * W + x) * 4 + 3]; if (a < 250) transp++;
      if (a > 24) { if (x < x0) x0 = x; if (x > x1) x1 = x; if (y < y0) y0 = y; if (y > y1) y1 = y; }
    }
    if (transp < W * H * 0.03 || x1 < x0) return null;
    const o = document.createElement('canvas'); o.width = x1 - x0 + 1; o.height = y1 - y0 + 1;
    o.getContext('2d').drawImage(c, x0, y0, o.width, o.height, 0, 0, o.width, o.height);
    return o;
  }

  /* ---------- cor do carro: simulacao de pintura ---------- */
  const CORES = [['Branco', '#f1f2f3'], ['Prata', '#b8bcc1'], ['Cinza', '#6a6e73'], ['Preto', '#18191b'], ['Vermelho', '#b3141c'], ['Azul', '#1f4fa5'], ['Azul claro', '#5aa0d8'], ['Verde', '#2f6b3a'], ['Amarelo', '#e8b512'], ['Laranja', '#e0651c'], ['Bege', '#c9b48f'], ['Marrom', '#5b3a26']];
  const hexRgb = (h) => [1, 3, 5].map((i) => parseInt(h.substr(i, 2), 16));
  const REAL_HEX = { branco: '#f1f2f3', prata: '#b8bcc1', preto: '#18191b', cinza: '#6a6e73', vermelho: '#b3141c', azul: '#1f4fa5', verde: '#2f6b3a', amarelo: '#e8b512', laranja: '#e0651c', bege: '#c9b48f', marrom: '#5b3a26' };
  const norm = (n) => (n || '').toLowerCase().normalize('NFD').replace(/[\u0300-\u036f]/g, '');
  function colorOptions() {
    const reais = S.sets ? Object.keys(S.sets).filter((n) => n) : [];
    const list = reais.length ? reais.map((n) => ({ name: n, hex: REAL_HEX[norm(n)] || '#888', real: true })) : [{ name: 'Original', hex: null, real: true }];
    CORES.filter(([n]) => !reais.some((r) => norm(r) === norm(n))).forEach(([n, h]) => list.push({ name: n, hex: h, real: false }));
    return list;
  }
  const colorOn = (o) => (o.real ? !S.paint && (!S.sets || !o.hex || S.realColor === o.name) : !!S.paint && S.paint.hex === o.hex);
  function selectColor(o) {
    const base = () => S.sets && (S.sets[Object.keys(S.sets).find((n) => /branc/i.test(n))] || S.sets[S.realColor]);
    if (o.real) { S.paint = null; if (S.sets && o.hex) { S.realColor = o.name; S.frames = S.sets[o.name]; } }
    else { S.paint = { name: o.name, hex: o.hex }; if (S.sets) S.frames = base(); }
    renderCores();
  }
  function renderCores() {
    const box = $('pv-cores'); box.innerHTML = '';
    const reais = S.sets ? Object.keys(S.sets).filter((n) => n) : [];
    colorOptions().forEach((o) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-cor' + (colorOn(o) ? ' on' : '');
      b.title = o.hex ? o.name + (o.real ? ' (foto real)' : ' (simulação)') : 'Original'; b.setAttribute('aria-label', b.title);
      b.innerHTML = (o.hex ? `<i style="background:${o.hex}"></i>` : '<i class="orig"></i>') + `<small>${esc(o.name)}</small>${o.real && o.hex ? '<em>foto</em>' : ''}`;
      b.onclick = () => selectColor(o);
      box.append(b);
    });
    const l = document.createElement('label'); l.className = 'pv-cor'; l.title = 'Outra cor (simulação)';
    l.innerHTML = `<input type="color" value="${S.paint ? S.paint.hex : '#7a1f2b'}" aria-label="Outra cor"><small>Outra</small>`;
    l.querySelector('input').oninput = (e) => selectColor({ name: 'Personalizada', hex: e.target.value, real: false });
    box.append(l);
    const hint = $('pv-cores-t');
    if (hint) hint.textContent = reais.length ? 'Com a etiqueta "foto" é a foto real do carro nessa cor. As outras cores são simulação de pintura.' : 'Simulação de pintura do carro.';
    tvSync();
  }
  // pinta a lataria: acha a cor da porta (meio do carro), troca so o que parece com ela, mantendo luz e sombra
  function painted(f, hex) {
    f._paint = f._paint || {};
    if (f._paint[hex]) return f._paint[hex];
    const W = f.canvas.width, H = f.canvas.height, src = f.canvas.getContext('2d', { willReadFrequently: true }).getImageData(0, 0, W, H), d = src.data;
    const lum = (r, g, b) => 0.299 * r + 0.587 * g + 0.114 * b;
    const sample = [];
    for (let y = Math.round(H * 0.42); y < H * 0.68; y += 3) for (let x = Math.round(W * 0.28); x < W * 0.72; x += 3) {
      const i = (y * W + x) * 4; if (d[i + 3] > 220) sample.push([d[i], d[i + 1], d[i + 2]]);
    }
    if (!sample.length) return f.canvas;
    const med = (k) => sample.map((p) => p[k]).sort((a, b) => a - b)[sample.length >> 1];
    const dom = [med(0), med(1), med(2)], Ld = Math.max(8, lum(...dom)), tgt = hexRgb(hex), Lt = Math.max(8, lum(...tgt));
    const chroma = (r, g, b, L) => [r - L, g - L, b - L];
    const cd = chroma(...dom, Ld);
    const out = new ImageData(W, H), o = out.data;
    const wheelHit = (x, y) => f.wheels.some((w) => ((x - w.x) / (w.r * w.sq * 1.0)) ** 2 + ((y - w.y) / (w.r * 1.0)) ** 2 < 1);
    for (let y = 0; y < H; y++) for (let x = 0; x < W; x++) {
      const i = (y * W + x) * 4, r = d[i], g = d[i + 1], b = d[i + 2], a = d[i + 3];
      o[i] = r; o[i + 1] = g; o[i + 2] = b; o[i + 3] = a;
      if (a < 10 || (f.wheelsOn && wheelHit(x, y))) continue;
      const L = lum(r, g, b), c = chroma(r, g, b, L);
      const dist = Math.abs(L - Ld) / 255 * (Ld > 120 ? 0.85 : 1.2) + Math.hypot(c[0] - cd[0], c[1] - cd[1], c[2] - cd[2]) / 255 * 1.6;
      let m = 1 - Math.min(1, Math.max(0, (dist - 0.16) / 0.16));
      if (m <= 0) continue;
      const k = Ld > 90 ? Math.min(1.6, L / Ld) : 1 + (L - Ld) / 160;
      for (let j = 0; j < 3; j++) {
        const v = Math.min(255, Math.max(0, tgt[j] * k + Math.max(0, L - Ld) * 0.35));
        o[i + j] = d[i + j] * (1 - m) + v * m;
      }
    }
    const c = document.createElement('canvas'); c.width = W; c.height = H; c.getContext('2d').putImageData(out, 0, 0);
    f._paint[hex] = c; return c;
  }

  /* ---------- modo TV: tela cheia so com a apresentacao, barra que some sozinha ---------- */
  const stage = $('pv-stage'), bar = $('pv-tvbar');
  const tvOn = () => stage.classList.contains('pv-tv');
  function tvSync() {
    if (!bar || !tvOn()) return;
    $('tv-car').textContent = S.car ? S.car.marca + ' ' + S.car.modelo : '-';
    $('tv-roda').textContent = S.roda ? S.roda.nome.replace(/^RODA\s+/i, '') : '-';
    $('tv-aro').textContent = S.aro;
    const c = colorOptions().find(colorOn); $('tv-cor').textContent = c ? c.name : (S.paint ? S.paint.name : '-');
    $('tv-play').innerHTML = S.play ? '&#10074;&#10074;' : '&#9654;';
  }
  let idleT = null;
  function wake() { stage.classList.remove('idle'); clearTimeout(idleT); idleT = setTimeout(() => { if (tvOn()) stage.classList.add('idle'); }, 3000); }
  function tv(on) {
    stage.classList.toggle('pv-tv', on); document.documentElement.classList.toggle('tv-on', on); bar.hidden = !on;
    if (on) {
      const el = document.documentElement;
      if (!document.fullscreenElement && el.requestFullscreen) el.requestFullscreen().catch(() => {});
      wake(); tvSync(); if (!S.play) setPlay(true);
    } else {
      stage.classList.remove('idle');
      if (document.fullscreenElement && document.exitFullscreen) document.exitFullscreen().catch(() => {});
    }
  }
  const carsComFoto = () => CARS.filter((c) => carFotos(c).length).sort((a, b) => (a.marca + a.modelo).localeCompare(b.marca + b.modelo));
  function tvAction(a) {
    const step = (list, cur, d) => list[((cur < 0 ? 0 : cur + d) % list.length + list.length) % list.length];
    if (a === 'car+' || a === 'car-') { const l = carsComFoto(); if (l.length) loadCar(step(l, l.indexOf(S.car), a === 'car+' ? 1 : -1)); }
    if (a === 'roda+' || a === 'roda-') { if (RODAS.length) pickRoda(step(RODAS, RODAS.indexOf(S.roda), a === 'roda+' ? 1 : -1)); }
    if (a === 'aro+' || a === 'aro-') { const l = aroRange(), i = l.indexOf(S.aro), n = l[Math.max(0, Math.min(l.length - 1, i + (a === 'aro+' ? 1 : -1)))]; S.aro = n; S.rimCache.clear(); syncBtns(); }
    if (a === 'cor+' || a === 'cor-') { const l = colorOptions(); selectColor(step(l, l.findIndex(colorOn), a === 'cor+' ? 1 : -1)); }
    if (a === 'play') setPlay(!S.play);
    if (a === 'sair') tv(false);
    tvSync();
  }
  $('pv-tv').onclick = () => tv(true);
  bar.addEventListener('click', (e) => { const b = e.target.closest('button[data-tv]'); if (b) { tvAction(b.dataset.tv); wake(); } });
  stage.addEventListener('pointermove', () => { if (tvOn()) wake(); });
  document.addEventListener('fullscreenchange', () => { if (!document.fullscreenElement && tvOn()) tv(false); });
  document.addEventListener('keydown', (e) => {
    if (!tvOn() || e.target.closest('input,select,textarea')) return;
    const k = { ArrowRight: 'roda+', ArrowLeft: 'roda-', ArrowDown: 'car+', ArrowUp: 'car-', '+': 'aro+', '=': 'aro+', '-': 'aro-', c: 'cor+', C: 'cor-', ' ': 'play', Escape: 'sair' }[e.key];
    if (k) { e.preventDefault(); tvAction(k); wake(); }
  });

  renderCars(); renderAros(); adminCar(); renderCores(); renderRodas('');
  const first = RODAS.find((r) => r.catalogo) || RODAS[0];
  if (first) pickRoda(first);
  requestAnimationFrame(loop);
  const sel = CARS.find((c) => c.id === window.PV_CAR_SEL) || CARS.find((c) => carFotos(c).length);
  if (sel) loadCar(sel);
  window.__PV = S; // usado nos testes
})();
