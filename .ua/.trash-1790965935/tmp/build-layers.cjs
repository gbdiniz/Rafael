#!/usr/bin/env node
'use strict';

const fs = require('fs');

const input = JSON.parse(fs.readFileSync(process.argv[2], 'utf8'));
const results = JSON.parse(fs.readFileSync(process.argv[3], 'utf8'));
const outputPath = process.argv[4];

const { fileNodes } = input;
const assignments = {};

function assign(ids, layer) {
  for (const id of ids) {
    if (assignments[id]) {
      throw new Error(`Duplicate assignment: ${id} was ${assignments[id]}, now ${layer}`);
    }
    assignments[id] = layer;
  }
}

function getPath(node) {
  return node.filePath || node.id.replace(/^[^:]+:/, '');
}

function filterNodes(predicate) {
  return fileNodes.filter(predicate).map((n) => n.id);
}

// API Layer: routes, HTTP controllers, form requests, public entry
assign(filterNodes((n) => {
  const p = getPath(n);
  return /^routes\//.test(p) ||
    /^app\/Http\/Controllers\//.test(p) ||
    /^app\/Http\/Requests\//.test(p) ||
    p === 'public/index.php';
}), 'layer:api');

// Service Layer: actions, services, jobs, contracts, exceptions, providers, domain enums/value objects
assign(filterNodes((n) => {
  const p = getPath(n);
  return /^app\/Actions\//.test(p) ||
    /^app\/Services\//.test(p) ||
    /^app\/Jobs\//.test(p) ||
    /^app\/Contracts\//.test(p) ||
    /^app\/Exceptions\//.test(p) ||
    /^app\/Providers\//.test(p) ||
    /^app\/Records\//.test(p) ||
    p === 'app/RecordKind.php' ||
    p === 'app/VoiceTurnStatus.php';
}), 'layer:service');

// Data Layer: Eloquent models, migrations, factories, seeders, table schema nodes
assign(filterNodes((n) => {
  const p = getPath(n);
  return /^app\/Models\//.test(p) ||
    /^database\//.test(p) ||
    n.type === 'table';
}), 'layer:data');

// UI Layer: Blade views, Livewire pages, CSS/JS assets, legacy static HTML
assign(filterNodes((n) => {
  const p = getPath(n);
  return /^resources\//.test(p) || p === 'public_old/index.html';
}), 'layer:ui');

// Test Layer
assign(results.directoryGroups.tests || [], 'layer:test');

// Configuration Layer: Laravel config, bootstrap, build tooling, i18n, public server config, IDE/tool configs
assign(filterNodes((n) => {
  const p = getPath(n);
  if (assignments[n.id]) return false;
  return /^config\//.test(p) ||
    /^bootstrap\//.test(p) ||
    /^lang\//.test(p) ||
    p === 'artisan' ||
    p === 'vite.config.js' ||
    p === 'public/.htaccess' ||
    p === 'public/robots.txt' ||
    p === '.gitattributes' ||
    p === '.npmrc' ||
    /^\.cursor\//.test(p) ||
    /^\.ua\//.test(p) ||
    (n.type === 'config' && (
      p === '.env.example' ||
      p === '.mcp.json' ||
      p === 'boost.json' ||
      p === 'composer.json' ||
      p === 'package.json' ||
      p === 'phpunit.xml' ||
      p === 'skills-lock.json'
    ));
}), 'layer:config');

// Documentation Layer: project docs, specs, prompts
assign(filterNodes((n) => {
  if (assignments[n.id]) return false;
  const p = getPath(n);
  return n.type === 'document' && (
    /^docs\//.test(p) ||
    ['AGENTS.md', 'CLAUDE.md', 'DESIGN.md', 'PRODUCT.md', 'README.md'].includes(p)
  );
}), 'layer:documentation');

// Developer Tooling Layer: agent skills, design review tooling, duplicated skill configs
assign(filterNodes((n) => !assignments[n.id]), 'layer:tooling');

// Verify all assigned
const unassigned = fileNodes.filter((n) => !assignments[n.id]);
if (unassigned.length > 0) {
  console.error('Unassigned nodes:', unassigned.map((n) => n.id));
  process.exit(1);
}

const layerDefs = {
  'layer:api': {
    name: 'API Layer',
    description: 'HTTP routes, controllers, form requests, and the public front controller that expose Rafael\'s voice conversation and record endpoints.',
  },
  'layer:service': {
    name: 'Service Layer',
    description: 'Business logic for Portuguese voice conversations, transcription orchestration, appointment briefings, and voice-turn storage via Actions, Jobs, and Services.',
  },
  'layer:data': {
    name: 'Data Layer',
    description: 'Eloquent models (User, Record, VoiceTurn), migrations, factories, seeders, and table definitions for appointments and voice turns.',
  },
  'layer:ui': {
    name: 'Presentation Layer',
    description: 'Livewire conversation page, Blade components, Tailwind CSS, and JavaScript for the voice talk-control UI.',
  },
  'layer:test': {
    name: 'Test Layer',
    description: 'Pest feature and unit tests covering auth, conversation flows, voice upload, transcription jobs, and model behavior.',
  },
  'layer:config': {
    name: 'Configuration Layer',
    description: 'Laravel bootstrap, environment config (including rafael.php), Vite/build settings, PHPUnit, and Portuguese auth translations.',
  },
  'layer:documentation': {
    name: 'Documentation Layer',
    description: 'Project README, product/design specs, database schema docs, user stories, and phase planning in docs/.',
  },
  'layer:tooling': {
    name: 'Developer Tooling Layer',
    description: 'Agent skill definitions, Impeccable design-review configs, and Cursor/Claude assistant rules supporting development workflows.',
  },
};

const layerOrder = [
  'layer:api',
  'layer:service',
  'layer:data',
  'layer:ui',
  'layer:test',
  'layer:config',
  'layer:documentation',
  'layer:tooling',
];

const layers = layerOrder.map((id) => ({
  id,
  name: layerDefs[id].name,
  description: layerDefs[id].description,
  nodeIds: fileNodes.filter((n) => assignments[n.id] === id).map((n) => n.id).sort(),
}));

const total = layers.reduce((s, l) => s + l.nodeIds.length, 0);
if (total !== fileNodes.length) {
  console.error(`Count mismatch: ${total} vs ${fileNodes.length}`);
  process.exit(1);
}

for (const layer of layers) {
  if (layer.nodeIds.length === 0) {
    console.error(`Empty layer: ${layer.id}`);
    process.exit(1);
  }
}

fs.writeFileSync(outputPath, JSON.stringify(layers, null, 2));

const summary = layers.map((l) => `${l.name}: ${l.nodeIds.length}`).join(', ');
console.log(`${layers.length} layers, ${total} files — ${summary}`);
