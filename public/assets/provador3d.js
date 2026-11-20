/* Provador 3D: biblioteca de carros (medidas reais) + rodas da loja encaixadas no pneu certo. */
(function () {
  'use strict';
  const $ = (id) => document.getElementById(id);
  const CARS = window.PV_CARS || [];
  const TIPOS = { hatch: 'Hatch', sedan: 'Sedã', suv: 'SUV', picape: 'Picape' };
  const CORES = [['Branco', '#f1f1ed'], ['Prata', '#b8bbbf'], ['Cinza', '#5a5e62'], ['Preto', '#101113'], ['Vermelho', '#a3121b'], ['Azul', '#1d3f8f'], ['Verde', '#2f4a3a']];
  const SISTEMA = [['esportiva', 'Esportiva 5 raios'], ['multi', 'Multirraios'], ['duplo', 'Raios duplos'], ['turbina', 'Turbina']];
  const ACAB = [['prata', 'Prata'], ['diamantado', 'Preto diamantado'], ['grafite', 'Grafite'], ['preto', 'Preto brilhante']];

  const S = {
    car: null, cor: '#f1f1ed', rebaixa: 0, slot: 'A',
    A: { aro: null, roda: { tipo: 'sis', id: 'esportiva' }, acab: 'diamantado' },
    B: { aro: null, roda: { tipo: 'sis', id: 'multi' }, acab: 'prata' }
  };
  const cfg = () => S[S.slot];

  /* ---------- medidas do pneu: mantem o diametro original ao subir o aro ---------- */
  function parseTire(t) { const m = /(\d{3})\/(\d{2})\s*R(\d{2})/i.exec(t || '') || [0, 185, 60, 15]; return { w: +m[1], p: +m[2], a: +m[3] }; }
  function tireFor(car, aro) {
    const o = parseTire(car.pneu), D = o.a * 25.4 + 2 * o.w * o.p / 100;
    if (aro === o.a) return { ...o, D, diff: 0, flanco: o.w * o.p / 100 };
    let w = o.w + 10 * (aro - o.a); w = Math.max(145, Math.min(335, Math.round((w - 5) / 10) * 10 + 5));
    let p = Math.round(((D - aro * 25.4) / 2 / w * 100) / 5) * 5; p = Math.max(25, Math.min(85, p));
    const D2 = aro * 25.4 + 2 * w * p / 100;
    return { w, p, a: aro, D: D2, diff: (D2 / D - 1) * 100, flanco: w * p / 100 };
  }
  const aroRange = (car) => { const a = parseTire(car.pneu).a, r = []; for (let i = Math.max(13, a - 1); i <= Math.min(22, a + 4); i++) r.push(i); return r; };

  /* ---------- cena ---------- */
  const canvas = $('p3-c');
  const renderer = new THREE.WebGLRenderer({ canvas, antialias: true, preserveDrawingBuffer: true });
  renderer.setPixelRatio(Math.min(window.devicePixelRatio || 1, 2));
  renderer.outputEncoding = THREE.sRGBEncoding; renderer.toneMapping = THREE.ACESFilmicToneMapping; renderer.toneMappingExposure = 1.05;
  renderer.shadowMap.enabled = true; renderer.shadowMap.type = THREE.PCFSoftShadowMap;
  const scene = new THREE.Scene();
  scene.background = new THREE.Color(0x0e0e0c); scene.fog = new THREE.Fog(0x0e0e0c, 12, 30);
  scene.environment = new THREE.PMREMGenerator(renderer).fromScene(new THREE.RoomEnvironment(), 0.04).texture;
  const camera = new THREE.PerspectiveCamera(30, 1, 0.05, 100);
  camera.position.set(6.6, 2.2, 6.8);
  const controls = new THREE.OrbitControls(camera, canvas);
  controls.target.set(0, 0.7, 0); controls.enableDamping = true; controls.minDistance = 2; controls.maxDistance = 14;
  controls.maxPolarAngle = Math.PI * 0.49; controls.autoRotate = true; controls.autoRotateSpeed = 1.5;
  const key = new THREE.DirectionalLight(0xffffff, 1.6); key.position.set(4, 8, 3); key.castShadow = true;
  key.shadow.mapSize.set(2048, 2048); Object.assign(key.shadow.camera, { left: -5, right: 5, top: 5, bottom: -5, near: 1, far: 25 });
  scene.add(key, new THREE.HemisphereLight(0xffffff, 0x222018, 0.35));
  const floor = new THREE.Mesh(new THREE.CircleGeometry(40, 96), new THREE.MeshStandardMaterial({ color: 0x0b0b0a, roughness: 0.85, envMapIntensity: 0.05 }));
  floor.rotation.x = -Math.PI / 2; floor.receiveShadow = true; scene.add(floor);
  const plat = new THREE.Mesh(new THREE.CylinderGeometry(3.4, 3.45, 0.06, 96), new THREE.MeshStandardMaterial({ color: 0x121210, roughness: 0.7, metalness: 0.1, envMapIntensity: 0.2 }));
  plat.position.y = 0.03; plat.receiveShadow = true; scene.add(plat);
  const ring = new THREE.Mesh(new THREE.TorusGeometry(3.4, 0.025, 8, 160), new THREE.MeshBasicMaterial({ color: 0xffd21a }));
  ring.rotation.x = Math.PI / 2; ring.position.y = 0.06; scene.add(ring);
  const GROUND = 0.06; // carro fica em cima da plataforma

  /* ---------- materiais ---------- */
  const paint = new THREE.MeshPhysicalMaterial({ color: S.cor, metalness: 0.35, roughness: 0.32, clearcoat: 1, clearcoatRoughness: 0.06 });
  const glass = new THREE.MeshPhysicalMaterial({ color: 0x0a0d11, metalness: 0.6, roughness: 0.04, clearcoat: 1 });
  const trim = new THREE.MeshStandardMaterial({ color: 0x0d0d0d, roughness: 0.6 });
  const chrome = new THREE.MeshStandardMaterial({ color: 0xdddddd, metalness: 1, roughness: 0.15 });
  const headMat = new THREE.MeshPhysicalMaterial({ color: 0xe9f1ff, emissive: 0x9fb8ff, emissiveIntensity: 0.25, roughness: 0.1, metalness: 0.4, clearcoat: 1 });
  const tailMat = new THREE.MeshPhysicalMaterial({ color: 0x8a0710, emissive: 0x5a0006, emissiveIntensity: 0.6, roughness: 0.15, clearcoat: 1 });
  const tireMat = new THREE.MeshStandardMaterial({ color: 0x161616, roughness: 0.92 });
  const discMat = new THREE.MeshStandardMaterial({ color: 0x8d8d88, metalness: 0.9, roughness: 0.35 });
  const FIN = { prata: [[0xc9cdd1, 0.9, 0.28]], diamantado: [[0x0f0f10, 0.6, 0.3], [0xd9dbde, 1, 0.12]], grafite: [[0x3b3d40, 0.7, 0.6]], preto: [[0x0b0b0c, 0.5, 0.18]] };
  const finMat = {};
  for (const k in FIN) finMat[k] = FIN[k].map((f) => new THREE.MeshStandardMaterial({ color: f[0], metalness: f[1], roughness: f[2] }));

  /* ---------- carroceria desenhada a partir das medidas reais e do tipo ---------- */
  const carGroup = new THREE.Group(); scene.add(carGroup);
  const body = new THREE.Group(); carGroup.add(body);
  let glbRoot = null, dims = null, wheels = [];

  function ext(shape, depth, mat, bevel) {
    const g = new THREE.ExtrudeGeometry(shape, { depth, bevelEnabled: true, bevelSize: bevel, bevelThickness: bevel * 1.2, bevelSegments: 5, curveSegments: 28 });
    g.translate(0, 0, -depth / 2);
    const m = new THREE.Mesh(g, mat); m.castShadow = true; m.receiveShadow = true; return m;
  }
  function buildBody(car) {
    body.clear();
    const L = car.L / 2000, W = car.W / 1000 - 0.16, H = car.H / 1000, E = car.E / 1000, t = car.tipo;
    const o = parseTire(car.pneu), R = (o.a * 25.4 + 2 * o.w * o.p / 100) / 2000, AR = R + 0.07;
    const front = Math.min(L - 0.65, E * 0.5 + (car.L / 1000 - E) * 0.06), fa = front, ra = fa - E;
    const base = Math.max(0.16, R * 0.62), belt = t === 'suv' ? 0.62 : t === 'picape' ? 0.6 : 0.665;
    const hoodF = t === 'suv' || t === 'picape' ? 0.6 : 0.53;
    dims = { fa, ra, half: car.W / 2000, R, base };
    const s = new THREE.Shape();
    s.moveTo(-L + 0.1, base); s.lineTo(ra - AR, base); s.absarc(ra, R, AR, Math.PI, 0, true);
    s.lineTo(fa - AR, base); s.absarc(fa, R, AR, Math.PI, 0, true);
    s.lineTo(L - 0.1, base); s.quadraticCurveTo(L, base, L, base + 0.14); s.lineTo(L, H * (hoodF - 0.1));
    s.quadraticCurveTo(L - 0.02, H * hoodF, L - 0.26, H * (hoodF + 0.02));
    const ws = t === 'picape' || t === 'suv' ? L * 0.38 : L * 0.42; // base do para-brisa
    s.quadraticCurveTo(L * 0.7, H * (belt - 0.03), ws, H * belt);
    if (t === 'sedan') { s.lineTo(-L + 0.18, H * (belt + 0.02)); s.quadraticCurveTo(-L + 0.03, H * (belt + 0.01), -L + 0.02, H * 0.5); }
    else if (t === 'picape') { s.lineTo(-L + 0.06, H * belt); s.lineTo(-L + 0.02, H * 0.42); }
    else { s.lineTo(-L + 0.2, H * (belt + 0.01)); s.quadraticCurveTo(-L + 0.03, H * belt, -L + 0.02, H * 0.5); }
    s.lineTo(-L, base + 0.16); s.quadraticCurveTo(-L, base, -L + 0.1, base);
    body.add(ext(s, W, paint, 0.07));

    // estufa (vidros)
    const gw = W - (t === 'suv' ? 0.18 : 0.26), g = new THREE.Shape(), roofY = H * 0.955;
    let roofEnd;
    if (t === 'picape') {
      const cabEnd = ra + AR + 0.25; roofEnd = cabEnd + 0.12;
      g.moveTo(ws, H * belt); g.quadraticCurveTo(L * 0.14, roofY * 0.97, L * 0.02, roofY); g.lineTo(roofEnd, roofY * 0.99);
      g.lineTo(cabEnd, H * belt); g.closePath();
      // cacamba
      const bedL = cabEnd - (-L + 0.06), bed = new THREE.Mesh(new THREE.BoxGeometry(bedL - 0.12, H * 0.2, W - 0.14), trim);
      bed.position.set((cabEnd + (-L + 0.06)) / 2, H * belt - H * 0.09, 0); body.add(bed);
    } else if (t === 'sedan') {
      roofEnd = -L * 0.38;
      g.moveTo(ws, H * belt); g.quadraticCurveTo(L * 0.14, roofY * 0.97, L * 0.0, roofY);
      g.quadraticCurveTo(-L * 0.2, roofY * 1.02, roofEnd, roofY * 0.97); g.quadraticCurveTo(-L * 0.5, roofY * 0.9, -L * 0.66, H * (belt + 0.02)); g.closePath();
    } else if (t === 'suv') {
      roofEnd = -L + 0.28;
      g.moveTo(ws, H * belt); g.quadraticCurveTo(L * 0.18, roofY * 0.98, L * 0.06, roofY); g.lineTo(roofEnd, roofY * 0.985);
      g.quadraticCurveTo(-L + 0.16, roofY * 0.95, -L + 0.14, H * (belt + 0.01)); g.closePath();
    } else {
      roofEnd = -L * 0.64;
      g.moveTo(ws, H * belt); g.quadraticCurveTo(L * 0.12, roofY * 0.97, -L * 0.02, roofY);
      g.quadraticCurveTo(-L * 0.36, roofY * 1.03, roofEnd, roofY * 0.975); g.quadraticCurveTo(-L * 0.78, roofY * 0.94, -L + 0.2, H * 0.74); g.lineTo(-L + 0.2, H * belt); g.closePath();
    }
    const gg = new THREE.ExtrudeGeometry(g, { depth: gw, bevelEnabled: true, bevelSize: 0.06, bevelThickness: 0.1, bevelSegments: 5, curveSegments: 24 });
    gg.translate(0, 0, -gw / 2); const gm = new THREE.Mesh(gg, glass); gm.castShadow = true; body.add(gm);
    const roof = new THREE.Mesh(new THREE.BoxGeometry(Math.abs(L * 0.05 - roofEnd) * 0.82, 0.04, gw + 0.1), paint);
    roof.position.set((L * 0.05 + roofEnd) / 2 - 0.02, roofY + 0.04, 0); roof.castShadow = true; body.add(roof);
    const pil = new THREE.Mesh(new THREE.BoxGeometry(0.09, H * (0.96 - belt), gw + 0.13), trim);
    pil.position.set(t === 'picape' ? (ws + roofEnd) / 2 - 0.15 : (ws + roofEnd) * 0.45, H * (belt + 0.96) / 2, 0); body.add(pil);
    if (t === 'suv') for (const z of [-1, 1]) { const rail = new THREE.Mesh(new THREE.BoxGeometry(Math.abs(L * 0.06 - roofEnd) * 0.8, 0.035, 0.035), chrome); rail.position.set((L * 0.06 + roofEnd) / 2, roofY + 0.08, z * gw * 0.42); body.add(rail); }
    for (const z of [-1, 1]) {
      const hl = new THREE.Mesh(new THREE.BoxGeometry(0.12, 0.09, 0.36), headMat); hl.position.set(L - 0.12, H * (hoodF - 0.03), z * (W / 2 - 0.14)); hl.rotation.z = -0.5; body.add(hl);
      const tl = new THREE.Mesh(new THREE.BoxGeometry(0.08, 0.14, 0.28), tailMat); tl.position.set(-L, H * (t === 'picape' ? 0.5 : 0.58), z * (W / 2 - 0.1)); body.add(tl);
      const mir = new THREE.Mesh(new THREE.BoxGeometry(0.14, 0.09, 0.16), paint); mir.position.set(ws - 0.12, H * (belt + 0.05), z * (W / 2 + 0.11)); body.add(mir);
      if (t === 'suv' || t === 'picape') { // moldura preta das caixas de roda
        for (const ax of [fa, ra]) { const arc = new THREE.Mesh(new THREE.TorusGeometry(AR + 0.02, 0.045, 8, 32, Math.PI), trim); arc.position.set(ax, R, z * (W / 2 + 0.06)); body.add(arc); }
      }
    }
    const grille = new THREE.Mesh(new THREE.BoxGeometry(0.06, t === 'picape' || t === 'suv' ? 0.3 : 0.16, W * 0.62), trim); grille.position.set(L + 0.02, H * (hoodF - 0.17), 0); body.add(grille);
    const pan = new THREE.Mesh(new THREE.BoxGeometry(car.L / 1000 - 0.3, 0.08, W - 0.1), trim); pan.position.y = base + 0.06; body.add(pan);
  }

  /* ---------- modelo 3D real (.glb) enviado pelo admin ---------- */
  const loader = new THREE.GLTFLoader();
  const glbCache = new Map();
  function loadGlb(car) {
    return new Promise((ok, fail) => {
      if (glbCache.has(car.glb)) return ok(glbCache.get(car.glb).clone(true));
      loader.load(car.glb, (g) => { glbCache.set(car.glb, g.scene); ok(g.scene.clone(true)); }, undefined, fail);
    });
  }
  function fitGlb(root, car) {
    let box = new THREE.Box3().setFromObject(root), size = box.getSize(new THREE.Vector3());
    if (size.z > size.x) { root.rotation.y = Math.PI / 2; root.updateMatrixWorld(true); box = new THREE.Box3().setFromObject(root); size = box.getSize(new THREE.Vector3()); }
    const k = (car.L / 1000) / size.x; root.scale.multiplyScalar(k); root.updateMatrixWorld(true);
    box = new THREE.Box3().setFromObject(root); const c = box.getCenter(new THREE.Vector3());
    root.position.x -= c.x; root.position.z -= c.z; root.position.y -= box.min.y; root.updateMatrixWorld(true);
    // rodas do modelo: acha pelos nomes e esconde (as nossas entram no lugar)
    const found = { '1,1': null, '1,-1': null, '-1,1': null, '-1,-1': null };
    root.traverse((o) => {
      if (!o.isMesh) return;
      const nm = (o.name + ' ' + (o.parent ? o.parent.name : '') + ' ' + (o.material && o.material.name || '')).toLowerCase();
      if (/(wheel|rim|tire|tyre|roda|pneu|felge|reifen|brake|caliper|disc)/.test(nm)) {
        const b = new THREE.Box3().setFromObject(o), cc = b.getCenter(new THREE.Vector3());
        const q = (cc.x >= 0 ? 1 : -1) + ',' + (cc.z >= 0 ? 1 : -1);
        found[q] = found[q] ? found[q].union(b) : b.clone(); o.visible = false;
      }
      if (o.material && /(paint|body|carroceria|carpaint|car_paint|exterior)/i.test(o.material.name || '')) { o.material = o.material.clone(); o.material.color && o.material.color.set(S.cor); o.userData.paint = true; }
      o.castShadow = true;
    });
    const pos = Object.values(found).filter(Boolean).map((b) => { const cc = b.getCenter(new THREE.Vector3()), sz = b.getSize(new THREE.Vector3()); return { x: cc.x, y: cc.y, z: cc.z, r: Math.max(sz.y, sz.x) / 2 }; });
    return pos.length === 4 ? pos : null;
  }

  /* ---------- roda ---------- */
  const rimTex = new Map();
  function rimTexture(id) {
    if (rimTex.has(id)) return rimTex.get(id);
    const img = window.PVAPI && window.PVAPI.rimImages.get(id); if (!img) return null;
    const t = new THREE.CanvasTexture(img); t.encoding = THREE.sRGBEncoding; t.anisotropy = 8; rimTex.set(id, t); return t;
  }
  function buildWheel(c, tire) {
    const grp = new THREE.Group(), R = tire.D / 2000, rimR = tire.a * 25.4 / 2000, w = tire.w / 1000, hw = w / 2;
    const fin = finMat[c.acab] || finMat.prata, base = fin[0], face = fin[1] || null;
    const r0 = rimR + 0.01, cc = Math.min(0.035, (R - r0) * 0.45);
    const pts = [[r0, -hw + 0.012], [R - cc, -hw], [R - cc * 0.25, -hw + cc * 0.6], [R, -hw + cc * 1.4], [R, hw - cc * 1.4], [R - cc * 0.25, hw - cc * 0.6], [R - cc, hw], [r0, hw - 0.012]].map((p) => new THREE.Vector2(p[0], p[1]));
    const tm = new THREE.Mesh(new THREE.LatheGeometry(pts, 72), tireMat); tm.rotation.x = Math.PI / 2; tm.castShadow = true; grp.add(tm);
    const barrel = new THREE.Mesh(new THREE.CylinderGeometry(rimR, rimR, w * 0.9, 64, 1, true), new THREE.MeshStandardMaterial({ color: 0x1a1a1a, metalness: 0.6, roughness: 0.5, side: THREE.DoubleSide }));
    barrel.rotation.x = Math.PI / 2; grp.add(barrel);
    const disc = new THREE.Mesh(new THREE.CylinderGeometry(rimR * 0.78, rimR * 0.78, 0.022, 48), discMat); disc.rotation.x = Math.PI / 2; disc.position.z = -0.03; grp.add(disc);
    const back = new THREE.Mesh(new THREE.CircleGeometry(rimR, 48), new THREE.MeshStandardMaterial({ color: 0x0c0c0c, roughness: 0.8 })); back.position.z = -0.05; grp.add(back);
    const zf = hw - 0.03;
    const tex = c.roda.tipo !== 'sis' ? rimTexture(c.roda.id) : null;
    if (tex) { // foto da roda da loja aplicada na face
      const fm = new THREE.Mesh(new THREE.CircleGeometry(rimR, 64), new THREE.MeshStandardMaterial({ map: tex, transparent: true, alphaTest: 0.35, metalness: 0.45, roughness: 0.35 }));
      fm.position.z = zf + 0.005; grp.add(fm);
      const lip = new THREE.Mesh(new THREE.TorusGeometry(rimR + 0.003, 0.01, 10, 72), chrome); lip.position.z = zf; grp.add(lip);
    } else {
      const lip = new THREE.Mesh(new THREE.TorusGeometry(rimR + 0.004, 0.012, 12, 72), face || base); lip.position.z = hw - 0.012; grp.add(lip);
      const inner = 0.062, outer = rimR - 0.005, sc = rimR / 0.19;
      const spoke = (a, wd, tw) => {
        const len = outer - inner, g = new THREE.BoxGeometry(len, wd, 0.05); g.translate(inner + len / 2, 0, 0);
        const m = new THREE.Mesh(g, base); m.rotation.z = a; m.rotation.x = tw || 0; m.position.z = zf - 0.02; m.castShadow = true; grp.add(m);
        if (face) { const fg = new THREE.BoxGeometry(len * 0.92, wd * 0.62, 0.006); fg.translate(inner + len * 0.52, 0, 0); const f = new THREE.Mesh(fg, face); f.rotation.z = a; f.rotation.x = tw || 0; f.position.z = zf + 0.007; grp.add(f); }
      };
      const d = c.roda.id;
      if (d === 'esportiva') for (let i = 0; i < 5; i++) spoke(i * Math.PI * 0.4, 0.07 * sc);
      if (d === 'multi') for (let i = 0; i < 10; i++) spoke(i * Math.PI * 0.2, 0.028 * sc);
      if (d === 'duplo') for (let i = 0; i < 5; i++) { spoke(i * Math.PI * 0.4 - 0.13, 0.03 * sc); spoke(i * Math.PI * 0.4 + 0.13, 0.03 * sc); }
      if (d === 'turbina') for (let i = 0; i < 12; i++) spoke(i * Math.PI / 6 + 0.12, 0.045 * sc, 0.55);
      const hub = new THREE.Mesh(new THREE.CylinderGeometry(0.075, 0.08, 0.06, 32), base); hub.rotation.x = Math.PI / 2; hub.position.z = zf - 0.01; grp.add(hub);
      const cap = new THREE.Mesh(new THREE.CylinderGeometry(0.04, 0.04, 0.012, 32), chrome); cap.rotation.x = Math.PI / 2; cap.position.z = zf + 0.022; grp.add(cap);
    }
    grp.userData = { R, w };
    return grp;
  }
  function placeWheels() {
    wheels.forEach((w) => carGroup.remove(w)); wheels = [];
    if (!S.car) return;
    const t = tireFor(S.car, cfg().aro), proto = buildWheel(cfg(), t), R = proto.userData.R;
    const pos = S.glbWheels || [[dims.fa, 1], [dims.fa, -1], [dims.ra, 1], [dims.ra, -1]].map(([x, s]) => ({ x, z: s * (dims.half - proto.userData.w / 2 - 0.02), y: R }));
    pos.forEach((p, i) => {
      const w = i ? proto.clone() : proto; w.position.set(p.x, GROUND + R, p.z);
      if (p.z < 0) w.rotation.y = Math.PI; carGroup.add(w); wheels.push(w);
    });
    // carroceria acompanha o diametro do pneu e o rebaixamento
    const h0 = S.glbWheels ? S.glbWheels[0].y : dims.R;
    (glbRoot || body).position.y = GROUND + (R - h0) - S.rebaixa / 1000 + (glbRoot ? glbRoot.userData.y0 : 0);
    spec(t);
  }

  async function setCar(car) {
    S.car = car; S.glbWheels = null;
    if (glbRoot) { carGroup.remove(glbRoot); glbRoot = null; }
    const orig = parseTire(car.pneu).a;
    ['A', 'B'].forEach((k, i) => { if (!S[k].aro || !aroRange(car).includes(S[k].aro)) S[k].aro = Math.min(22, orig + (i ? 0 : 2)); });
    buildBody(car); body.visible = true;
    if (car.glb) {
      busy('Carregando o modelo 3D do ' + car.modelo + '...');
      try {
        const root = await loadGlb(car); const pos = fitGlb(root, car);
        root.userData.y0 = root.position.y; glbRoot = root; carGroup.add(root); body.visible = false;
        if (pos) S.glbWheels = pos;
        else { S.glbWheels = null; } // sem rodas nomeadas: usa as posicoes das medidas
      } catch (e) { console.error(e); } finally { busy(null); }
    }
    const sc = (car.L / 1000) / 3.94; ring.scale.setScalar(1); plat.scale.set(Math.max(1, sc), 1, Math.max(1, sc));
    ring.scale.set(Math.max(1, sc), Math.max(1, sc), 1);
    placeWheels(); syncUI();
    $('p3-car-t').textContent = `${TIPOS[car.tipo]} · ${car.L} x ${car.W} x ${car.H} mm · entre-eixos ${car.E} mm · pneu original ${car.pneu}${car.glb ? ' · modelo 3D real' : ''}. Medidas aproximadas.`;
    document.querySelectorAll('#p3-edit,#p3-glb,#p3-glb-rm').forEach((f) => { f.querySelector('input[name=id]').value = car.id; });
    const ed = $('p3-edit'); if (ed) { ed.length_mm.value = car.L; ed.width_mm.value = car.W; ed.height_mm.value = car.H; ed.wheelbase_mm.value = car.E; ed.tire.value = car.pneu; $('p3-glb-rm').hidden = !car.glb; }
  }

  /* ---------- painel ---------- */
  function spec(t) {
    const d = t.diff, ok = Math.abs(d) <= 3;
    $('p3-spec').innerHTML = `<b>${S.slot} · Aro ${t.a}</b> ${t.w}/${t.p} R${t.a} · flanco ${Math.round(t.flanco)} mm · diâmetro ${Math.round(t.D)} mm · <span style="color:${ok ? '#4ade80' : '#fbbf24'}">velocímetro ${d > 0 ? '+' : ''}${d.toFixed(1).replace('.', ',')}%</span>`;
  }
  function rodaLabel(c) {
    const a = 'Aro ' + c.aro;
    if (c.roda.tipo === 'sis') return a + ' · ' + SISTEMA.find((s) => s[0] === c.roda.id)[1];
    const r = (window.PV_RODAS || []).find((x) => x.id === c.roda.id); return a + ' · ' + (r ? r.nome : 'roda');
  }
  function syncUI() {
    if (!S.car) return;
    const box = $('p3-aros'); box.innerHTML = '';
    aroRange(S.car).forEach((a) => {
      const t = tireFor(S.car, a), b = document.createElement('button'); b.type = 'button'; b.className = 'pv-opt' + (a === cfg().aro ? ' on' : '');
      b.innerHTML = `${a}<small>${t.w}/${t.p}</small>`; b.onclick = () => { cfg().aro = a; placeWheels(); syncUI(); }; box.append(b);
    });
    const t = tireFor(S.car, cfg().aro);
    $('p3-aro-t').textContent = `No ${S.car.modelo}, o aro ${t.a} pede pneu ${t.w}/${t.p} R${t.a} para manter a altura original (${S.car.pneu}). Confira furação e offset da roda antes de montar.`;
    document.querySelectorAll('#p3-sys button').forEach((b) => b.classList.toggle('on', cfg().roda.tipo === 'sis' && cfg().roda.id === b.dataset.v));
    document.querySelectorAll('#p3-acab button').forEach((b) => b.classList.toggle('on', cfg().acab === b.dataset.v));
    document.querySelectorAll('#p3-rodas button').forEach((b) => b.classList.toggle('on', cfg().roda.tipo === 'loja' && cfg().roda.id === +b.dataset.v));
    document.querySelectorAll('#p3-cores button').forEach((b) => b.classList.toggle('on', b.dataset.v === S.cor));
    $('p3-a').classList.toggle('on', S.slot === 'A'); $('p3-b').classList.toggle('on', S.slot === 'B');
    $('p3-a-t').textContent = rodaLabel(S.A); $('p3-b-t').textContent = rodaLabel(S.B);
  }
  function busy(t) { $('p3-busy').hidden = !t; if (t) $('p3-busy-t').textContent = t; }

  const sel = $('p3-car');
  function fillCars(q) {
    q = (q || '').toLowerCase(); sel.innerHTML = '';
    Object.keys(TIPOS).forEach((tp) => {
      const list = CARS.filter((c) => c.tipo === tp && (!q || (c.marca + ' ' + c.modelo + ' ' + TIPOS[tp]).toLowerCase().includes(q)));
      if (!list.length) return;
      const og = document.createElement('optgroup'); og.label = TIPOS[tp];
      list.forEach((c) => { const o = document.createElement('option'); o.value = c.id; o.textContent = `${c.marca} ${c.modelo}${c.glb ? ' (3D real)' : ''}`; og.append(o); });
      sel.append(og);
    });
    if (S.car && [...sel.options].some((o) => +o.value === S.car.id)) sel.value = S.car.id;
  }
  $('p3-q').oninput = (e) => { fillCars(e.target.value); if (sel.options.length && (!S.car || +sel.value !== S.car.id)) setCar(CARS.find((c) => c.id === +sel.value)); };
  sel.onchange = () => setCar(CARS.find((c) => c.id === +sel.value));
  CORES.forEach(([n, hex]) => {
    const b = document.createElement('button'); b.type = 'button'; b.className = 'p3-sw'; b.style.background = hex; b.dataset.v = hex; b.title = n; b.setAttribute('aria-label', 'Cor ' + n);
    b.onclick = () => { S.cor = hex; paint.color.set(hex); if (glbRoot) glbRoot.traverse((o) => { if (o.userData.paint && o.material.color) o.material.color.set(hex); }); syncUI(); };
    $('p3-cores').append(b);
  });
  SISTEMA.forEach(([id, n]) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-opt'; b.dataset.v = id; b.textContent = n; b.onclick = () => { cfg().roda = { tipo: 'sis', id }; placeWheels(); syncUI(); }; $('p3-sys').append(b); });
  ACAB.forEach(([id, n]) => { const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-opt'; b.dataset.v = id; b.textContent = n; b.onclick = () => { cfg().acab = id; placeWheels(); syncUI(); }; $('p3-acab').append(b); });
  function renderRodas(q) {
    const box = $('p3-rodas'); box.innerHTML = ''; q = (q || '').toLowerCase();
    const list = (window.PV_RODAS || []).filter((r) => !q || (r.nome + ' ' + r.marca + ' aro ' + (r.aro || '')).toLowerCase().includes(q)).slice(0, 60);
    if (!list.length) { box.innerHTML = '<p class="small muted">Nenhuma roda com foto no estoque. Cadastre a foto em Estoque para ela aparecer aqui.</p>'; return; }
    list.forEach((r) => {
      const b = document.createElement('button'); b.type = 'button'; b.className = 'pv-roda'; b.dataset.v = r.id;
      const img = document.createElement('img'); img.src = r.img; img.alt = ''; img.loading = 'lazy';
      const sp = document.createElement('span'); sp.innerHTML = '<b></b><small></small>';
      sp.querySelector('b').textContent = r.nome; sp.querySelector('small').textContent = (r.aro ? 'Aro ' + r.aro + ' · ' : '') + r.estoque + ' em estoque';
      b.append(img, sp);
      b.onclick = async () => {
        if (!window.PVAPI.rimImages.has(r.id)) {
          busy('Recortando a roda da foto...');
          try { await window.PVAPI.prepareRim(r.id, window.PVAPI.toCanvas(await window.PVAPI.loadImg(r.img), 900)); } catch (e) { busy(null); return; }
          busy(null);
        }
        cfg().roda = { tipo: 'loja', id: r.id }; if (r.aro && aroRange(S.car).includes(r.aro)) cfg().aro = r.aro;
        placeWheels(); syncUI();
      };
      box.append(b);
    });
  }
  $('p3-rq').oninput = (e) => renderRodas(e.target.value);
  $('p3-a').onclick = () => { S.slot = 'A'; placeWheels(); syncUI(); };
  $('p3-b').onclick = () => { S.slot = 'B'; placeWheels(); syncUI(); };
  let alt = null;
  $('p3-alt').onclick = (e) => {
    if (alt) { clearInterval(alt); alt = null; e.currentTarget.classList.remove('on'); return; }
    e.currentTarget.classList.add('on'); alt = setInterval(() => { S.slot = S.slot === 'A' ? 'B' : 'A'; placeWheels(); syncUI(); }, 2200);
  };
  $('p3-rb').oninput = (e) => { S.rebaixa = +e.target.value; $('p3-rb-t').textContent = S.rebaixa ? S.rebaixa + ' mm mais baixo' : 'original'; placeWheels(); };

  /* ---------- cameras ---------- */
  let anim = null;
  document.querySelectorAll('#p3-views .chip').forEach((b) => {
    b.onclick = () => {
      document.querySelectorAll('#p3-views .chip').forEach((x) => x.classList.toggle('on', x === b));
      const v = b.dataset.v, L = S.car ? S.car.L / 1000 : 4; controls.autoRotate = v === 'auto'; if (v === 'auto') return;
      const k = Math.max(1, L / 4);
      const V = { tres: [[5.9 * k, 1.8 * k, 5.6 * k], [0, 0.7, 0]], lado: [[0, 1.1, 8.6 * k], [0, 0.7, 0]], tras: [[-6.4 * k, 2 * k, 4.2 * k], [0, 0.7, 0]] };
      const [p, t] = v === 'roda' ? [[dims.fa + 1.2, 0.6, dims.half + 1.8], [dims.fa, 0.4, dims.half - 0.1]] : V[v];
      anim = { p0: camera.position.clone(), t0: controls.target.clone(), p1: new THREE.Vector3(...p), t1: new THREE.Vector3(...t), k: 0 };
    };
  });

  /* ---------- loop ---------- */
  let visible = true;
  function tick() {
    if (visible) {
      const w = canvas.clientWidth, h = canvas.clientHeight, pr = renderer.getPixelRatio();
      if (w && (canvas.width !== Math.floor(w * pr) || canvas.height !== Math.floor(h * pr))) { renderer.setSize(w, h, false); camera.aspect = w / h; camera.fov = w / h < 1 ? 44 : 30; camera.updateProjectionMatrix(); }
      if (anim) { anim.k = Math.min(1, anim.k + 0.045); const e = 1 - Math.pow(1 - anim.k, 3); camera.position.lerpVectors(anim.p0, anim.p1, e); controls.target.lerpVectors(anim.t0, anim.t1, e); if (anim.k >= 1) anim = null; }
      controls.update(); renderer.render(scene, camera);
    }
    requestAnimationFrame(tick);
  }

  /* ---------- abas: 3D x foto ---------- */
  function mode(m) {
    visible = m === '3d';
    $('p3').hidden = m !== '3d'; $('pv').hidden = m === '3d';
    $('pv-tab-3d').classList.toggle('on', m === '3d'); $('pv-tab-foto').classList.toggle('on', m !== '3d');
    $('pv-tab-3d').setAttribute('aria-selected', m === '3d'); $('pv-tab-foto').setAttribute('aria-selected', m !== '3d');
  }
  $('pv-tab-3d').onclick = () => mode('3d');
  $('pv-tab-foto').onclick = () => mode('foto');

  fillCars('');
  const first = CARS.find((c) => c.id === window.PV_CAR_SEL) || CARS.find((c) => c.modelo === 'HB20') || CARS[0];
  renderRodas('');
  if (first) { sel.value = first.id; setCar(first); }
  tick();
  window.__P3 = S;
})();
