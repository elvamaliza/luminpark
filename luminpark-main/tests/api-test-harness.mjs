const BASE = 'http://127.0.0.1:8080/api';

const results = [];

function trimBody(text) {
  if (!text) return '(empty)';
  let t = String(text).replace(/\s+/g, ' ').trim();
  // Strip Laravel debug HTML noise
  if (t.startsWith('<')) {
    const m = t.match(/"message"\s*:\s*"([^"]{0,300})/);
    t = m ? m[1] : t.slice(0, 200);
  }
  return t.length > 280 ? t.slice(0, 280) + ' ...[truncated]' : t;
}

async function call(tc, token) {
  const headers = { Accept: 'application/json' };
  if (tc.contentType !== 'none') headers['Content-Type'] = 'application/json';
  if (token) headers['Authorization'] = 'Bearer ' + token;

  let status = 0, body = '', err = null;
  try {
    const res = await fetch(BASE + tc.path, {
      method: tc.method,
      headers,
      body: tc.method === 'GET' || tc.method === 'DELETE' ? undefined
        : (typeof tc.body === 'string' ? tc.body : JSON.stringify(tc.body ?? {})),
    });
    status = res.status;
    body = await res.text();
  } catch (e) {
    err = e.message;
  }
  results.push({
    id: tc.id, nama: tc.nama, skenario: tc.skenario, deskripsi: tc.deskripsi,
    langkah: tc.langkah, dataUji: tc.dataUji, ekspektasi: tc.ekspektasi,
    method: tc.method, path: tc.path,
    actualStatus: status || 'ERR', actualBody: err ? 'NETWORK ERROR: ' + err : trimBody(body),
  });
  const ok = status === tc.expectStatus;
  console.log(`${tc.id} [${ok ? 'PASS' : 'FAIL'}] ${tc.method} ${tc.path} -> ${status} (exp ${tc.expectStatus})`);
  return { status, body };
}

export { call, results, BASE };
