#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const FILE_NODE_TYPES = new Set([
  'file', 'config', 'document', 'service', 'pipeline', 'table', 'schema', 'resource', 'endpoint',
]);

const graphPath = process.argv[2];
const outputPath = process.argv[3];

if (!graphPath || !outputPath) {
  console.error('Usage: node extract-arch-input.js <assembled-graph.json> <output.json>');
  process.exit(1);
}

const graph = JSON.parse(fs.readFileSync(graphPath, 'utf8'));
const fileNodes = graph.nodes
  .filter((n) => FILE_NODE_TYPES.has(n.type))
  .map(({ id, type, name, filePath, summary, tags }) => ({
    id,
    type,
    name,
    filePath,
    summary: summary || '',
    tags: tags || [],
  }));

const fileNodeIds = new Set(fileNodes.map((n) => n.id));

const importEdges = graph.edges.filter(
  (e) => e.type === 'imports' && fileNodeIds.has(e.source) && fileNodeIds.has(e.target),
);

const allEdges = graph.edges.filter(
  (e) => fileNodeIds.has(e.source) && fileNodeIds.has(e.target),
);

const output = { fileNodes, importEdges, allEdges };
fs.writeFileSync(outputPath, JSON.stringify(output, null, 2));
console.error(`Extracted ${fileNodes.length} file nodes, ${importEdges.length} import edges, ${allEdges.length} all edges`);
