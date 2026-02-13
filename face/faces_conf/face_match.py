import argparse
import json
import os
import sys

import cv2
import numpy as np

BASE_DIR = os.path.dirname(os.path.abspath(__file__))
CASCADE_PATH = os.path.join(BASE_DIR, 'data', 'haarcascade_frontalface_default.xml')
EYE_CASCADE_PATHS = [
    getattr(cv2.data, 'haarcascades', '') + 'haarcascade_eye.xml',
    getattr(cv2.data, 'haarcascades', '') + 'haarcascade_eye_tree_eyeglasses.xml'
]


def _error(message, code=1):
    payload = {
        'success': False,
        'error': message
    }
    print(json.dumps(payload))
    sys.exit(code)


def _detect_largest_face(gray, cascade):
    faces = cascade.detectMultiScale(
        gray,
        scaleFactor=1.1,
        minNeighbors=5,
        minSize=(40, 40)
    )
    if faces is None or len(faces) == 0:
        return None
    faces = sorted(faces, key=lambda b: b[2] * b[3], reverse=True)
    x, y, w, h = faces[0]

    pad = int(0.15 * max(w, h))
    x0 = max(0, x - pad)
    y0 = max(0, y - pad)
    x1 = min(gray.shape[1], x + w + pad)
    y1 = min(gray.shape[0], y + h + pad)

    return (x0, y0, x1 - x0, y1 - y0)


def _preprocess_face(img, box, size=160):
    x, y, w, h = box
    face = img[y:y + h, x:x + w]
    gray = cv2.cvtColor(face, cv2.COLOR_BGR2GRAY)
    gray = cv2.equalizeHist(gray)
    clahe = cv2.createCLAHE(clipLimit=2.0, tileGridSize=(8, 8))
    gray = clahe.apply(gray)
    gray = cv2.GaussianBlur(gray, (3, 3), 0)
    resized = cv2.resize(gray, (size, size))
    return resized


def _extract_core_face(gray_face, size=160):
    """Crop central facial area to reduce hair/background influence."""
    h, w = gray_face.shape[:2]
    x0 = int(w * 0.16)
    x1 = int(w * 0.84)
    y0 = int(h * 0.18)
    y1 = int(h * 0.92)
    core = gray_face[y0:y1, x0:x1]
    if core.size == 0:
        core = gray_face
    return cv2.resize(core, (size, size))


def _adjust_gamma(image, gamma):
    if gamma <= 0:
        return image
    inv = 1.0 / gamma
    table = np.array([((i / 255.0) ** inv) * 255 for i in np.arange(256)]).astype("uint8")
    return cv2.LUT(image, table)


def _load_eye_cascade():
    for path in EYE_CASCADE_PATHS:
        if not path:
            continue
        if os.path.exists(path):
            cascade = cv2.CascadeClassifier(path)
            if not cascade.empty():
                return cascade
    return None


def _detect_eyes(gray_face, eye_cascade):
    if eye_cascade is None:
        return []

    eyes = eye_cascade.detectMultiScale(
        gray_face,
        scaleFactor=1.1,
        minNeighbors=5,
        minSize=(18, 18)
    )

    if eyes is None or len(eyes) == 0:
        return []

    h = gray_face.shape[0]
    filtered = [e for e in eyes if e[1] < h * 0.65]
    if len(filtered) >= 2:
        eyes = filtered

    eyes = sorted(eyes, key=lambda b: b[2] * b[3], reverse=True)
    eyes = eyes[:4] if len(eyes) > 4 else eyes

    eyes = sorted(eyes, key=lambda b: b[0])
    if len(eyes) >= 2:
        return [eyes[0], eyes[-1]]
    return eyes


