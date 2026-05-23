import asyncio
import importlib.util
import os
import sys
import tempfile
import types
import unittest
from contextlib import contextmanager
from pathlib import Path


ROOT_DIR = Path(__file__).resolve().parents[3]
MODULE_PATH = ROOT_DIR / "docker" / "embedding" / "app.py"


@contextmanager
def stubbed_dependencies(cache_lookup):
    original_modules = {
        name: sys.modules.get(name)
        for name in (
            "fastapi",
            "pydantic",
            "sentence_transformers",
            "starlette",
            "starlette.status",
            "torch",
            "huggingface_hub",
        )
    }

    fastapi = types.ModuleType("fastapi")

    class FastAPI:
        def __init__(self, *args, **kwargs):
            # Intentionally empty: the test only needs the constructor to accept any arguments.
            pass

        def on_event(self, *args, **kwargs):
            return lambda fn: fn

        def get(self, *args, **kwargs):
            return lambda fn: fn

        def post(self, *args, **kwargs):
            return lambda fn: fn

    class HTTPException(Exception):
        pass

    class Response:
        def __init__(self, *args, **kwargs):
            self.status_code = None
            self.headers = {}

    fastapi.FastAPI = FastAPI
    fastapi.HTTPException = HTTPException
    fastapi.Response = Response

    pydantic = types.ModuleType("pydantic")

    class BaseModel:
        def __init__(self, **data):
            for key, value in data.items():
                setattr(self, key, value)

    pydantic.BaseModel = BaseModel

    torch = types.ModuleType("torch")
    torch._num_threads = 4
    torch._num_interop_threads = 4
    torch.set_num_threads = lambda value: setattr(torch, "_num_threads", value)
    torch.set_num_interop_threads = lambda value: setattr(torch, "_num_interop_threads", value)
    torch.get_num_threads = lambda: torch._num_threads
    torch.get_num_interop_threads = lambda: torch._num_interop_threads

    sentence_transformers = types.ModuleType("sentence_transformers")

    class SentenceTransformer:
        instances = []

        def __init__(self, model_name_or_path, **kwargs):
            self.model_name_or_path = model_name_or_path
            self.kwargs = kwargs
            type(self).instances.append(self)

        def encode(self, texts, **kwargs):
            return []

        def get_sentence_embedding_dimension(self):
            return 768

    sentence_transformers.SentenceTransformer = SentenceTransformer

    starlette = types.ModuleType("starlette")
    starlette_status = types.ModuleType("starlette.status")
    starlette_status.HTTP_503_SERVICE_UNAVAILABLE = 503
    starlette.status = starlette_status

    huggingface_hub = types.ModuleType("huggingface_hub")
    huggingface_hub.try_to_load_from_cache = cache_lookup

    sys.modules["fastapi"] = fastapi
    sys.modules["pydantic"] = pydantic
    sys.modules["sentence_transformers"] = sentence_transformers
    sys.modules["starlette"] = starlette
    sys.modules["starlette.status"] = starlette_status
    sys.modules["torch"] = torch
    sys.modules["huggingface_hub"] = huggingface_hub

    try:
        yield SentenceTransformer
    finally:
        for name, module in original_modules.items():
            if module is None:
                sys.modules.pop(name, None)
            else:
                sys.modules[name] = module


def load_module(cache_lookup):
    spec = importlib.util.spec_from_file_location("embedding_app_test_target", MODULE_PATH)
    module = importlib.util.module_from_spec(spec)
    assert spec.loader is not None
    spec.loader.exec_module(module)
    return module


class EmbeddingCacheDetectionTest(unittest.TestCase):
    def setUp(self):
        self.cache_root = Path(tempfile.mkdtemp(prefix="ledgerleap-embedding-cache-"))
        self.model_name = "cl-nagoya/ruri-v3-310m"
        self.snapshot_id = "18b60fb8c2b9df296fb4212bb7d23ef94e579cd3"
        self.snapshot_dir = self.cache_root / f"models--{self.model_name.replace('/', '--')}" / "snapshots" / self.snapshot_id
        self.original_env = {key: os.environ.get(key) for key in ("EMBEDDING_MODEL", "EMBEDDING_OFFLINE", "EMBEDDING_CACHE_DIR")}
        os.environ["EMBEDDING_MODEL"] = self.model_name
        os.environ["EMBEDDING_OFFLINE"] = "auto"
        os.environ["EMBEDDING_CACHE_DIR"] = str(self.cache_root)

    def tearDown(self):
        for key, value in self.original_env.items():
            if value is None:
                os.environ.pop(key, None)
            else:
                os.environ[key] = value

    def _write_file(self, relative_path: str) -> None:
        full_path = self.snapshot_dir / relative_path
        full_path.parent.mkdir(parents=True, exist_ok=True)
        full_path.touch()

    def _cache_lookup(self, repo_id, filename, cache_dir):
        if repo_id != self.model_name or filename != "config.json":
            return None

        candidate = Path(cache_dir) / f"models--{repo_id.replace('/', '--')}" / "snapshots" / self.snapshot_id / filename
        return str(candidate) if candidate.exists() else None

    def test_complete_snapshot_enables_offline_mode_and_local_snapshot_loading(self):
        for relative_path in (
            "config.json",
            "modules.json",
            "sentence_bert_config.json",
            "1_Pooling/config.json",
            "tokenizer.json",
            "model.safetensors",
        ):
            self._write_file(relative_path)

        with stubbed_dependencies(self._cache_lookup) as fake_sentence_transformer:
            module = load_module(self._cache_lookup)

            local_files_only = module._resolve_local_files_only(self.model_name, str(self.cache_root))
            self.assertTrue(local_files_only)

            asyncio.run(module._load_model())

            self.assertEqual(module.app_status, module.AppStatus.READY)
            self.assertEqual(module.loaded_model_name, self.model_name)
            self.assertEqual(len(fake_sentence_transformer.instances), 1)
            self.assertEqual(fake_sentence_transformer.instances[0].model_name_or_path, self.model_name)
            self.assertTrue(fake_sentence_transformer.instances[0].kwargs["local_files_only"])

    def test_config_only_snapshot_falls_back_to_online_loading(self):
        self._write_file("config.json")

        with stubbed_dependencies(self._cache_lookup) as fake_sentence_transformer:
            module = load_module(self._cache_lookup)

            local_files_only = module._resolve_local_files_only(self.model_name, str(self.cache_root))
            self.assertFalse(local_files_only)

            asyncio.run(module._load_model())

            self.assertEqual(module.app_status, module.AppStatus.READY)
            self.assertEqual(module.loaded_model_name, self.model_name)
            self.assertEqual(len(fake_sentence_transformer.instances), 1)
            self.assertEqual(fake_sentence_transformer.instances[0].model_name_or_path, self.model_name)
            self.assertFalse(fake_sentence_transformer.instances[0].kwargs["local_files_only"])


if __name__ == "__main__":
    unittest.main()


