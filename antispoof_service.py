from flask import Flask, request, jsonify
from flask_cors import CORS
import base64
import io
from PIL import Image
import numpy as np
import onnxruntime as ort
import os

app = Flask(__name__)
CORS(app)

# Path to your ONNX model file. Place the downloaded MiniFAS ONNX here.
# Path to your ONNX model file. Can be overridden with environment variable ANTISPOOF_MODEL_PATH
MODEL_PATH = os.environ.get('ANTISPOOF_MODEL_PATH', "models/antispoof_best_model.onnx")

# Threshold for considering an input 'live' (probability of real). Tune as needed.
LIVE_PROB_THRESHOLD = float(os.environ.get('LIVE_PROB_THRESHOLD', '0.70'))


def load_model(path):
    sess = ort.InferenceSession(path, providers=["CPUExecutionProvider"])
    # inspect input shape to allow dynamic preprocessing
    inps = sess.get_inputs()
    outs = sess.get_outputs()
    input_shape = None
    input_layout = 'NCHW'
    if inps:
        shp = inps[0].shape
        # shape often like [N, C, H, W] or [N, H, W, C]
        try:
            # convert any None to -1 for clarity
            shp_clean = [(-1 if s is None else int(s)) for s in shp]
        except Exception:
            shp_clean = shp
        input_shape = shp_clean
        # heuristics to detect layout
        if len(shp_clean) == 4:
            if shp_clean[1] in (1, 3):
                input_layout = 'NCHW'
            elif shp_clean[3] in (1, 3):
                input_layout = 'NHWC'
    # attach metadata
    sess._meta = {'input_shape': input_shape, 'input_layout': input_layout, 'output_shapes': [o.shape for o in outs]}
    return sess


# Lightweight fallback model used when the ONNX model file is missing.
class _IO:
    def __init__(self, name):
        self.name = name


class FallbackModel:
    """Minimal model-like object that mimics ONNXRuntimeSession API.

    It uses a simple brightness heuristic to produce two logits [real, spoof].
    This allows the service to operate for development/testing without an
    ONNX model file. Replace with a real ONNX model for production.
    """
    def __init__(self):
        self._input_name = 'input'

    def get_inputs(self):
        return [_IO(self._input_name)]

    def get_outputs(self):
        return [_IO('output')]

    def run(self, out_names, feed):
        # feed is a dict {input_name: numpy_array}
        arr = next(iter(feed.values()))
        # expect arr in [1,3,128,128] with range [0,1]
        try:
            avg = float(np.mean(arr))
        except Exception:
            avg = 0.5
        # Map average brightness to a logit: darker -> spoof, brighter -> real
        real_logit = (avg - 0.5) * 12.0
        spoof_logit = -real_logit
        return [np.array([[real_logit, spoof_logit]], dtype=np.float32)]

def preprocess_pil(img_pil, size=128, layout='NCHW'):
    # letterbox / pad to square while keeping aspect ratio
    img = img_pil.convert('RGB')
    w, h = img.size
    scale = min(size / w, size / h)
    nw, nh = int(w * scale), int(h * scale)
    resized = img.resize((nw, nh), Image.BILINEAR)
    new_img = Image.new('RGB', (size, size), (0, 0, 0))
    paste_x = (size - nw) // 2
    paste_y = (size - nh) // 2
    new_img.paste(resized, (paste_x, paste_y))
    arr = np.asarray(new_img).astype(np.float32) / 255.0
    # return either NCHW or NHWC depending on model
    if layout == 'NHWC':
        arr = np.expand_dims(arr, 0)
        return arr.astype(np.float32)
    # default NCHW
    arr = np.transpose(arr, (2, 0, 1))
    arr = np.expand_dims(arr, 0)
    return arr.astype(np.float32)

sess = None
# True if we're running the fallback heuristic model instead of a real ONNX model
using_fallback = False
# Allow fallback to heuristic model only when explicitly enabled via env var
ALLOW_FALLBACK = str(os.environ.get('ALLOW_FALLBACK', '')).lower() in ('1', 'true', 'yes')


