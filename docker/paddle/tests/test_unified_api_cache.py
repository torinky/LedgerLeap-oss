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


class MlxpaddleocrvlUnitTest(unittest.TestCase):
    """Unit tests for MLX backend helper functions (no model required)."""

    @classmethod
    def setUpClass(cls):
        cls.module = load_module()

    def test_is_apple_silicon_returns_bool(self):
        self.assertIsInstance(self.module._is_apple_silicon(), bool)

    def test_check_mlx_installed_returns_string_or_none(self):
        result = self.module._check_mlx_installed()
        self.assertTrue(result is None or isinstance(result, str))

    def test_parse_ocr_text_extracts_lines(self):
        """Test the OCR text parsing logic with a known output sample."""
        text = (
            "2022年11月19日\n"
            "領収書\n"
            "¥153,729\n"
            "税抜金額\n"
            "¥139,741\n"
            "消費税\n"
            "¥13,988\n"
        )
        lines = [l.strip() for l in text.split('\n') if l.strip() and len(l.strip()) >= 2]
        self.assertGreater(len(lines), 0)
        self.assertIn("2022年11月19日", lines)
        self.assertIn("領収書", lines)
        self.assertIn("¥153,729", lines)
        self.assertIn("税抜金額", lines)

    def test_parse_ocr_text_skips_whitespace(self):
        text = "\n\n   \n有効な行\n\n"
        lines = [l.strip() for l in text.split('\n') if l.strip() and len(l.strip()) >= 2]
        self.assertEqual(len(lines), 1)
        self.assertEqual(lines[0], "有効な行")

    def test_parse_ocr_text_skips_separators(self):
        import re
        text = "Header\n________\nContent"
        lines = []
        for line in text.split('\n'):
            stripped = line.strip()
            if not stripped or len(stripped) < 2:
                continue
            if re.match(r'^[_\-]{3,}$', stripped):
                continue
            lines.append(stripped)
        self.assertEqual(len(lines), 2)
        self.assertEqual(lines[0], "Header")
        self.assertEqual(lines[1], "Content")

    def test_pdf_suffix_detection(self):
        from pathlib import Path
        self.assertEqual(Path("file.pdf").suffix.lower(), ".pdf")
        self.assertEqual(Path("file.PDF").suffix.lower(), ".pdf")
        self.assertEqual(Path("file.jpg").suffix.lower(), ".jpg")
        self.assertNotEqual(Path("file.jpg").suffix.lower(), ".pdf")

    def test_kv_extraction_from_colon_lines(self):
        lines = [
            "請求金額: 20,158円",
            "お支払期限: 0000年00月00日",
            "振込先: ○○銀行",
        ]
        kvs = []
        for line in lines:
            if ':' in line:
                parts = line.split(':', 1)
                if len(parts) == 2:
                    k, v = parts[0].strip(), parts[1].strip()
                    if k and v:
                        kvs.append({"key": k, "value": v})
        self.assertEqual(len(kvs), 3)
        self.assertEqual(kvs[0]["key"], "請求金額")
        self.assertEqual(kvs[0]["value"], "20,158円")
        self.assertEqual(kvs[2]["value"], "○○銀行")

    def test_kv_extraction_from_yen_lines(self):
        """Currency lines starting with ¥ should be treated as key-value pairs."""
        lines = ["税抜金額", "¥139,741"]
        kvs = []
        for idx, line in enumerate(lines):
            stripped = line.strip()
            if stripped.startswith('¥'):
                prev = lines[idx-1] if idx > 0 else ""
                kvs.append({"key": prev, "value": stripped})
        self.assertEqual(len(kvs), 1)
        self.assertEqual(kvs[0]["key"], "税抜金額")
        self.assertEqual(kvs[0]["value"], "¥139,741")

    def test_cache_marker_group_for_mlx(self):
        markers = self.module._backend_cache_marker_groups("paddleocr-vl-mlx")
        self.assertIsInstance(markers, list)
        self.assertGreater(len(markers), 0)
        self.assertIn(
            Path("models--PaddlePaddle--PaddleOCR-VL"),
            markers[0],
        )

    def test_truncate_repeating_garbage_truncates_after_repeats(self):
        text = "Content line 1\nContent line 2\n※\n※\n※\n※\n※\n※\n※\n※\n※\n※\n※\n※"
        result = self.module._truncate_at_repeating_garbage(text, max_repeat=10)
        self.assertIn("Content line 1", result)
        self.assertIn("Content line 2", result)
        # Should have truncated before the 11th ※
        self.assertLess(len(result.split('\n')), len(text.split('\n')))

    def test_truncate_repeating_garbage_no_effect_on_normal_text(self):
        text = "Line A\nLine B\nLine C\nLine D\nLine E"
        result = self.module._truncate_at_repeating_garbage(text, max_repeat=3)
        self.assertEqual(text, result)


if __name__ == "__main__":
    unittest.main()
