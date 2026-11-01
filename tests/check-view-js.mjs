/**
 * Memeriksa sintaks blok <script> inline dalam berkas Blade.
 * Pemakaian: node tests/check-view-js.mjs resources/views/app/data-unit.blade.php [berkas lain...]
 */
import { readFileSync, writeFileSync } from 'node:fs';
import { execFileSync } from 'node:child_process';
import { tmpdir } from 'node:os';

let problems = 0;

for (const file of process.argv.slice(2)) {
  const blade = readFileSync(file, 'utf8');
  const blocks = [...blade.matchAll(/<script(?![^>]*\bsrc=)[^>]*>([\s\S]*?)<\/script>/gi)];
  if (!blocks.length) { console.log(`${file}: tidak ada script inline`); continue; }

  blocks.forEach((match, index) => {
    const js = match[1];
    const label = `${file} blok ${index + 1}`;
    const bladeLeftover = js.match(/\{\{|\}\}|@yield|@can|@if|@foreach/);
    if (bladeLeftover) {
      console.log(`${label}: ditemukan "${bladeLeftover[0]}" di dalam JS — gunakan LP.esc()/data atribut, bukan sintaks Blade.`);
      problems++;
      return;
    }
    const tmp = `${tmpdir()}\\blade-check-${Date.now()}-${index}.mjs`;
    writeFileSync(tmp, js);
    try {
      execFileSync(process.execPath, ['--check', tmp], { stdio: 'pipe' });
      console.log(`${label}: OK`);
    } catch (error) {
      console.log(`${label}: ERROR\n${(error.stderr || '').toString() || error.message}`);
      problems++;
    }
  });
}

process.exit(problems ? 1 : 0);
