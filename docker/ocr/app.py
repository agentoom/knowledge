"""Local OCR service for the Agentoom Knowledge pipeline.

Exposes exactly two endpoints:

    GET  /health            readiness probe -> {"status": "ok"}
    POST /ocr               raw image body (Content-Type = image MIME)
                            -> {"text": "recognized text"}

The OCR model is loaded lazily on the first request so the container can
start before the (large) PaddleOCR dependencies finish initializing.
"""

import os
import tempfile

from fastapi import FastAPI, Request
from paddleocr import PaddleOCR

app = FastAPI(title="Agentoom OCR Service")

_ocr: PaddleOCR | None = None

_EXTENSIONS = {
    "image/png": ".png",
    "image/jpeg": ".jpg",
    "image/jpg": ".jpg",
    "image/tiff": ".tiff",
    "image/tif": ".tif",
    "image/webp": ".webp",
    "image/bmp": ".bmp",
    "image/gif": ".gif",
}


def _ocr_instance() -> PaddleOCR:
    global _ocr
    if _ocr is None:
        _ocr = PaddleOCR(use_angle_cls=True, lang="en", show_log=False)
    return _ocr


@app.get("/health")
def health():
    return {"status": "ok"}


@app.post("/ocr")
async def recognize(request: Request):
    body = await request.body()

    if not body:
        return {"text": ""}

    content_type = (request.headers.get("content-type") or "").split(";")[0].strip().lower()
    suffix = _EXTENSIONS.get(content_type, ".img")

    fd, path = tempfile.mkstemp(suffix=suffix)
    try:
        with os.fdopen(fd, "wb") as handle:
            handle.write(body)

        result = _ocr_instance().ocr(path, cls=True)

        lines = []
        for block in result or []:
            for line in block or []:
                if line and len(line) > 1 and line[1]:
                    lines.append(str(line[1][0]))

        return {"text": "\n".join(lines).strip()}
    finally:
        try:
            os.unlink(path)
        except OSError:
            pass