def _align_face(img, face_box, face_cascade, eye_cascade):
    if eye_cascade is None:
        return img, face_box, False

    x, y, w, h = face_box
    face_roi = img[y:y + h, x:x + w]
    if face_roi.size == 0:
        return img, face_box, False

    gray_face = cv2.cvtColor(face_roi, cv2.COLOR_BGR2GRAY)
    eyes = _detect_eyes(gray_face, eye_cascade)
    if len(eyes) < 2:
        return img, face_box, False

    (x1, y1, w1, h1), (x2, y2, w2, h2) = eyes
    left = (x1 + w1 / 2.0 + x, y1 + h1 / 2.0 + y)
    right = (x2 + w2 / 2.0 + x, y2 + h2 / 2.0 + y)

    if left[0] > right[0]:
        left, right = right, left

    dy = right[1] - left[1]
    dx = right[0] - left[0]
    if dx == 0:
        return img, face_box, False

    angle = np.degrees(np.arctan2(dy, dx))
    center = ((left[0] + right[0]) / 2.0, (left[1] + right[1]) / 2.0)

    rot_mat = cv2.getRotationMatrix2D(center, angle, 1.0)
    rotated = cv2.warpAffine(
        img,
        rot_mat,
        (img.shape[1], img.shape[0]),
        flags=cv2.INTER_LINEAR,
        borderMode=cv2.BORDER_REPLICATE
    )

    gray_rot = cv2.cvtColor(rotated, cv2.COLOR_BGR2GRAY)
    new_box = _detect_largest_face(gray_rot, face_cascade)
    if new_box is None:
        new_box = face_box

    return rotated, new_box, True


def _lbph_similarity(face_ref, face_cand):
    recognizer = cv2.face.LBPHFaceRecognizer_create(
        radius=2,
        neighbors=16,
        grid_x=8,
        grid_y=8
    )
    augmented = [
        face_ref,
        cv2.flip(face_ref, 1),
        cv2.convertScaleAbs(face_ref, alpha=1.1, beta=10),
        cv2.convertScaleAbs(face_ref, alpha=0.9, beta=-10)
    ]
    labels = np.array([0] * len(augmented))
    recognizer.train(augmented, labels)
    _, confidence = recognizer.predict(face_cand)
    confidence = float(confidence)

    _, base_conf = recognizer.predict(face_ref)
    base_conf = float(base_conf)

    min_conf = max(20.0, base_conf - 5.0)
    max_conf = max(min_conf + 80.0, base_conf + 110.0)
    if confidence <= min_conf:
        similarity = 100.0
    elif confidence >= max_conf:
        similarity = 0.0
    else:
        similarity = (max_conf - confidence) / (max_conf - min_conf) * 100.0

    return similarity, confidence, base_conf


def _hist_similarity(face_ref, face_cand):
    hist_ref = cv2.calcHist([face_ref], [0], None, [64], [0, 256])
    hist_cand = cv2.calcHist([face_cand], [0], None, [64], [0, 256])
    cv2.normalize(hist_ref, hist_ref)
    cv2.normalize(hist_cand, hist_cand)

    corr = cv2.compareHist(hist_ref, hist_cand, cv2.HISTCMP_CORREL)
    similarity = max(0.0, min(100.0, (corr + 1.0) * 50.0))
    return similarity, float(corr)


def _edge_similarity(face_ref, face_cand):
    edges_ref = cv2.Canny(face_ref, 50, 140)
    edges_cand = cv2.Canny(face_cand, 50, 140)
    corr = cv2.matchTemplate(edges_ref, edges_cand, cv2.TM_CCOEFF_NORMED)[0][0]
    similarity = max(0.0, min(100.0, corr * 100.0))
    return similarity, float(corr)


def _gradient_similarity(face_ref, face_cand):
    gx_ref = cv2.Sobel(face_ref, cv2.CV_32F, 1, 0, ksize=3)
    gy_ref = cv2.Sobel(face_ref, cv2.CV_32F, 0, 1, ksize=3)
    gx_cand = cv2.Sobel(face_cand, cv2.CV_32F, 1, 0, ksize=3)
    gy_cand = cv2.Sobel(face_cand, cv2.CV_32F, 0, 1, ksize=3)

    mag_ref = cv2.magnitude(gx_ref, gy_ref).reshape(-1)
    mag_cand = cv2.magnitude(gx_cand, gy_cand).reshape(-1)
    mag_ref /= (np.linalg.norm(mag_ref) + 1e-6)
    mag_cand /= (np.linalg.norm(mag_cand) + 1e-6)

    sim = float(np.dot(mag_ref, mag_cand))
    similarity = max(0.0, min(100.0, sim * 100.0))
    return similarity, sim