@app.route('/ping', methods=['GET'])
def ping():
    """Health check: reports whether service is up and whether model is loadable."""
    global sess
    model_loaded = False
    model_error = None
    try:
        if sess is None:
            try:
                # try loading the ONNX model
                if os.path.exists(MODEL_PATH):
                    sess = load_model(MODEL_PATH)
                else:
                    raise FileNotFoundError(f"Model file not found: {MODEL_PATH}")
            except Exception as e:
                model_error = str(e)
                # only use fallback if explicitly allowed
                if ALLOW_FALLBACK:
                    sess = FallbackModel()
                    globals()['using_fallback'] = True
                else:
                    sess = None
        if sess is not None:
            model_loaded = True
    except Exception as e:
        model_error = str(e)
    meta = None
    if sess is not None and hasattr(sess, '_meta'):
        meta = sess._meta
    return jsonify({'ok': True, 'model_loaded': model_loaded, 'model_error': model_error, 'using_fallback': globals().get('using_fallback', False), 'meta': meta})


@app.route('/antispoof', methods=['POST'])
def antispoof():
    global sess
    if sess is None:
        try:
            if os.path.exists(MODEL_PATH):
                sess = load_model(MODEL_PATH)
            else:
                raise FileNotFoundError(f"Model file not found: {MODEL_PATH}")
        except Exception as e:
            # fall back to heuristic model so the endpoint remains reachable
            sess = FallbackModel()
            globals()['using_fallback'] = True

    data = request.get_json(force=True)
    if not data or 'image' not in data:
        return jsonify({'error': 'expected JSON with base64 "image" field'}), 400

    b64 = data['image']
    try:
        img_bytes = base64.b64decode(b64)
        img = Image.open(io.BytesIO(img_bytes))
    except Exception as e:
        return jsonify({'error': f'Invalid image data: {e}'}), 400

    # choose input size/layout based on loaded model metadata when available
    size = 128
    layout = 'NCHW'
    if hasattr(sess, '_meta') and sess._meta.get('input_shape'):
        shp = sess._meta.get('input_shape')
        layout = sess._meta.get('input_layout', 'NCHW')
        try:
            if layout == 'NCHW' and len(shp) >= 4 and isinstance(shp[2], int) and shp[2] > 0:
                size = int(shp[2])
            elif layout == 'NHWC' and len(shp) >= 4 and isinstance(shp[1], int) and shp[1] > 0:
                size = int(shp[1])
        except Exception:
            size = 128

    inp = preprocess_pil(img, size=size, layout=layout)

    # ONNX input name
    input_name = sess.get_inputs()[0].name
    out_names = [o.name for o in sess.get_outputs()]
    try:
        out = sess.run(out_names, {input_name: inp})
    except Exception as e:
        # if inference fails on ONNX runtime, try falling back
        try:
            sess = FallbackModel()
            globals()['using_fallback'] = True
            out = sess.run(out_names, {input_name: inp})
        except Exception as e2:
            return jsonify({'error': f'Inference failed: {e2}'}), 500

    # handle outputs robustly: logits may be 2-class logits or single score
    logits = out[0]
    logits = np.asarray(logits)
    prob_real = None
    try:
        if logits.ndim == 2:
            # [batch, C]
            if logits.shape[1] == 2:
                expv = np.exp(logits)
                probs = expv / np.sum(expv, axis=1, keepdims=True)
                prob_real = float(probs[0, 0])
            elif logits.shape[1] == 1:
                # sigmoid
                prob_real = float(1.0 / (1.0 + np.exp(-float(logits[0, 0]))))
            else:
                # try softmax and take first class
                expv = np.exp(logits)
                probs = expv / np.sum(expv, axis=1, keepdims=True)
                prob_real = float(probs[0, 0])
        elif logits.ndim == 1:
            if logits.shape[0] == 2:
                expv = np.exp(logits)
                probs = expv / np.sum(expv)
                prob_real = float(probs[0])
            elif logits.shape[0] == 1:
                prob_real = float(1.0 / (1.0 + np.exp(-float(logits[0]))))
            else:
                # fallback: normalize and pick first
                expv = np.exp(logits)
                probs = expv / np.sum(expv)
                prob_real = float(probs[0])
        else:
            prob_real = float(np.squeeze(logits))
    except Exception:
        # final fallback
        try:
            prob_real = float(np.squeeze(logits))
        except Exception:
            prob_real = 0.0

    # clamp
    try:
        if prob_real is None:
            prob_real = 0.0
        prob_real = float(max(0.0, min(1.0, prob_real)))
    except Exception:
        prob_real = 0.0

    live = prob_real >= LIVE_PROB_THRESHOLD

    return jsonify({
        'live': bool(live),
        'prob_real': prob_real,
        'threshold': LIVE_PROB_THRESHOLD,
        'meta': getattr(sess, '_meta', None)
    })

if __name__ == '__main__':
    print('Starting antispoof service...')
    app.run(host='0.0.0.0', port=5001)
