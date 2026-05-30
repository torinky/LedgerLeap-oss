import importlib.util
import os
import shutil
import sys
import tempfile
import types
import unittest
from contextlib import contextmanager
from pathlib import Path


ROOT_DIR = Path(__file__).resolve().parents[3]
MODULE_PATH = ROOT_DIR / "docker" / "paddle" / "unified_api.py"


@contextmanager
def fastapi_stubs():
    original_fastapi = sys.modules.get("fastapi")
    original_responses = sys.modules.get("fastapi.responses")

    fastapi = types.ModuleType("fastapi")
    responses = types.ModuleType("fastapi.responses")

    class FastAPI:
        def __init__(self, *args, **kwargs):
            pass

        def on_event(self, *args, **kwargs):
            return lambda fn: fn

        def get(self, *args, **kwargs):
            return lambda fn: fn

        def post(self, *args, **kwargs):
            return lambda fn: fn

    class HTTPException(Exception):
        pass

    class UploadFile:
        pass

    class JSONResponse:
        pass

    def File(*args, **kwargs):
        return None

    fastapi.FastAPI = FastAPI
    fastapi.File = File
    fastapi.UploadFile = UploadFile
    fastapi.HTTPException = HTTPException
    responses.JSONResponse = JSONResponse

    sys.modules["fastapi"] = fastapi
    sys.modules["fastapi.responses"] = responses
    try:
        yield
    finally:
        if original_fastapi is not None:
            sys.modules["fastapi"] = original_fastapi
        else:
            sys.modules.pop("fastapi", None)

        if original_responses is not None:
            sys.modules["fastapi.responses"] = original_responses
        else:
            sys.modules.pop("fastapi.responses", None)


def load_module():
    with fastapi_stubs():
        spec = importlib.util.spec_from_file_location("unified_api_test_target", MODULE_PATH)
        module = importlib.util.module_from_spec(spec)
        assert spec.loader is not None
        spec.loader.exec_module(module)
        return module


class UnifiedApiCacheDetectionTest(unittest.TestCase):
    def setUp(self):
        self.module = load_module()
        self.cache_root = Path(tempfile.mkdtemp(prefix="ledgerleap-vlm-cache-"))
        self.original_env = {key: os.environ.get(key) for key in (
            "VLM_CACHE_DIR",
            "PADDLEOCR_CACHE_DIR",
            "PADDLEOCR_HOME",
            "PADDLEX_HOME",
            "VLM_OFFLINE",
        )}
        os.environ["VLM_CACHE_DIR"] = str(self.cache_root)
        for key in ("PADDLEOCR_CACHE_DIR", "PADDLEOCR_HOME", "PADDLEX_HOME", "VLM_OFFLINE"):
            os.environ.pop(key, None)

    def tearDown(self):
        shutil.rmtree(self.cache_root, ignore_errors=True)
        for key, value in self.original_env.items():
            if value is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = value

    def _touch(self, relative_paths: list[Path]) -> None:
        for relative_path in relative_paths:
            full_path = self.cache_root / relative_path
            full_path.parent.mkdir(parents=True, exist_ok=True)
            full_path.touch()

    def test_detects_current_japanese_tar_cache_layout(self):
        self._touch([
            Path("det/ml/Multilingual_PP-OCRv3_det_infer/Multilingual_PP-OCRv3_det_infer.tar"),
            Path("rec/japan/japan_PP-OCRv4_rec_infer/japan_PP-OCRv4_rec_infer.tar"),
            Path("cls/ch_ppocr_mobile_v2.0_cls_infer/ch_ppocr_mobile_v2.0_cls_infer.tar"),
        ])

        self.assertTrue(self.module._is_backend_cached("paddleocr"))

    def test_detects_legacy_multilingual_tar_cache_layout(self):
        self._touch([
            Path("det/ml/Multilingual_PP-OCRv3_det_infer/Multilingual_PP-OCRv3_det_infer.tar"),
            Path("rec/ml/Multilingual_PP-OCRv3_rec_infer/Multilingual_PP-OCRv3_rec_infer.tar"),
            Path("cls/ch_ppocr_mobile_v2.0_cls_infer/ch_ppocr_mobile_v2.0_cls_infer.tar"),
        ])

        self.assertTrue(self.module._is_backend_cached("paddleocr"))

    def test_detects_extracted_cache_layout(self):
        self._touch([
            Path("det/ml/Multilingual_PP-OCRv3_det_infer/inference.pdiparams"),
            Path("rec/japan/japan_PP-OCRv4_rec_infer/inference.pdiparams"),
            Path("cls/ch_ppocr_mobile_v2.0_cls_infer/inference.pdiparams"),
        ])

        self.assertTrue(self.module._is_backend_cached("paddleocr"))

    def test_rejects_incomplete_cache_layout(self):
        self._touch([
            Path("det/ml/Multilingual_PP-OCRv3_det_infer/Multilingual_PP-OCRv3_det_infer.tar"),
            Path("rec/japan/japan_PP-OCRv4_rec_infer/japan_PP-OCRv4_rec_infer.tar"),
        ])

        self.assertFalse(self.module._is_backend_cached("paddleocr"))

    def test_auto_offline_mode_turns_on_when_cache_exists(self):
        self._touch([
            Path("det/ml/Multilingual_PP-OCRv3_det_infer/Multilingual_PP-OCRv3_det_infer.tar"),
            Path("rec/japan/japan_PP-OCRv4_rec_infer/japan_PP-OCRv4_rec_infer.tar"),
            Path("cls/ch_ppocr_mobile_v2.0_cls_infer/ch_ppocr_mobile_v2.0_cls_infer.tar"),
        ])
        os.environ["VLM_OFFLINE"] = "auto"

        self.assertTrue(self.module._resolve_offline_mode("paddleocr"))

    def test_forced_offline_mode_raises_without_complete_cache(self):
        os.environ["VLM_OFFLINE"] = "1"

        with self.assertRaises(RuntimeError):
            self.module._resolve_offline_mode("paddleocr")


if __name__ == "__main__":
    unittest.main()