def _corr_similarity(face_ref, face_cand):
    ref = face_ref.astype(np.float32)
    cand = face_cand.astype(np.float32)
    ref = (ref - ref.mean()) / (ref.std() + 1e-6)
    cand = (cand - cand.mean()) / (cand.std() + 1e-6)
    corr = float(np.mean(ref * cand))
    similarity = max(0.0, min(100.0, (corr + 1.0) * 50.0))
    return similarity, corr


def _quality_score(lbph, corr_sim, edge_sim, hist_sim, hog_sim, grad_sim):
    """Aggregate confidence score; mixes texture + structure."""
    return (
        (lbph * 0.32) +
        (corr_sim * 0.23) +
        (hog_sim * 0.2) +
        (grad_sim * 0.15) +
        (hist_sim * 0.07) +
        (edge_sim * 0.03)
    )


def _structural_quality(corr_sim, hog_sim, hist_sim, edge_sim, grad_sim):
    """Structure-heavy quality score to survive lighting shifts."""
    return (
        (corr_sim * 0.35) +
        (hog_sim * 0.28) +
        (grad_sim * 0.25) +
        (hist_sim * 0.07) +
        (edge_sim * 0.05)
    )


def _hog_similarity(face_ref, face_cand):
    win_size = (160, 160)
    block_size = (32, 32)
    block_stride = (16, 16)
    cell_size = (16, 16)
    nbins = 9
    hog = cv2.HOGDescriptor(win_size, block_size, block_stride, cell_size, nbins)
    feat_ref = hog.compute(face_ref)
    feat_cand = hog.compute(face_cand)
    if feat_ref is None or feat_cand is None:
        return 0.0, 0.0
    ref = feat_ref.reshape(-1).astype(np.float32)
    cand = feat_cand.reshape(-1).astype(np.float32)
    ref /= (np.linalg.norm(ref) + 1e-6)
    cand /= (np.linalg.norm(cand) + 1e-6)
    sim = float(np.dot(ref, cand))
    similarity = max(0.0, min(100.0, sim * 100.0))
    return similarity, sim


def _median(values, fallback=0.0):
    if not values:
        return fallback
    return float(np.median(np.array(values, dtype=np.float32)))


def _annotate(img, box, label):
    x, y, w, h = box
    color = (0, 0, 255)
    cv2.rectangle(img, (x, y), (x + w, y + h), color, 2)

    if label:
        font = cv2.FONT_HERSHEY_SIMPLEX
        scale = 0.7
        thickness = 2
        (tw, th), baseline = cv2.getTextSize(label, font, scale, thickness)
        label_w = tw + 12
        label_h = th + 10
        top = max(0, y - label_h)
        cv2.rectangle(img, (x, top), (x + label_w, top + label_h), color, -1)
        cv2.putText(
            img,
            label,
            (x + 6, top + label_h - 6),
            font,
            scale,
            (255, 255, 255),
            thickness,
            cv2.LINE_AA
        )



