import argparse
import json
import os
import sys
from pathlib import Path
from typing import Dict, List, Optional, Tuple

import cv2

# Keep python output JSON-friendly even on Windows cp1252 consoles.
if hasattr(sys.stdout, "reconfigure"):
    sys.stdout.reconfigure(encoding="utf-8", errors="replace")
if hasattr(sys.stderr, "reconfigure"):
    sys.stderr.reconfigure(encoding="utf-8", errors="replace")

IMAGE_EXTS = {".jpg", ".jpeg", ".png", ".bmp", ".webp"}
DEFAULT_MODEL = "SFace"
DEFAULT_DETECTOR = "opencv"
DEFAULT_METRIC = "cosine"
DEFAULT_MAX_REFERENCES = 1
DEFAULT_BACKUP_MODEL = "Facenet512"
DEFAULT_BACKUP_DETECTOR = "retinaface"
DEFAULT_BACKUP_MAX_REFERENCES = 3


def emit(payload: Dict, code: int = 0) -> None:
    print(json.dumps(payload, ensure_ascii=False))
    sys.exit(code)


def fail(message: str, code: int = 1, details: Optional[Dict] = None) -> None:
    payload = {"success": False, "error": message}
    if details:
        payload["details"] = details
    emit(payload, code)


def is_image_file(path: Path) -> bool:
    return path.is_file() and path.suffix.lower() in IMAGE_EXTS


def extract_identity_token(file_path: Path) -> str:
    stem = file_path.stem.strip()
    if not stem:
        return ""
    parts = stem.replace("_", "-").split("-")
    if not parts:
        return ""
    token = parts[0].strip()
    return token


def scan_references(reference: Path, max_refs: int) -> List[Path]:
    refs: List[Path] = []
    seen = set()

    def add_ref(candidate: Path) -> None:
        key = str(candidate.resolve()).lower()
        if key in seen:
            return
        if is_image_file(candidate):
            refs.append(candidate)
            seen.add(key)

    if reference.is_dir():
        for child in sorted(reference.rglob("*")):
            add_ref(child)
    else:
        add_ref(reference)
        parent = reference.parent
        token = extract_identity_token(reference)
        if token and parent.exists():
            token_lower = token.lower()
            for child in parent.iterdir():
                if not is_image_file(child):
                    continue
                stem = child.stem.lower()
                if (
                    stem == token_lower
                    or stem.startswith(token_lower + "-")
                    or stem.startswith(token_lower + "_")
                ):
                    add_ref(child)

    refs.sort(key=lambda p: p.stat().st_mtime, reverse=True)
    if max_refs > 0:
        refs = refs[:max_refs]
    return refs


def compute_similarity(distance: float, threshold: float) -> float:
    if threshold <= 0:
        threshold = 1.0
    if distance <= threshold:
        margin = threshold - distance
        score = 90.0 + min(10.0, (margin / threshold) * 10.0)
    else:
        over = distance - threshold
        score = 90.0 - min(90.0, (over / threshold) * 90.0)
    return max(0.0, min(100.0, score))


def extract_candidate_box(result: Dict) -> Optional[Tuple[int, int, int, int]]:
    facial_areas = result.get("facial_areas")
    if isinstance(facial_areas, dict):
        img2 = facial_areas.get("img2")
        if isinstance(img2, dict):
            x = int(img2.get("x", 0))
            y = int(img2.get("y", 0))
            w = int(img2.get("w", 0))
            h = int(img2.get("h", 0))
            if w > 0 and h > 0:
                return x, y, w, h
    return None


def draw_annotation(
    candidate_path: Path,
    output_path: Path,
    label: str,
    similarity: float,
    box: Optional[Tuple[int, int, int, int]],
) -> bool:
    img = cv2.imread(str(candidate_path))
    if img is None:
        return False

    if box:
        x, y, w, h = box
        cv2.rectangle(img, (x, y), (x + w, y + h), (18, 189, 126), 2)

    text = label.strip() if label else "Face Verified"
    text = f"{text} | {similarity:.2f}%"

    (tw, th), baseline = cv2.getTextSize(text, cv2.FONT_HERSHEY_SIMPLEX, 0.58, 2)
    tx, ty = 12, 24
    cv2.rectangle(img, (tx - 6, ty - th - 8), (tx + tw + 6, ty + baseline + 4), (18, 189, 126), -1)
    cv2.putText(img, text, (tx, ty), cv2.FONT_HERSHEY_SIMPLEX, 0.58, (255, 255, 255), 2, cv2.LINE_AA)

    output_path.parent.mkdir(parents=True, exist_ok=True)
    return bool(cv2.imwrite(str(output_path), img))


