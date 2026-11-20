#!/usr/bin/env python3
"""Remove o fundo da foto do carro (ou da roda) no servidor.

Uso: python3 bin/cutout.py entrada.jpg saida.png
Grava um PNG com transparencia ja recortado no tamanho do objeto e imprime
"x0,y0,x1,y1" (o recorte na foto original, para o botao "Original").
Mesmo modelo do navegador (U2-Netp, public/models/u2netp.onnx), roda em qualquer celular.
"""
import os
import sys
from collections import deque

import numpy as np
import onnxruntime as ort
from PIL import Image, ImageOps

MODEL = os.path.join(os.path.dirname(__file__), '..', 'public', 'models', 'u2netp.onnx')
N = 320


def main(src, dst):
    im = ImageOps.exif_transpose(Image.open(src)).convert('RGB')
    im.thumbnail((1800, 1800), Image.LANCZOS)
    W, H = im.size
    so = ort.SessionOptions()
    so.intra_op_num_threads = 2
    sess = ort.InferenceSession(MODEL, so, providers=['CPUExecutionProvider'])
    x = np.asarray(im.resize((N, N), Image.BILINEAR), dtype=np.float32) / 255
    x = (x - [0.485, 0.456, 0.406]) / [0.229, 0.224, 0.225]
    m = sess.run(None, {sess.get_inputs()[0].name: x.transpose(2, 0, 1)[None].astype(np.float32)})[0][0, 0]
    m = (m - m.min()) / (m.max() - m.min() + 1e-6)
    v = np.clip((m - 0.12) / 0.6, 0, 1)
    # fica so com a maior mancha (o carro): tira pessoas e objetos soltos
    on = v >= 0.3
    lab = np.zeros((N, N), np.int32)
    sizes = [0]
    for sy, sx in zip(*np.nonzero(on)):
        if lab[sy, sx]:
            continue
        cid = len(sizes)
        lab[sy, sx] = cid
        q = deque([(sy, sx)])
        n = 0
        while q:
            y, x0 = q.popleft()
            n += 1
            for yy, xx in ((y - 1, x0), (y + 1, x0), (y, x0 - 1), (y, x0 + 1)):
                if 0 <= yy < N and 0 <= xx < N and on[yy, xx] and not lab[yy, xx]:
                    lab[yy, xx] = cid
                    q.append((yy, xx))
        sizes.append(n)
    if len(sizes) > 1:
        keep = int(np.argmax(sizes[1:])) + 1
        body = lab == keep
        # borda suave: so o que encosta no carro
        near = np.zeros_like(body)
        for dy in range(-2, 3):
            for dx in range(-2, 3):
                near |= np.roll(np.roll(body, dy, 0), dx, 1)
        v = np.where(body, v, np.where(near & (v < 0.3), v, 0))
    a = Image.fromarray((v * 255).astype(np.uint8)).resize((W, H), Image.BILINEAR)
    out = im.copy()
    out.putalpha(a)
    bb = a.point(lambda p: 255 if p > 128 else 0).getbbox()
    if not bb:
        raise SystemExit('nada encontrado na foto')
    pad = int((bb[2] - bb[0]) * 0.02)
    bb = (max(0, bb[0] - pad), max(0, bb[1] - pad), min(W, bb[2] + pad), min(H, bb[3] + pad))
    out.crop(bb).save(dst, 'PNG', optimize=False, compress_level=3)
    print('%d,%d,%d,%d,%d,%d' % (bb + (W, H)))


if __name__ == '__main__':
    main(sys.argv[1], sys.argv[2])
