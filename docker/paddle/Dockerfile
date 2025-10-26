# docker/paddle/Dockerfile
FROM python:3.10-slim

# Set working directory to the mount point
WORKDIR /var/www/html

RUN apt-get update && apt-get install -y \
    libgomp1 \
    libglib2.0-0 \
    libsm6 \
    libxext6 \
    libxrender-dev \
    libgl1 \
    && rm -rf /var/lib/apt/lists/*

# Copy requirements from the project volume and install
COPY requirements.txt .
RUN pip install --no-cache-dir -r requirements.txt

# The app.py is now available via the volume mount, so no COPY needed.
# The CMD will be executed from the WORKDIR.
EXPOSE 8000
CMD ["uvicorn", "docker.paddle.app:app", "--host", "0.0.0.0", "--port", "8000"]
