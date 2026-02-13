import cv2
import pickle
import numpy as np
from pathlib import Path

BASE_DIR = Path(__file__).resolve().parent
DATA_DIR = BASE_DIR / "data"
CASCADE_PATH = DATA_DIR / "haarcascade_frontalface_default.xml"

DATA_DIR.mkdir(parents=True, exist_ok=True)

video = cv2.VideoCapture(0)
facedetect = cv2.CascadeClassifier(str(CASCADE_PATH))

faces_data = []
i = 0

name = input("Enter Your Name: ").strip() or "Unknown"

while True:
    ret, frame = video.read()
    gray = cv2.cvtColor(frame, cv2.COLOR_BGR2GRAY)
    faces = facedetect.detectMultiScale(gray, 1.3, 5)
    for (x, y, w, h) in faces:
        crop_img = frame[y:y + h, x:x + w, :]
        resized_img = cv2.resize(crop_img, (50, 50))
        if len(faces_data) < 100 and i % 10 == 0:
            faces_data.append(resized_img)
        i += 1
        cv2.putText(frame, str(len(faces_data)), (50, 50), cv2.FONT_HERSHEY_COMPLEX, 1, (50, 50, 255), 1)
        cv2.rectangle(frame, (x, y), (x + w, y + h), (50, 50, 255), 1)
    cv2.imshow("Frame", frame)
    k = cv2.waitKey(1)
    if k == ord('q') or len(faces_data) >= 100:
        break

video.release()
cv2.destroyAllWindows()

faces_data = np.asarray(faces_data, dtype=np.uint8)
if faces_data.size == 0:
    raise SystemExit("Tidak ada wajah yang berhasil direkam.")

faces_data = faces_data.reshape(len(faces_data), -1)
faces_count = faces_data.shape[0]

names_file = DATA_DIR / "names.pkl"
faces_file = DATA_DIR / "faces_data.pkl"

if not names_file.exists():
    names = [name] * faces_count
else:
    with names_file.open("rb") as f:
        names = pickle.load(f)
    names = names + [name] * faces_count

with names_file.open("wb") as f:
    pickle.dump(names, f)

if not faces_file.exists():
    existing = faces_data
else:
    with faces_file.open("rb") as f:
        existing = pickle.load(f)
    existing = np.append(existing, faces_data, axis=0)

with faces_file.open("wb") as f:
    pickle.dump(existing, f)