def deepface_verify(deepface, kwargs: Dict) -> Dict:
    try:
        return deepface.verify(**kwargs)
    except TypeError:
        fallback_kwargs = dict(kwargs)
        fallback_kwargs.pop("align", None)
        return deepface.verify(**fallback_kwargs)


def build_detector_candidates(primary_detector: str, stage: str) -> List[str]:
    detector = (primary_detector or DEFAULT_DETECTOR).strip() or DEFAULT_DETECTOR
    candidates = [detector]

    # Keep primary stage fast: only one detector.
    if stage == "primary":
        return candidates

    # Backup stage is for difficult cases: add broader detector fallbacks.
    for fallback in ["retinaface", "mtcnn", "opencv", "mediapipe"]:
        if fallback not in candidates:
            candidates.append(fallback)
    return candidates


def main() -> None:
    os.environ.setdefault("TF_CPP_MIN_LOG_LEVEL", "2")

    parser = argparse.ArgumentParser(description="DeepFace-based face matcher")
    parser.add_argument("--reference", required=True, help="Path to reference image or folder")
    parser.add_argument("--candidate", required=True, help="Path to candidate image")
    parser.add_argument("--threshold", type=float, default=89.0, help="Final similarity threshold (0-100)")
    parser.add_argument("--label", default="", help="Label for output annotation")
    parser.add_argument("--output", default="", help="Optional annotated output path")
    parser.add_argument("--model", default=DEFAULT_MODEL, help="DeepFace model name")
    parser.add_argument("--detector", default=DEFAULT_DETECTOR, help="Primary detector backend")
    parser.add_argument("--metric", default=DEFAULT_METRIC, help="Distance metric")
    parser.add_argument("--enforce-detection", default="true", help="true/false")
    parser.add_argument("--max-references", type=int, default=DEFAULT_MAX_REFERENCES, help="Max references to evaluate")
    parser.add_argument("--use-backup", default="true", help="true/false")
    parser.add_argument("--backup-model", default=DEFAULT_BACKUP_MODEL, help="Backup DeepFace model name")
    parser.add_argument("--backup-detector", default=DEFAULT_BACKUP_DETECTOR, help="Backup detector backend")
    parser.add_argument(
        "--backup-max-references",
        type=int,
        default=DEFAULT_BACKUP_MAX_REFERENCES,
        help="Max references for backup stage",
    )
    args = parser.parse_args()

    reference = Path(args.reference).expanduser().resolve()
    candidate = Path(args.candidate).expanduser().resolve()

    if not reference.exists():
        fail("Foto referensi tidak ditemukan", details={"reference": str(reference)})
    if not candidate.exists():
        fail("Foto selfie tidak ditemukan", details={"candidate": str(candidate)})
    if not is_image_file(candidate):
        fail("Format foto selfie tidak didukung")

    try:
        from deepface import DeepFace
    except Exception as exc:
        fail(
            "Library DeepFace belum terpasang. Jalankan: pip install deepface",
            details={"python_error": str(exc)},
        )

    primary_max_refs = max(1, int(args.max_references))
    backup_max_refs = max(1, int(args.backup_max_references))
    use_backup = str(args.use_backup).strip().lower() not in {"0", "false", "no", "off"}

    references = scan_references(reference, max_refs=max(primary_max_refs, backup_max_refs))
    if not references:
        fail("Tidak ada foto referensi valid untuk siswa ini")

    enforce_detection = str(args.enforce_detection).strip().lower() not in {"0", "false", "no", "off"}
    primary_detector = args.detector.strip() or DEFAULT_DETECTOR
    primary_model = args.model.strip() or DEFAULT_MODEL
    backup_detector = args.backup_detector.strip() or DEFAULT_DETECTOR
    backup_model = args.backup_model.strip() or primary_model

    strategies = [
        {
            "stage": "primary",
            "model": primary_model,
            "detector": primary_detector,
            "max_refs": primary_max_refs,
        }
    ]
    if use_backup and (
        backup_model.lower() != primary_model.lower()
        or backup_detector.lower() != primary_detector.lower()
        or backup_max_refs != primary_max_refs
    ):
        strategies.append(
            {
                "stage": "backup",
                "model": backup_model,
                "detector": backup_detector,
                "max_refs": backup_max_refs,
            }
        )

    attempts = []
    best = None

    for strategy in strategies:
        stage = strategy["stage"]
        model_name = strategy["model"]
        stage_detector = strategy["detector"]
        stage_references = references[: max(1, int(strategy["max_refs"]))]
        detector_candidates = build_detector_candidates(stage_detector, stage)

        for ref in stage_references:
            for detector in detector_candidates:
                kwargs = {
                    "img1_path": str(ref),
                    "img2_path": str(candidate),
                    "model_name": model_name,
                    "detector_backend": detector,
                    "distance_metric": args.metric,
                    "enforce_detection": enforce_detection,
                    "align": True,
                }
                try:
                    result = deepface_verify(DeepFace, kwargs)
                except Exception as exc:
                    attempts.append(
                        {
                            "stage": stage,
                            "model_name": model_name,
                            "reference_image": ref.name,
                            "detector_backend": detector,
                            "error": str(exc),
                        }
                    )
                    continue

                distance = float(result.get("distance", 0.0))
                verified = bool(result.get("verified", False))
                deepface_threshold = float(result.get("threshold", 0.0))
                similarity = compute_similarity(distance=distance, threshold=deepface_threshold)

                item = {
                    "stage": stage,
                    "model_name": model_name,
                    "reference_image": ref.name,
                    "reference_path": str(ref),
                    "detector_backend": detector,
                    "distance": distance,
                    "deepface_threshold": deepface_threshold,
                    "verified": verified,
                    "similarity": similarity,
                    "result": result,
                }
                attempts.append(
                    {
                        "stage": stage,
                        "model_name": model_name,
                        "reference_image": ref.name,
                        "detector_backend": detector,
                        "distance": round(distance, 6),
                        "threshold": round(deepface_threshold, 6),
                        "verified": verified,
                        "similarity": round(similarity, 2),
                    }
                )

                if best is None:
                    best = item
                else:
                    # Prioritize verified results, then lower distance.
                    if item["verified"] and not best["verified"]:
                        best = item
                    elif item["verified"] == best["verified"] and item["distance"] < best["distance"]:
                        best = item

                # Early stop for strong verified result.
                if verified and similarity >= 95:
                    break
            if best is not None and best["verified"] and best["similarity"] >= 95:
                break
        if best is not None and best["verified"] and best["similarity"] >= 95:
            break

    if best is None:
        fail(
            "Gagal memproses wajah dengan DeepFace",
            details={
                "attempts": attempts[-6:],
                "model": primary_model,
                "detector": primary_detector,
            },
        )

    final_similarity = float(best["similarity"])
    passed = bool(best["verified"] and final_similarity >= float(args.threshold))
    box = extract_candidate_box(best["result"])

    response = {
        "success": True,
        "similarity": round(final_similarity, 2),
        "passed": passed,
        "threshold": float(args.threshold),
        "details": {
            "source": "python-deepface",
            "model_name": best["model_name"],
            "distance_metric": args.metric,
            "detector_backend": best["detector_backend"],
            "stage_used": best["stage"],
            "distance": round(float(best["distance"]), 6),
            "deepface_threshold": round(float(best["deepface_threshold"]), 6),
            "verified_by_deepface": bool(best["verified"]),
            "reference_image": best["reference_image"],
            "total_references_scanned": len(references),
            "total_attempts": len(attempts),
            "backup_enabled": use_backup,
            "backup_model": backup_model if use_backup else "",
            "backup_detector": backup_detector if use_backup else "",
            "attempts": attempts[-10:],
        },
    }

    if box:
        response["details"]["face_box"] = {"x": box[0], "y": box[1], "w": box[2], "h": box[3]}

    if args.output:
        output_path = Path(args.output).expanduser().resolve()
        if draw_annotation(candidate, output_path, args.label, final_similarity, box):
            response["annotated_path"] = str(output_path)

    emit(response)


if __name__ == "__main__":
    main()
