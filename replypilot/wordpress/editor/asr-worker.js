/* Speech to text with a time for every word. Runs in the member's browser; the model files live on this site. */
import { pipeline, env } from './transformers.min.js';
const BASE = new URL('./', import.meta.url).href;
env.allowRemoteModels = false;
env.allowLocalModels = true;
env.localModelPath = BASE;
env.backends.onnx.wasm.wasmPaths = BASE;
env.backends.onnx.wasm.numThreads = 1;
let asr = null;
self.onmessage = async (e) => {
  const { audio, model } = e.data;
  try {
    if (!asr) {
      asr = await pipeline('automatic-speech-recognition', model || 'model', {
        dtype: 'q8', device: 'wasm',
        progress_callback: (p) => self.postMessage({ type: 'progress', p })
      });
    }
    self.postMessage({ type: 'status' });
    const out = await asr(audio, { return_timestamps: 'word', chunk_length_s: 30, stride_length_s: 5 });
    self.postMessage({ type: 'done', out });
  } catch (err) {
    self.postMessage({ type: 'error', msg: String((err && err.message) || err) });
  }
};
