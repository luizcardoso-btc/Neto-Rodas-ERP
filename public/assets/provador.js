/* Provador de rodas: foto do carro do cliente -> remove o fundo (U2-Net no aparelho) -> plataforma 360 com a roda escolhida. */
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const cv = $('pv-c'), ctx = cv.getContext('2d');
  const ANGULOS = window.PV_ANGULOS || ['Lateral direita', '3/4 traseira direita', 'Traseira', '3/4 traseira esquerda', 'Lateral esquerda', '3/4 frente esquerda', 'Frente', '3/4 frente direita'];
  const CARS = window.PV_CARS || [];
  const SISTEMA = [['esportiva', 'Esportiva 5 raios'], ['multi', 'Multirraios'], ['duplo', 'Raios duplos'], ['turbina', 'Turbina']];

  const S = {
    frames: [], cur: 0, pos: 0, original: false, adjust: false, spin: false, car: null, ang: 0, last: performance.now(),
    slot: 'A', A: { aro: 17, roda: { tipo: 'sis', id: 'esportiva' } }, B: { aro: 15, roda: { tipo: 'sis', id: 'multi' } },
    rimCache: new Map(), shoot: null, drag: null, t0: performance.now()
  };
  const cfg = () => S[S.slot];
  const frame = () => S.frames[S.cur];

  /* ---------- medidas ---------- */
  // pneu original do carro (ou 185/60 R15 para foto de cliente) e o pneu equivalente em cada aro, mesmo diametro total
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
  async function cutout(src) {
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

  /* ---------- rodas ---------- */
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
  function wheelSprite(c) {
    const key = c.aro + '|' + c.roda.tipo + '|' + c.roda.id;
    if (S.rimCache.has(key)) return S.rimCache.get(key);
    const R = 256, cnv = document.createElement('canvas'); cnv.width = cnv.height = R * 2;
    const g = cnv.getContext('2d'); g.translate(R, R);
    const rr = drawTire(g, R - 1, tire(c.aro).ratio);
    if (c.roda.tipo === 'sis') sysRim(g, rr, c.roda.id);
    else {
      const rim = rimImages.get(c.roda.id);
      if (!rim) { sysRim(g, rr, 'esportiva'); return cnv; } // ainda carregando
      g.fillStyle = '#101010'; g.beginPath(); g.arc(0, 0, rr, 0, Math.PI * 2); g.fill();
      g.save(); g.beginPath(); g.arc(0, 0, rr, 0, Math.PI * 2); g.clip();
      g.drawImage(rim, -rr, -rr, rr * 2, rr * 2); g.restore();
    }
    S.rimCache.set(key, cnv); return cnv;
  }

  // foto da roda (do estoque ou enviada): remove o fundo e centraliza o circulo da roda
  const rimImages = new Map();
  async function prepareRim(id, src) {
    try {
      busy('Preparando a roda...');
      // foto ja limpa (com transparencia): usa direto. Foto comum: remove o fundo
      const c = trimAlpha(src) || (await cutout(src)).canvas, side = Math.max(c.width, c.height), sq = document.createElement('canvas');
      sq.width = sq.height = side;
      sq.getContext('2d').drawImage(c, (side - c.width) / 2, (side - c.height) / 2);
      rimImages.set(id, sq); S.rimCache.clear();
    } finally { busy(null); }
  }
  function loadImg(url) {
    return new Promise((ok, fail) => { const i = new Image(); i.onload = () => ok(i); i.onerror = fail; i.src = url; });
  }
  function toCanvas(img, max) {
    const k = Math.min(1, max / Math.max(img.width || img.videoWidth, img.height || img.videoHeight));
    const w = Math.round((img.width || img.videoWidth) * k), h = Math.round((img.height || img.videoHeight) * k);
    const c = document.createElement('canvas'); c.width = w; c.height = h; c.getContext('2d').drawImage(img, 0, 0, w, h); return c;
  }

  /* ---------- palco: fundo de showroom, plataforma giratoria, carro, reflexo ---------- */
  function layout() {
    const W = cv.width, H = cv.height, cx = W / 2, py = H * 0.8, rx = Math.min(W * 0.46, H * 1.05), ry = rx * 0.16;
    return { W, H, cx, py, rx, ry };
  }
  function carRect(L, f) {
    const c = f.canvas, k = Math.min((L.rx * 1.75) / c.width, (L.H * 0.62) / c.height);
    const w = c.width * k, h = c.height * k;
    return { x: L.cx - w / 2, y: L.py + L.ry * 0.25 - h, w, h, k };
  }
  function drawStage(g, L, t, opts) {
    const bg = g.createRadialGradient(L.cx, L.H * 0.42, 10, L.cx, L.H * 0.42, Math.max(L.W, L.H) * 0.75);
    bg.addColorStop(0, '#2b2a26'); bg.addColorStop(0.55, '#141412'); bg.addColorStop(1, '#070706');
    g.fillStyle = bg; g.fillRect(0, 0, L.W, L.H);
    // feixe de luz de cima
    const beam = g.createLinearGradient(0, 0, 0, L.py);
    beam.addColorStop(0, 'rgba(255,240,200,0.10)'); beam.addColorStop(1, 'rgba(255,240,200,0)');
    g.fillStyle = beam; g.beginPath(); g.moveTo(L.cx - L.rx * 0.25, 0); g.lineTo(L.cx + L.rx * 0.25, 0); g.lineTo(L.cx + L.rx, L.py); g.lineTo(L.cx - L.rx, L.py); g.fill();
    // plataforma
    g.save(); g.translate(L.cx, L.py);
    g.fillStyle = '#0a0a09'; g.beginPath(); g.ellipse(0, L.ry * 0.35, L.rx * 1.01, L.ry * 1.05, 0, 0, Math.PI * 2); g.fill();
    const top = g.createRadialGradient(0, 0, 4, 0, 0, L.rx);
    top.addColorStop(0, '#34332e'); top.addColorStop(1, '#161614');
    g.save(); g.scale(1, L.ry / L.rx); g.fillStyle = top; g.beginPath(); g.arc(0, 0, L.rx, 0, Math.PI * 2); g.fill();
    // ranhuras que giram (efeito de plataforma girando)
    g.strokeStyle = 'rgba(255,255,255,0.05)'; g.lineWidth = L.rx * 0.006;
    for (let i = 0; i < 36; i++) { const a = t * 0.25 + i * Math.PI / 18; g.beginPath(); g.moveTo(Math.cos(a) * L.rx * 0.2, Math.sin(a) * L.rx * 0.2); g.lineTo(Math.cos(a) * L.rx * 0.96, Math.sin(a) * L.rx * 0.96); g.stroke(); }
    g.strokeStyle = '#ffd21a'; g.lineWidth = L.rx * 0.012; g.beginPath(); g.arc(0, 0, L.rx * 0.985, 0, Math.PI * 2); g.stroke();
    g.restore(); g.restore();
  }
  function drawCar(g, L, f, alpha, withMarks, sx) {
    if (!f) return;
    const R = carRect(L, f); sx = sx == null ? 1 : sx;
    g.save(); g.translate(L.cx, 0); g.scale(sx, 1); g.translate(-L.cx, 0);
    // sombra de contato
    g.save(); g.globalAlpha = 0.55 * alpha; const sh = g.createRadialGradient(L.cx, R.y + R.h, 2, L.cx, R.y + R.h, R.w * 0.55);
    sh.addColorStop(0, 'rgba(0,0,0,.9)'); sh.addColorStop(1, 'rgba(0,0,0,0)'); g.fillStyle = sh;
    g.beginPath(); g.ellipse(L.cx, R.y + R.h - R.h * 0.02, R.w * 0.55, R.h * 0.07, 0, 0, Math.PI * 2); g.fill(); g.restore();
    const comp = composeCar(f, !S.original);
    // reflexo no piso
    g.save(); g.globalAlpha = 0.16 * alpha; g.translate(0, (R.y + R.h) * 2); g.scale(1, -1);
    g.drawImage(comp, R.x, R.y, R.w, R.h); g.restore();
    g.save(); g.globalAlpha = alpha; g.drawImage(S.original ? f.orig : comp, R.x, R.y, R.w, R.h); g.restore();
    g.restore();
    if (withMarks && !S.original && f.wheelsOn) f.wheels.forEach((w) => {
      const x = R.x + w.x * R.k, y = R.y + w.y * R.k, r = w.r * R.k, lw = Math.max(2, L.W / 600);
      g.save(); g.strokeStyle = '#ffd21a'; g.lineWidth = lw; g.setLineDash([lw * 4, lw * 3]);
      g.beginPath(); g.ellipse(x, y, r * w.sq, r, 0, 0, Math.PI * 2); g.stroke(); g.setLineDash([]);
      g.fillStyle = '#ffd21a'; g.beginPath(); g.arc(x + r * w.sq, y, lw * 6, 0, Math.PI * 2); g.fill();
      g.beginPath(); g.arc(x, y - r, lw * 5, 0, Math.PI * 2); g.fill(); g.restore();
    });
  }
  // carro recortado + rodas novas
  function composeCar(f, wheels) {
    const key = wheels && f.wheelsOn ? JSON.stringify([S.slot, cfg(), f.wheels]) : 'nowheels';
    if (f._ck === key && f._comp) return f._comp;
    const c = document.createElement('canvas'); c.width = f.canvas.width; c.height = f.canvas.height;
    const g = c.getContext('2d'); g.drawImage(f.canvas, 0, 0);
    if (wheels && f.wheelsOn) {
      const sp = wheelSprite(cfg());
      f.wheels.forEach((w) => { g.save(); g.translate(w.x, w.y); g.scale(w.sq, 1); g.drawImage(sp, -w.r * 1.04, -w.r * 1.04, w.r * 2.08, w.r * 2.08); g.restore(); });
    }
    f._ck = key; f._comp = c; return c;
  }

  function resize() {
    const dpr = Math.min(window.devicePixelRatio || 1, 2), w = cv.clientWidth, h = cv.clientHeight;
    if (cv.width !== Math.round(w * dpr) || cv.height !== Math.round(h * dpr)) { cv.width = Math.round(w * dpr); cv.height = Math.round(h * dpr); }
  }
  function loop(now) {
    resize();
    const t = (now - S.t0) / 1000, L = layout(), dt = Math.min(0.05, (now - S.last) / 1000); S.last = now;
    if (S.frames.length === 1) {
      // uma foto so: a plataforma gira e o carro vira junto (mostra os dois lados), andando mais rapido quando esta de frente
      if (S.spin) S.ang += dt * (0.55 + 2.2 * (1 - Math.abs(Math.cos(S.ang))));
      else if (!S.drag) { const per = S.adjust ? 2 * Math.PI : Math.PI, tgt = Math.round(S.ang / per) * per, d = tgt - S.ang; S.ang += Math.sign(d) * Math.min(Math.abs(d), dt * (S.adjust ? 6 : 2.5)); } // para de lado
      drawStage(ctx, L, S.ang, {});
      // gira de verdade so ate certo ponto: encolhe ate 40% e funde com o lado espelhado, sem virar uma "folha"
      const cs = Math.cos(S.ang), w = 0.4 + 0.6 * Math.abs(cs), aPos = Math.max(0, Math.min(1, (cs + 0.35) / 0.7));
      if (aPos < 1) drawCar(ctx, L, S.frames[0], 1 - aPos, false, -w);
      if (aPos > 0) drawCar(ctx, L, S.frames[0], aPos, S.adjust && cs > 0.999, w);
      info();
      requestAnimationFrame(loop); return;
    }
    drawStage(ctx, L, S.spin || S.frames.length < 2 ? t : S.pos * 0.8, {});
    if (S.frames.length) {
      if (S.spin && S.frames.length > 1) S.pos = (S.pos + 0.012) % S.frames.length;
      const i = Math.floor(S.pos) % S.frames.length, j = (i + 1) % S.frames.length, k = S.frames.length > 1 ? S.pos - Math.floor(S.pos) : 0;
      S.cur = k > 0.5 ? j : i;
      const ease = k < 0.75 ? 0 : (k - 0.75) / 0.25; // segura cada foto e funde rapido na proxima
      drawCar(ctx, L, S.frames[i], 1 - ease, S.adjust && ease === 0);
      if (ease > 0) drawCar(ctx, L, S.frames[j], ease, false);
      info();
    }
    requestAnimationFrame(loop);
  }

  /* ---------- camera ao vivo ---------- */
  const video = $('pv-video');
  async function openCam(n) {
    S.shoot = { n, done: 0, list: [] };
    try {
      const stream = await navigator.mediaDevices.getUserMedia({ video: { facingMode: { ideal: 'environment' }, width: { ideal: 1920 }, height: { ideal: 1080 } }, audio: false });
      video.srcObject = stream; await video.play();
      video.hidden = false; $('pv-guide').hidden = false; $('pv-shoot').hidden = false; $('pv-empty').hidden = true; $('pv-tools').hidden = true;
      guideText();
    } catch (e) {
      S.shoot = null;
      $('pv-file').click(); // sem permissao de camera: abre a camera/galeria do aparelho
    }
  }
  function guideText() {
    const s = S.shoot; if (!s) return;
    $('pv-guide-t').textContent = s.n > 1 ? `Foto ${s.done + 1} de ${s.n}: ${ANGULOS[s.done]}. Ande em volta do carro mantendo a mesma distância.` : 'Enquadre o carro de lado, inteiro, com as duas rodas aparecendo';
    $('pv-done').hidden = !(s.n > 1 && s.done > 0);
  }
  function closeCam() {
    const st = video.srcObject; if (st) st.getTracks().forEach((t) => t.stop());
    video.srcObject = null; video.hidden = true; $('pv-guide').hidden = true; $('pv-shoot').hidden = true; S.shoot = null;
    if (!S.frames.length) $('pv-empty').hidden = false; else $('pv-tools').hidden = false;
  }
  $('pv-snap').onclick = async () => {
    const s = S.shoot; if (!s) return;
    const shot = toCanvas(video, 1600);
    s.list.push({ shot, label: s.n > 1 ? ANGULOS[s.done] : 'Lateral' }); s.done++;
    flash();
    if (s.done >= s.n) { const list = s.list; closeCam(); await addFrames(list, true); } else guideText();
  };
  $('pv-done').onclick = async () => { const list = S.shoot.list; closeCam(); await addFrames(list, true); };
  $('pv-cancel').onclick = closeCam;
  document.querySelectorAll('[data-cam]').forEach((b) => { b.onclick = () => openCam(+b.dataset.cam); });
  $('pv-file').onchange = async (e) => {
    const files = [...e.target.files]; e.target.value = ''; if (!files.length) return;
    const list = [];
    for (const [i, f] of files.entries()) { const url = URL.createObjectURL(f); try { list.push({ shot: toCanvas(await loadImg(url), 1600), label: files.length > 1 ? (ANGULOS[i] || 'Foto ' + (i + 1)) : 'Lateral' }); } finally { URL.revokeObjectURL(url); } }
    await addFrames(list, true);
  };
  function flash() { const st = $('pv-stage'); st.classList.add('pv-flash'); setTimeout(() => st.classList.remove('pv-flash'), 160); }

  async function addFrames(list, replace) {
    if (!list.length) return;
    try {
      if (replace) S.frames = [];
      for (const [i, it] of list.entries()) {
        busy(`Removendo o fundo (${i + 1} de ${list.length})...`);
        const r = await cutout(it.shot);
        const side = /Lateral|3\/4/.test(it.label);
        S.frames.push({ canvas: r.canvas, orig: r.orig, label: it.label, wheels: findWheels(r.canvas), wheelsOn: side });
      }
      S.car = null; renderCars(); renderAros(); adminCar();
      S.pos = 0; S.cur = 0; S.ang = 0; S.spin = S.frames.length === 1;
      $('pv-empty').hidden = true; $('pv-tools').hidden = false; $('pv-girar').classList.toggle('on', S.spin);
      renderFrames(); syncBtns();
    } catch (e) {
      console.error(e); alertMsg('Não consegui processar a foto. Tente de novo com boa luz e o carro inteiro no quadro.');
    } finally { busy(null); }
  }
  function busy(t) { $('pv-busy').hidden = !t; if (t) $('pv-busy-t').textContent = t; }
  function alertMsg(t) { const i = $('pv-info'); i.hidden = false; i.dataset.msg = '1'; i.textContent = t; clearTimeout(alertMsg.t); alertMsg.t = setTimeout(() => { delete i.dataset.msg; }, 5000); }

  /* ---------- miniaturas dos angulos ---------- */
  function renderFrames() {
    const box = $('pv-frames'); box.innerHTML = '';
    S.frames.forEach((f, i) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-fr' + (i === S.cur ? ' on' : '');
      const th = document.createElement('canvas'); th.width = 120; th.height = 70; const g = th.getContext('2d');
      const k = Math.min(120 / f.canvas.width, 70 / f.canvas.height); g.drawImage(f.canvas, (120 - f.canvas.width * k) / 2, (70 - f.canvas.height * k) / 2, f.canvas.width * k, f.canvas.height * k);
      b.append(th); const s = document.createElement('span'); s.textContent = f.label; b.append(s);
      const t = document.createElement('label'); t.className = 'pv-chk';
      t.innerHTML = `<input type="checkbox" ${f.wheelsOn ? 'checked' : ''}> rodas`;
      t.querySelector('input').onchange = (e) => { f.wheelsOn = e.target.checked; f._ck = null; f.dirty = true; syncSave(); };
      t.onclick = (e) => e.stopPropagation();
      b.append(t);
      b.onclick = () => { S.spin = false; $('pv-girar').classList.remove('on'); S.pos = i; S.cur = i; renderFrames(); syncBtns(); };
      box.append(b);
    });
  }

  /* ---------- arrastar: gira (varias fotos) ou ajusta as rodas ---------- */
  function hit(e) {
    const b = cv.getBoundingClientRect(), dpr = cv.width / b.width, L = layout(), f = frame(); if (!f) return null;
    const R = carRect(L, f), px = (e.clientX - b.left) * dpr, py = (e.clientY - b.top) * dpr;
    return { px, py, R, f, x: (px - R.x) / R.k, y: (py - R.y) / R.k, tol: 24 * dpr / R.k };
  }
  cv.addEventListener('pointerdown', (e) => {
    const h = hit(e); if (!h) return;
    if (S.adjust && h.f.wheelsOn && (S.frames.length > 1 || Math.cos(S.ang) > 0.999)) {
      for (const [i, w] of h.f.wheels.entries()) {
        if (Math.hypot(h.x - (w.x + w.r * w.sq), h.y - w.y) < h.tol) { S.drag = { i, m: 'w' }; break; }
        if (Math.hypot(h.x - w.x, h.y - (w.y - w.r)) < h.tol) { S.drag = { i, m: 'h' }; break; }
        if (Math.hypot((h.x - w.x) / w.sq, h.y - w.y) < w.r) { S.drag = { i, m: 'm', dx: h.x - w.x, dy: h.y - w.y }; break; }
      }
    }
    if (!S.drag && S.frames.length) { S.drag = { m: 'spin', x0: e.clientX, p0: S.frames.length > 1 ? S.pos : S.ang }; S.spin = false; $('pv-girar').classList.remove('on'); }
    if (S.drag) { cv.setPointerCapture(e.pointerId); e.preventDefault(); }
  });
  cv.addEventListener('pointermove', (e) => {
    const d = S.drag; if (!d) return;
    if (d.m === 'spin') {
      if (S.frames.length === 1) { S.ang = d.p0 + (e.clientX - d.x0) / cv.clientWidth * Math.PI * 2; return; }
      const n = S.frames.length, step = cv.clientWidth / (n * 1.2);
      S.pos = (((d.p0 - (e.clientX - d.x0) / step) % n) + n) % n; return;
    }
    const h = hit(e), w = h.f.wheels[d.i];
    if (d.m === 'm') { w.x = h.x - d.dx; w.y = h.y - d.dy; }
    if (d.m === 'h') { w.r = Math.max(5, w.y - h.y); }
    if (d.m === 'w') { w.sq = Math.max(0.35, Math.min(1.2, Math.abs(h.x - w.x) / w.r)); }
    h.f._ck = null; h.f.dirty = true; syncSave();
  });
  const end = () => {
    if (S.drag && S.drag.m === 'spin' && S.frames.length > 1) { S.pos = Math.round(S.pos) % S.frames.length; renderFrames(); }
    S.drag = null;
  };
  cv.addEventListener('pointerup', end); cv.addEventListener('pointercancel', end);

  /* ---------- painel ---------- */
  function info() {
    const t = tire(cfg().aro), el = $('pv-info');
    if (!el.dataset.msg) { el.hidden = false; el.innerHTML = `<b>${S.car ? esc(S.car.marca + ' ' + S.car.modelo) + ' · ' : ''}${S.slot} · Aro ${t.a}</b> ${t.l}/${t.p} R${t.a} · flanco ${Math.round(t.flanco)} mm${S.frames.length > 1 ? ' · ' + (frame() ? frame().label : '') : ''}`; }
  }
  const fmtMoney = (v) => v.toLocaleString('pt-BR', { style: 'currency', currency: 'BRL' });
  function rodaLabel(c) {
    if (c.roda.tipo === 'sis') return `Aro ${c.aro} · ${SISTEMA.find((s) => s[0] === c.roda.id)[1]}`;
    if (c.roda.tipo === 'up') return `Aro ${c.aro} · foto enviada`;
    const r = window.PV_RODAS.find((x) => x.id === c.roda.id); return `Aro ${c.aro} · ${r ? r.nome : 'roda'}`;
  }
  function syncBtns() {
    $('pv-a').classList.toggle('on', S.slot === 'A'); $('pv-b').classList.toggle('on', S.slot === 'B');
    $('pv-a-t').textContent = rodaLabel(S.A); $('pv-b-t').textContent = rodaLabel(S.B);
    document.querySelectorAll('#pv-aros button').forEach((b) => b.classList.toggle('on', +b.dataset.v === cfg().aro));
    document.querySelectorAll('#pv-sys button').forEach((b) => b.classList.toggle('on', cfg().roda.tipo === 'sis' && cfg().roda.id === b.dataset.v));
    document.querySelectorAll('#pv-rodas button').forEach((b) => b.classList.toggle('on', cfg().roda.tipo === 'loja' && String(cfg().roda.id) === b.dataset.v));
    const t = tire(cfg().aro);
    const orig = parseTire(basePneu()).a === t.a;
    $('pv-aro-t').textContent = `Pneu ${t.l}/${t.p} R${t.a}${orig ? ' (original' + (S.car ? ' do ' + S.car.modelo : '') + ')' : ''}: flanco de ${Math.round(t.flanco)} mm, diâmetro total ${Math.round(t.diam)} mm${orig ? '' : ` (${t.diff >= 0 ? '+' : ''}${t.diff.toFixed(1).replace('.', ',')}% do original)`}. Quanto maior o aro, mais roda e menos borracha aparecem.`;
  }
  function renderAros() {
    const aros = $('pv-aros'), list = aroRange(); aros.innerHTML = '';
    ['A', 'B'].forEach((k) => { if (!list.includes(S[k].aro)) S[k].aro = list[Math.min(list.length - 1, k === 'A' ? 2 : 1)]; });
    S.rimCache.clear();
    list.forEach((a) => {
      const t = tire(a), b = document.createElement('button'); b.type = 'button'; b.className = 'pv-opt'; b.dataset.v = a;
      b.innerHTML = `${a}<small>${t.l}/${t.p}</small>`;
      b.onclick = () => { cfg().aro = a; syncBtns(); }; aros.append(b);
    });
    syncBtns();
  }
  const sys = $('pv-sys');
  SISTEMA.forEach(([id, n]) => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-opt'; b.dataset.v = id; b.textContent = n;
    b.onclick = () => { cfg().roda = { tipo: 'sis', id }; syncBtns(); }; sys.append(b);
  });
  function renderRodas(q) {
    const box = $('pv-rodas'); box.innerHTML = ''; q = (q || '').toLowerCase();
    const list = window.PV_RODAS.filter((r) => !q || (r.nome + ' ' + r.marca + ' aro ' + (r.aro || '')).toLowerCase().includes(q)).slice(0, 60);
    if (!list.length) { box.innerHTML = '<p class="small muted">Nenhuma roda com foto no estoque. Cadastre a foto da roda em Estoque.</p>'; return; }
    list.forEach((r) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-roda'; b.dataset.v = r.id;
      b.innerHTML = `<img src="${r.img}" alt="" loading="lazy"><span><b>${esc(r.nome)}</b><small>${r.aro ? 'Aro ' + r.aro + ' · ' : ''}${r.estoque == null ? 'catálogo' : r.estoque + ' em estoque · ' + fmtMoney(r.preco)}</small></span>`;
      b.onclick = async () => {
        if (!rimImages.has(r.id)) { try { await prepareRim(r.id, toCanvas(await loadImg(r.img), 900)); } catch (e) { alertMsg('Não consegui abrir a foto dessa roda.'); return; } }
        cfg().roda = { tipo: 'loja', id: r.id }; if (r.aro && aroRange().includes(+r.aro)) cfg().aro = +r.aro; syncBtns();
      };
      box.append(b);
    });
  }
  const esc = (s) => String(s).replace(/[&<>"]/g, (c) => ({ '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;' }[c]));
  $('pv-q').oninput = (e) => renderRodas(e.target.value);
  $('pv-rimfile').onchange = async (e) => {
    const f = e.target.files[0]; e.target.value = ''; if (!f) return;
    const url = URL.createObjectURL(f);
    try { await prepareRim('up', toCanvas(await loadImg(url), 900)); cfg().roda = { tipo: 'up', id: 'up' }; syncBtns(); } finally { URL.revokeObjectURL(url); }
  };
  $('pv-a').onclick = () => { S.slot = 'A'; syncBtns(); };
  $('pv-b').onclick = () => { S.slot = 'B'; syncBtns(); };
  let altT = null;
  $('pv-alt').onclick = (e) => {
    if (altT) { clearInterval(altT); altT = null; e.currentTarget.classList.remove('on'); return; }
    e.currentTarget.classList.add('on'); altT = setInterval(() => { S.slot = S.slot === 'A' ? 'B' : 'A'; syncBtns(); }, 2000);
  };
  $('pv-antes').onclick = () => { S.original = true; $('pv-antes').classList.add('on'); $('pv-depois').classList.remove('on'); };
  $('pv-depois').onclick = () => { S.original = false; $('pv-depois').classList.add('on'); $('pv-antes').classList.remove('on'); };
  $('pv-girar').onclick = (e) => { S.spin = !S.spin; e.currentTarget.classList.toggle('on', S.spin); };
  $('pv-ajustar').onclick = (e) => {
    S.adjust = !S.adjust; S.spin = false; $('pv-girar').classList.remove('on'); e.currentTarget.classList.toggle('on', S.adjust); syncSave();
    if (S.adjust) alertMsg('Arraste o círculo para o centro da roda. Bolinha de cima: tamanho. Bolinha do lado: deixa oval (foto de 3/4).');
  };
  $('pv-save').onclick = () => {
    const c = document.createElement('canvas'); c.width = 1600; c.height = 1000;
    const g = c.getContext('2d'), L = { W: 1600, H: 1000, cx: 800, py: 800, rx: 740, ry: 118 };
    drawStage(g, L, 0, {}); drawCar(g, L, frame(), 1, false);
    const t = tire(cfg().aro), txt = `NETO RODAS E PNEUS · ${S.car ? (S.car.marca + ' ' + S.car.modelo + ' · ').toUpperCase() : ''}${rodaLabel(cfg()).toUpperCase()} · ${t.l}/${t.p} R${t.a}`;
    g.font = '700 30px Inter, Arial, sans-serif'; const w = g.measureText(txt).width;
    g.fillStyle = 'rgba(12,12,11,.8)'; g.fillRect(30, 930, w + 40, 48); g.fillStyle = '#ffd21a'; g.textBaseline = 'middle'; g.fillText(txt, 50, 954);
    $('pv-out-img').src = c.toDataURL('image/jpeg', 0.9); $('pv-out').hidden = false; $('pv-out-x').focus();
  };
  $('pv-out-x').onclick = () => { $('pv-out').hidden = true; };

  /* ---------- biblioteca de carros (PNG sem fundo, uma foto por angulo) ---------- */
  // corta as bordas transparentes. Retorna null se a imagem nao tem transparencia (ai removemos o fundo)
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
  const carFotos = (c) => c.fotos || [];
  function renderCars() {
    const box = $('pv-cars'), q = ($('pv-cq').value || '').toLowerCase().trim(); box.innerHTML = '';
    const list = CARS.filter((c) => !q || (c.marca + ' ' + c.modelo + ' ' + c.tipo).toLowerCase().includes(q))
      .sort((a, b) => (carFotos(b).length > 0) - (carFotos(a).length > 0) || (a.marca + a.modelo).localeCompare(b.marca + b.modelo));
    if (!list.length) { box.innerHTML = '<p class="small muted">Nenhum carro encontrado.' + (window.PV_ADMIN ? ' Cadastre em "Biblioteca" abaixo.' : '') + '</p>'; return; }
    list.slice(0, q ? 80 : 120).forEach((c) => {
      const f = carFotos(c)[0], b = document.createElement('button'); b.type = 'button';
      b.className = 'pv-car' + (S.car && S.car.id === c.id ? ' on' : '') + (f ? '' : ' sem');
      b.innerHTML = (f ? `<img src="${esc(f.src)}" alt="" loading="lazy">` : '<i>sem foto</i>') + `<span><b>${esc(c.modelo)}</b><small>${esc(c.marca)} · ${esc(c.pneu)}${carFotos(c).length > 1 ? ' · ' + carFotos(c).length + ' ângulos' : ''}</small></span>`;
      b.onclick = () => loadLibraryCar(c);
      box.append(b);
    });
  }
  async function loadLibraryCar(c) {
    S.car = c; S.adjust = false; $('pv-ajustar').classList.remove('on'); renderCars(); renderAros(); adminCar();
    const fotos = carFotos(c);
    if (!fotos.length) {
      S.frames = []; $('pv-tools').hidden = true; $('pv-empty').hidden = false; renderFrames();
      $('pv-empty').querySelector('h2').textContent = c.marca + ' ' + c.modelo + ' ainda sem foto';
      $('pv-empty').querySelector('p').textContent = window.PV_ADMIN ? 'Envie os PNGs sem fundo em "Biblioteca: fotos e cadastro de carros", no painel ao lado. Enquanto isso dá para fotografar o carro do cliente.' : 'Peça ao administrador para enviar as fotos desse modelo, ou fotografe o carro do cliente.';
      return;
    }
    try {
      const frames = [];
      for (const [i, ft] of fotos.entries()) {
        busy(`Carregando ${c.modelo} (${i + 1} de ${fotos.length})...`);
        const img = await loadImg(ft.src);
        let cnv = trimAlpha(img);
        if (!cnv) cnv = (await cutout(toCanvas(img, 1600))).canvas;
        const W = cnv.width, H = cnv.height, sv = ft.rodas;
        const wheels = sv && sv.list && sv.list.length ? sv.list.map((w) => ({ x: w.x * W, y: w.y * H, r: w.r * W, sq: w.sq || 1 })) : findWheels(cnv);
        const on = sv ? !!sv.on : /Lateral|3\/4/.test(ft.angulo);
        frames.push({ canvas: cnv, orig: cnv, label: ft.angulo, wheels, wheelsOn: on, imgId: ft.id, carId: c.id });
      }
      if (S.car !== c) return; // trocou de carro no meio
      S.frames = frames; S.pos = 0; S.cur = 0; S.ang = 0; S.spin = true; S.original = false;
      $('pv-empty').hidden = true; $('pv-tools').hidden = false; $('pv-girar').classList.add('on');
      $('pv-depois').classList.add('on'); $('pv-antes').classList.remove('on');
      renderFrames(); syncBtns(); syncSave();
    } catch (e) {
      console.error(e); alertMsg('Não consegui abrir as fotos desse carro.');
    } finally { busy(null); }
  }
  $('pv-cq').oninput = renderCars;

  // admin: lista das fotos do carro, com angulo e excluir
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
  // admin: salva a posicao das rodas da foto da biblioteca (vale para todo mundo)
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

  renderRodas(''); renderCars(); renderAros(); adminCar(); requestAnimationFrame(loop);
  const sel = CARS.find((c) => c.id === window.PV_CAR_SEL);
  if (sel) loadLibraryCar(sel);
  window.__PV = S; // usado nos testes
  window.PVAPI = { prepareRim, rimImages, loadImg, toCanvas };
})();