def main():
    parser = argparse.ArgumentParser(description='Face matcher (LBPH + histogram).')
    parser.add_argument('--reference', required=True, help='Path to reference image')
    parser.add_argument('--candidate', required=True, help='Path to captured image')
    parser.add_argument('--threshold', type=float, default=70.0, help='Similarity threshold (0-100)')
    parser.add_argument('--label', default='', help='Label for annotation')
    parser.add_argument('--output', default='', help='Optional output path for annotated image')
    args = parser.parse_args()

    if not os.path.exists(args.reference):
        _error('Foto referensi tidak ditemukan')
    if not os.path.exists(args.candidate):
        _error('Foto selfie tidak ditemukan')
    if not os.path.exists(CASCADE_PATH):
        _error('Model deteksi wajah tidak ditemukan')

    cascade = cv2.CascadeClassifier(CASCADE_PATH)
    if cascade.empty():
        _error('Gagal memuat model deteksi wajah')

    eye_cascade = _load_eye_cascade()

    img_ref = cv2.imread(args.reference)
    img_cand = cv2.imread(args.candidate)

    if img_ref is None:
        _error('Gagal memuat foto referensi')
    if img_cand is None:
        _error('Gagal memuat foto selfie')

    gray_ref = cv2.cvtColor(img_ref, cv2.COLOR_BGR2GRAY)
    gray_cand = cv2.cvtColor(img_cand, cv2.COLOR_BGR2GRAY)

    box_ref = _detect_largest_face(gray_ref, cascade)
    if box_ref is None:
        _error('Wajah pada foto referensi tidak terdeteksi')

    img_ref_aligned, box_ref, ref_aligned = _align_face(img_ref, box_ref, cascade, eye_cascade)
    img_ref = img_ref_aligned
    gray_ref = cv2.cvtColor(img_ref, cv2.COLOR_BGR2GRAY)

    box_cand = _detect_largest_face(gray_cand, cascade)
    if box_cand is None:
        _error('Wajah pada foto selfie tidak terdeteksi')

    img_cand_aligned, box_cand, cand_aligned = _align_face(img_cand, box_cand, cascade, eye_cascade)
    img_cand = img_cand_aligned
    gray_cand = cv2.cvtColor(img_cand, cv2.COLOR_BGR2GRAY)

    face_ref = _preprocess_face(img_ref, box_ref)
    face_cand = _preprocess_face(img_cand, box_cand)
    face_ref_core = _extract_core_face(face_ref)

    cand_variants = [face_cand]
    cand_variants.append(_adjust_gamma(face_cand, 0.95))
    cand_variants.append(_adjust_gamma(face_cand, 1.05))
    cand_variants.append(cv2.convertScaleAbs(face_cand, alpha=1.06, beta=8))
    cand_variants.append(cv2.convertScaleAbs(face_cand, alpha=0.94, beta=-8))

    best = None
    for idx, cand in enumerate(cand_variants):
        cand_core = _extract_core_face(cand)

        lbph_similarity, lbph_conf, lbph_base_conf = _lbph_similarity(face_ref_core, cand_core)
        hist_similarity, hist_corr = _hist_similarity(face_ref_core, cand_core)
        edge_similarity, edge_corr = _edge_similarity(face_ref_core, cand_core)
        corr_similarity, corr_value = _corr_similarity(face_ref_core, cand_core)
        hog_similarity, hog_value = _hog_similarity(face_ref_core, cand_core)
        grad_similarity, grad_value = _gradient_similarity(face_ref_core, cand_core)

        base_similarity = (
            (lbph_similarity * 0.32) +
            (corr_similarity * 0.22) +
            (hog_similarity * 0.2) +
            (grad_similarity * 0.18) +
            (hist_similarity * 0.05) +
            (edge_similarity * 0.03)
        )
        quality_struct = _structural_quality(corr_similarity, hog_similarity, hist_similarity, edge_similarity, grad_similarity)
        quality = max(
            _quality_score(lbph_similarity, corr_similarity, edge_similarity, hist_similarity, hog_similarity, grad_similarity),
            quality_struct
        )
        score = (quality_struct * 0.62) + (base_similarity * 0.38)

        if best is None or score > best['score']:
            best = {
                'idx': idx,
                'face': cand,
                'face_core': cand_core,
                'lbph_similarity': lbph_similarity,
                'lbph_conf': lbph_conf,
                'lbph_base_conf': lbph_base_conf,
                'hist_similarity': hist_similarity,
                'hist_corr': hist_corr,
                'edge_similarity': edge_similarity,
                'edge_corr': edge_corr,
                'corr_similarity': corr_similarity,
                'corr_value': corr_value,
                'hog_similarity': hog_similarity,
                'hog_value': hog_value,
                'grad_similarity': grad_similarity,
                'grad_value': grad_value,
                'base_similarity': base_similarity,
                'quality_struct': quality_struct,
                'quality': quality,
                'score': score
            }

    lbph_similarity = best['lbph_similarity']
    lbph_conf = best['lbph_conf']
    lbph_base_conf = best['lbph_base_conf']
    hist_similarity = best['hist_similarity']
    hist_corr = best['hist_corr']
    edge_similarity = best['edge_similarity']
    edge_corr = best['edge_corr']
    corr_similarity = best['corr_similarity']
    corr_value = best['corr_value']
    hog_similarity = best['hog_similarity']
    hog_value = best['hog_value']
    grad_similarity = best['grad_similarity']
    grad_value = best['grad_value']
    base_similarity = best['base_similarity']
    quality_struct = best['quality_struct']
    quality = best['quality']

    # Build reference baselines from augmented versions to adapt to lighting variance.
    ref_augments = [
        cv2.flip(face_ref, 1),
        cv2.convertScaleAbs(face_ref, alpha=1.1, beta=10),
        cv2.convertScaleAbs(face_ref, alpha=0.9, beta=-10),
        cv2.GaussianBlur(face_ref, (3, 3), 0)
    ]
    base_lbph_list = []
    base_hist_list = []
    base_edge_list = []
    base_corr_list = []
    base_hog_list = []
    base_grad_list = []
    base_quality_list = []
    for aug in ref_augments:
        aug_core = _extract_core_face(aug)
        lbph_aug, _, _ = _lbph_similarity(face_ref_core, aug_core)
        hist_aug, _ = _hist_similarity(face_ref_core, aug_core)
        edge_aug, _ = _edge_similarity(face_ref_core, aug_core)
        corr_aug, _ = _corr_similarity(face_ref_core, aug_core)
        hog_aug, _ = _hog_similarity(face_ref_core, aug_core)
        grad_aug, _ = _gradient_similarity(face_ref_core, aug_core)
        base_lbph_list.append(lbph_aug)
        base_hist_list.append(hist_aug)
        base_edge_list.append(edge_aug)
        base_corr_list.append(corr_aug)
        base_hog_list.append(hog_aug)
        base_grad_list.append(grad_aug)
        base_quality_list.append(_structural_quality(corr_aug, hog_aug, hist_aug, edge_aug, grad_aug))

    base_lbph = _median(base_lbph_list, 70.0)
    base_hist = _median(base_hist_list, 60.0)
    base_edge = _median(base_edge_list, 55.0)
    base_corr = _median(base_corr_list, 60.0)
    base_hog = _median(base_hog_list, 60.0)
    base_grad = _median(base_grad_list, 60.0)
    base_quality = _median(base_quality_list, 65.0)

    if ref_aligned and cand_aligned:
        base_similarity = min(100.0, base_similarity + 4.0)

    relax = 3.5 if (ref_aligned and cand_aligned) else 0.0
    mean_ref = float(np.mean(face_ref_core))
    mean_cand = float(np.mean(best['face_core']))
    lighting_diff = abs(mean_ref - mean_cand)
    lighting_relax = min(0.10, lighting_diff / 140.0)

    min_lbph = max(48.0, (base_lbph * 0.78) * (1 - lighting_relax * 0.35) - relax)
    min_corr = max(48.0, (base_corr * 0.79) * (1 - lighting_relax * 0.35) - relax)
    min_edge = max(24.0, (base_edge * 0.6) * (1 - lighting_relax) - relax)
    min_hist = max(34.0, (base_hist * 0.72) * (1 - lighting_relax * 0.45) - relax)
    min_hog = max(50.0, (base_hog * 0.79) * (1 - lighting_relax * 0.35) - relax)
    min_grad = max(52.0, (base_grad * 0.79) * (1 - lighting_relax * 0.25) - relax)
    min_quality = max(58.0, (base_quality * 0.8) * (1 - lighting_relax * 0.35) - relax)

    # Hard floors to reduce false positives across different faces.
    hard_min_lbph = max(52.0, min_lbph)
    hard_min_corr = max(54.0, min_corr)
    hard_min_hog = max(57.0, min_hog)
    hard_min_grad = max(58.0, min_grad)
    hard_min_quality = max(63.0, min_quality)
    hard_min_hist = max(40.0, min_hist)
    hard_min_edge = max(30.0, min_edge)

    if lbph_base_conf < 1e-3:
        conf_ratio = 1.0
        conf_ratio_ok = True
    else:
        conf_ratio = lbph_conf / lbph_base_conf
        conf_ratio_ok = conf_ratio <= 1.8

    strong_structure = (
        corr_similarity >= hard_min_corr and
        hog_similarity >= hard_min_hog and
        grad_similarity >= hard_min_grad
    )
    name_detected = (
        quality >= hard_min_quality and
        (lbph_similarity >= hard_min_lbph or conf_ratio_ok) and
        (hist_similarity >= hard_min_hist or edge_similarity >= hard_min_edge) and
        (strong_structure or (quality_struct >= hard_min_quality and conf_ratio_ok))
    )

    similarity = max(0.0, min(100.0, score))

    if corr_similarity < hard_min_corr or hog_similarity < hard_min_hog or grad_similarity < hard_min_grad:
        name_detected = False
        similarity = min(similarity, 50.0)

    similarity = max(0.0, min(100.0, similarity))
    passed = bool(name_detected and similarity >= args.threshold)

    result = {
        'success': True,
        'similarity': round(similarity, 2),
        'passed': bool(passed),
        'threshold': args.threshold,
        'details': {
            'lbph_similarity': round(lbph_similarity, 2),
            'lbph_confidence': round(lbph_conf, 2),
            'lbph_base_confidence': round(lbph_base_conf, 2),
            'lbph_conf_ratio': round(conf_ratio, 3),
            'hist_similarity': round(hist_similarity, 2),
            'hist_corr': round(hist_corr, 4),
            'edge_similarity': round(edge_similarity, 2),
            'edge_corr': round(edge_corr, 4),
            'corr_similarity': round(corr_similarity, 2),
            'corr_value': round(corr_value, 4),
            'hog_similarity': round(hog_similarity, 2),
            'hog_value': round(hog_value, 4),
            'grad_similarity': round(grad_similarity, 2),
            'grad_value': round(grad_value, 4),
            'baseline_lbph': round(base_lbph, 2),
            'baseline_hist': round(base_hist, 2),
            'baseline_edge': round(base_edge, 2),
            'baseline_corr': round(base_corr, 2),
            'baseline_hog': round(base_hog, 2),
            'baseline_grad': round(base_grad, 2),
            'baseline_quality': round(base_quality, 2),
            'variant_index': int(best['idx']),
            'lighting_diff': round(lighting_diff, 2),
            'aligned_ref': bool(ref_aligned),
            'aligned_cand': bool(cand_aligned),
            'quality': round(quality, 2),
            'quality_struct': round(quality_struct, 2),
            'name_detected': bool(name_detected),
            'min_lbph': round(min_lbph, 2),
            'min_corr': round(min_corr, 2),
            'min_hist': round(min_hist, 2),
            'min_hog': round(min_hog, 2),
            'min_grad': round(min_grad, 2),
            'min_quality': round(min_quality, 2)
        }
    }

    if args.output:
        try:
            annotated = img_cand.copy()
            _annotate(annotated, box_cand, args.label)
            cv2.imwrite(args.output, annotated)
            result['annotated_path'] = args.output
        except Exception:
            result['annotated_path'] = ''

    print(json.dumps(result))


if __name__ == '__main__':
    main()
