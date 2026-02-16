// attendance_sync.js
// Client-side template sync + attendance_event poster with offline queue/retry.
// - Templates stored in localStorage under 'attendance_templates'
// - Outbound attendance events are queued in localStorage under 'attendance_event_queue'
// Usage:
//  - Call `AttendanceSync.start()` on page load.
//  - Use `AttendanceSync.recordAttendanceFor(userId)` to enqueue and attempt to send.

const API_BASE = './pc_per_office.php';
const SYNC_INTERVAL_MS = 15 * 1000; // 15 seconds (was 5 minutes)
const QUEUE_KEY = 'attendance_event_queue';
const TEMPLATES_KEY = 'attendance_templates';
const FLUSH_INTERVAL_MS = 30 * 1000; // try flush every 30s

async function fetchTemplates() {
  try {
    const res = await fetch(`${API_BASE}?action=get_templates`, { cache: 'no-store' });
    if (!res.ok) throw new Error('Network response not ok');
    const data = await res.json();
    if (data && Array.isArray(data.templates)) {
      localStorage.setItem(TEMPLATES_KEY, JSON.stringify({ templates: data.templates, threshold: data.threshold, ts: Date.now() }));
      return data;
    }
    throw new Error('Invalid templates response');
  } catch (err) {
    console.warn('fetchTemplates failed', err);
    return null;
  }
}

function getLocalTemplates() {
  try {
    const raw = localStorage.getItem(TEMPLATES_KEY);
    return raw ? JSON.parse(raw) : null;
  } catch (_) { return null; }
}

function _readQueue() {
  try { return JSON.parse(localStorage.getItem(QUEUE_KEY) || '[]'); } catch(e){ return []; }
}
function _writeQueue(q) { try { localStorage.setItem(QUEUE_KEY, JSON.stringify(q)); } catch(e){ console.warn('writeQueue failed', e); } }

function enqueueEvent(evt) {
  const q = _readQueue();
  // attach kiosk API key if available so queued events include auth
  try { if (window && window.KIOSK_API_KEY) evt.api_key = window.KIOSK_API_KEY; } catch(e){}
  q.push({ id: 'e' + Date.now() + '-' + Math.random().toString(36).slice(2,8), ts: Date.now(), attempts: 0, payload: evt });
  _writeQueue(q);
}

async function _sendSingle(item) {
  const body = new URLSearchParams();
  body.append('action', 'attendance_event');
  // ensure api_key included if available on runtime
  try { if (window && window.KIOSK_API_KEY && !item.payload.api_key) item.payload.api_key = window.KIOSK_API_KEY; } catch(e){}
  for (const k in item.payload) if (Object.prototype.hasOwnProperty.call(item.payload, k)) body.append(k, item.payload[k]);
  try {
    const res = await fetch(API_BASE, { method: 'POST', body });
    if (!res.ok) {
      const txt = await res.text().catch(()=>null);
      throw new Error('HTTP ' + res.status + ' ' + (txt||''));
    }
    const j = await res.json().catch(()=>null);
    if (!j || (j.success !== true && j.ok !== true)) throw new Error('server rejected');
    return { ok: true, resp: j };
  } catch (err) {
    return { ok: false, error: err.message || String(err) };
  }
}

async function flushQueueOnce(maxPerRun = 5) {
  const q = _readQueue();
  if (!q || q.length === 0) return { sent:0, left:0 };
  const out = [];
  let sent = 0;
  for (let i = 0; i < q.length && sent < maxPerRun; i++) {
    const item = q[i];
    // simple backoff rule: if attempts > 0, wait attempts*5s before retry
    const backoffMs = (item.attempts || 0) * 5000;
    if (Date.now() - (item.lastTryAt || 0) < backoffMs) { out.push(item); continue; }
    item.lastTryAt = Date.now();
    item.attempts = (item.attempts || 0) + 1;
    const r = await _sendSingle(item);
    if (r.ok) {
      sent++;
      continue; // drop from queue
    } else {
      // keep item, but if attempts exceed threshold, mark failed and drop
      if (item.attempts >= 6) {
        console.warn('Dropping attendance event after repeated failures', item, r.error);
        continue;
      }
      out.push(item);
    }
  }
  // append remaining unprocessed items
  for (let j = sent + (q.length - out.length - sent); j < q.length; j++) {
    if (q[j]) out.push(q[j]);
  }
  _writeQueue(out);
  return { sent, left: out.length };
}

let _flushTimer = null;
function startQueueProcessing(intervalMs = FLUSH_INTERVAL_MS) {
  if (_flushTimer) return _flushTimer;
  // flush immediately then periodically
  flushQueueOnce();
  _flushTimer = setInterval(()=>{ if (navigator.onLine) flushQueueOnce(); }, intervalMs);
  // try on reconnect
  window.addEventListener('online', () => { flushQueueOnce(); });
  return _flushTimer;
}

async function postAttendanceEvent(payload) {
  // Try immediate post if online, otherwise enqueue
  try { if (window && window.KIOSK_API_KEY && !payload.api_key) payload.api_key = window.KIOSK_API_KEY; } catch(e){}
  if (navigator.onLine) {
    const item = { payload };
    const r = await _sendSingle({ payload });
    if (r.ok) return r.resp;
    // on failure, enqueue for retry
    enqueueEvent(payload);
    return { success: false, queued: true, error: r.error };
  } else {
    enqueueEvent(payload);
    return { success: false, queued: true, error: 'offline' };
  }
}

// Periodic sync starter
function startTemplateSync(intervalMs = SYNC_INTERVAL_MS) {
  const local = getLocalTemplates();
  if (!local) fetchTemplates();
  return setInterval(fetchTemplates, intervalMs);
}

// Example helper to post an event for a matched user (uses queue)
async function recordAttendanceFor(userId, options = {}) {
  const d = new Date();
  const payload = {
    user_id: userId.toString(),
    client_local_date: d.toISOString().slice(0,10),
    client_local_time: d.toTimeString().slice(0,8),
    client_ts: d.toISOString(),
    confirm: options.confirm ? '1' : '0'
  };
  try { if (window && window.KIOSK_API_KEY) payload.api_key = window.KIOSK_API_KEY; } catch(e){}
  return await postAttendanceEvent(payload);
}

// Start background workers
function start(options = {}) {
  startTemplateSync(options.templateIntervalMs || SYNC_INTERVAL_MS);
  startQueueProcessing(options.flushIntervalMs || FLUSH_INTERVAL_MS);
}

// Expose globally
window.AttendanceSync = {
  fetchTemplates,
  getLocalTemplates,
  startTemplateSync,
  startQueueProcessing,
  postAttendanceEvent,
  recordAttendanceFor,
  start
};
