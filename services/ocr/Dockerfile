FROM python:3.12-slim@sha256:ddf5c3a80ffbd5d4d39e8cb67f8b254f263aa86e2f78504d6b99a7cd2b98b6c0

ENV PYTHONDONTWRITEBYTECODE=1 \
    PYTHONUNBUFFERED=1 \
    PADDLE_PDX_DISABLE_MODEL_SOURCE_CHECK=True

WORKDIR /app

RUN useradd --system --uid 10001 ocr \
    && apt-get update \
    && apt-get install --no-install-recommends --yes libgomp1 libgl1 \
    && rm -rf /var/lib/apt/lists/*

COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

COPY app ./app
USER ocr
EXPOSE 8000
CMD ["uvicorn", "app.main:app", "--host", "0.0.0.0", "--port", "8000", "--workers", "1"]
