/* Menguji spoofing _method untuk update multipart (dipakai form unggah gambar unit). */
const BASE = 'http://127.0.0.1:8080/api';
const { readFileSync } = await import('node:fs');

const login = async () => {
  const r = await fetch(BASE + '/login', {
    method: 'POST', headers: { 'Content-Type': 'application/json', Accept: 'application/json' },
    body: JSON.stringify({ email: 'manager@luminpark.com', password: 'password123' }),
  });
  return (await r.json()).access_token;
};

const token = await login();
const form = new FormData();
form.append('_method', 'PUT');
form.append('unit_type', 'Modern Tropical 60');
form.append('land_size', '72');
form.append('building_size', '60');
form.append('price', '755000000');
form.append('image', new Blob([readFileSync(new URL('./tmp-unit.png', import.meta.url))], { type: 'image/png' }), 'tmp-unit.png');

const res = await fetch(BASE + '/units/3', {
  method: 'POST', headers: { Accept: 'application/json', Authorization: 'Bearer ' + token }, body: form,
});
const text = await res.text();
console.log('status', res.status);
console.log(text.slice(0, 400));
