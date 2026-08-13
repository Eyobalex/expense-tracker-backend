import os
import tempfile
from functools import lru_cache
from pathlib import Path

from fastapi import FastAPI, File, HTTPException, UploadFile
from paddleocr import PaddleOCR

MAX_BYTES = 15 * 1024 * 1024
SUPPORTED_MIME_TYPES = {"image/jpeg", "image/png", "image/webp"}
SUFFIXES = {"image/jpeg": ".jpg", "image/png": ".png", "image/webp": ".webp"}
MODEL_VERSION = os.environ.get("PADDLE_OCR_MODEL_VERSION", "PP-OCRv6")

app = FastAPI(title="expense-tracker-private-ocr", docs_url=None, redoc_url=None)


@lru_cache
def engine() -> PaddleOCR:
    return PaddleOCR(lang="en", ocr_version=MODEL_VERSION)


@app.get("/health")
def health() -> dict[str, str]:
    return {"status": "ok", "model_version": MODEL_VERSION}


@app.post("/v1/ocr")
async def recognize(image: UploadFile = File(...)) -> dict[str, object]:
    if image.content_type not in SUPPORTED_MIME_TYPES:
        raise HTTPException(status_code=415, detail="unsupported_image_type")
    payload = await image.read(MAX_BYTES + 1)
    if len(payload) > MAX_BYTES:
        raise HTTPException(status_code=413, detail="image_too_large")
    descriptor, path = tempfile.mkstemp(suffix=SUFFIXES[image.content_type])
    try:
        with os.fdopen(descriptor, "wb") as temporary:
            temporary.write(payload)
        result = engine().predict(path)
        lines: list[dict[str, object]] = []
        for page in result:
            data = page.json
            for item in data.get("res", {}).get("rec_texts", []):
                lines.append({"text": item})
    except Exception as exc:
        raise HTTPException(status_code=422, detail="ocr_failed") from exc
    finally:
        Path(path).unlink(missing_ok=True)

    return {
        "text": "\n".join(str(line["text"]) for line in lines),
        "lines": lines,
        "confidence": {},
        "provider_version": "paddleocr-3.7.0",
        "model_version": MODEL_VERSION,
    }
