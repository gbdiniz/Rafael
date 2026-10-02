#!/usr/bin/env node
'use strict';

const fs = require('fs');

const ENTRY_FILENAME_PATTERNS = [
  'index.ts', 'index.js', 'main.ts', 'main.js', 'app.ts', 'app.js',
  'server.ts', 'server.js', 'mod.rs', 'main.go', 'main.py', 'main.rs',
  'manage.py', 'app.py', 'wsgi.py', 'asgi.py', 'run.py', '__main__.py',
  'Application.java', 'Main.java', 'Program.cs', 'config.ru', 'index.php',
  'App.swift', 'Application.kt', 'main.cpp', 'main.c',
];

function fatal(message) {
  process.stderr.write(String(message) + '\n');
  process.exit(1);
}

function readJson(path) {
  try {
    return JSON.parse(fs.readFileSync(path, 'utf8'));
  } catch (err) {
    fatal(`Failed to read ${path}: ${err.message}`);
  }
}

function getFilePath(node) {
  return node.filePath || node.name || '';
}

function isEntryFilename(name) {
  return ENTRY_FILENAME_PATTERNS.some((pattern) => name === pattern || name.endsWith('/' + pattern));
}

function depthFromRoot(filePath) {
  if (!filePath) {
    return 99;
  }
  return filePath.split('/').filter(Boolean).length;
}

function percentileThreshold(values, percentile) {
  if (values.length === 0) {
    return 0;
  }
  const sorted = [...values].sort((a, b) => a - b);
  const index = Math.floor((percentile / 100) * (sorted.length - 1));
  return sorted[index];
}

function buildFanCounts(nodes, edges) {
  const fanIn = new Map(nodes.map((n) => [n.id, 0]));
  const fanOut = new Map(nodes.map((n) => [n.id, 0]));

  for (const edge of edges) {
    if (fanIn.has(edge.target)) {
      fanIn.set(edge.target, fanIn.get(edge.target) + 1);
    }
    if (fanOut.has(edge.source)) {
      fanOut.set(edge.source, fanOut.get(edge.source) + 1);
    }
  }

  return { fanIn, fanOut };
}

function scoreEntryPoint(node, fanIn, fanOut, fanOutValues) {
  let score = 0;
  const filePath = getFilePath(node);
  const name = node.name || '';
  const type = node.type || '';

  if (type === 'document') {
    if (name === 'README.md' && (!filePath || filePath === 'README.md')) {
      score += 5;
    } else if (name.endsWith('.md') && depthFromRoot(filePath) <= 1) {
      score += 2;
    }
    return score;
  }

  if (type !== 'file') {
    return score;
  }

  if (isEntryFilename(name)) {
    score += 3;
  }

  if (depthFromRoot(filePath) <= 2) {
    score += 1;
  }

  const nodeFanOut = fanOut.get(node.id) || 0;
  const nodeFanIn = fanIn.get(node.id) || 0;
  const top10FanOut = percentileThreshold(fanOutValues, 90);

  if (nodeFanOut >= top10FanOut && top10FanOut > 0) {
    score += 1;
  }

  const bottom25FanIn = percentileThreshold([...fanIn.values()], 25);
  if (nodeFanIn <= bottom25FanIn) {
    score += 1;
  }

  return score;
}

function bfsFromEntry(startNodeId, edges) {
  const forwardEdges = new Map();
  for (const edge of edges) {
    if (edge.type === 'imports' || edge.type === 'calls') {
      if (!forwardEdges.has(edge.source)) {
        forwardEdges.set(edge.source, []);
      }
      forwardEdges.get(edge.source).push(edge.target);
    }
  }

  const order = [];
  const depthMap = {};
  const byDepth = {};
  const visited = new Set();
  const queue = [{ id: startNodeId, depth: 0 }];

  while (queue.length > 0) {
    const { id, depth } = queue.shift();
    if (visited.has(id)) {
      continue;
    }
    visited.add(id);
    order.push(id);
    depthMap[id] = depth;
    const depthKey = String(depth);
    if (!byDepth[depthKey]) {
      byDepth[depthKey] = [];
    }
    byDepth[depthKey].push(id);

    const neighbors = forwardEdges.get(id) || [];
    for (const neighbor of neighbors) {
      if (!visited.has(neighbor)) {
        queue.push({ id: neighbor, depth: depth + 1 });
      }
    }
  }

  return { startNode: startNodeId, order, depthMap, byDepth };
}

function findClusters(nodes, edges) {
  const nodeIds = new Set(nodes.map((n) => n.id));
  const adjacency = new Map();

  function addEdge(a, b) {
    if (!nodeIds.has(a) || !nodeIds.has(b) || a === b) {
      return;
    }
    if (!adjacency.has(a)) {
      adjacency.set(a, new Set());
    }
    adjacency.get(a).add(b);
  }

  for (const edge of edges) {
    addEdge(edge.source, edge.target);
    addEdge(edge.target, edge.source);
  }

  const bidirectionalPairs = [];
  for (const edge of edges) {
    const reverse = edges.some(
      (other) => other.source === edge.target && other.target === edge.source
        && (other.type === edge.type || ['imports', 'calls'].includes(other.type)),
    );
    if (reverse) {
      bidirectionalPairs.push([edge.source, edge.target]);
    }
  }

  const clusters = [];
  const used = new Set();

  function expandCluster(seed) {
    const cluster = new Set(seed);
    let changed = true;

    while (changed) {
      changed = false;
      for (const nodeId of [...cluster]) {
        const neighbors = adjacency.get(nodeId) || new Set();
        for (const neighbor of neighbors) {
          let connections = 0;
          for (const member of cluster) {
            if (adjacency.get(member)?.has(neighbor)) {
              connections += 1;
            }
          }
          if (connections >= 2 && !cluster.has(neighbor)) {
            cluster.add(neighbor);
            changed = true;
          }
        }
      }
    }

    return [...cluster];
  }

  for (const pair of bidirectionalPairs) {
    const key = pair.slice().sort().join('|');
    if (used.has(key)) {
      continue;
    }
    used.add(key);
    const clusterNodes = expandCluster(pair);
    if (clusterNodes.length >= 2 && clusterNodes.length <= 5) {
      let edgeCount = 0;
      const clusterSet = new Set(clusterNodes);
      for (const edge of edges) {
        if (clusterSet.has(edge.source) && clusterSet.has(edge.target)) {
          edgeCount += 1;
        }
      }
      clusters.push({ nodes: clusterNodes.sort(), edgeCount });
    }
  }

  clusters.sort((a, b) => b.edgeCount - a.edgeCount || b.nodes.length - a.nodes.length);
  return clusters.slice(0, 10);
}

function categorizeNonCode(nodes) {
  const result = {
    documentation: [],
    infrastructure: [],
    data: [],
    config: [],
  };

  for (const node of nodes) {
    const entry = {
      id: node.id,
      name: node.name,
      type: node.type,
      summary: node.summary || '',
    };

    switch (node.type) {
      case 'document':
        result.documentation.push(entry);
        break;
      case 'service':
      case 'pipeline':
      case 'resource':
        result.infrastructure.push(entry);
        break;
      case 'table':
      case 'schema':
      case 'endpoint':
        result.data.push(entry);
        break;
      case 'config':
        result.config.push(entry);
        break;
      default:
        break;
    }
  }

  return result;
}

function main() {
  const inputPath = process.argv[2];
  const outputPath = process.argv[3];

  if (!inputPath || !outputPath) {
    fatal('Usage: node ua-tour-analyze.js <input.json> <output.json>');
  }

  const input = readJson(inputPath);
  const nodes = input.nodes || [];
  const edges = input.edges || [];
  const layers = input.layers || [];

  const nodeById = new Map(nodes.map((n) => [n.id, n]));
  const { fanIn, fanOut } = buildFanCounts(nodes, edges);
  const fanOutValues = [...fanOut.values()];

  const fanInRanking = [...fanIn.entries()]
    .map(([id, count]) => ({ id, fanIn: count, name: nodeById.get(id)?.name || id }))
    .sort((a, b) => b.fanIn - a.fanIn || a.id.localeCompare(b.id))
    .slice(0, 20);

  const fanOutRanking = [...fanOut.entries()]
    .map(([id, count]) => ({ id, fanOut: count, name: nodeById.get(id)?.name || id }))
    .sort((a, b) => b.fanOut - a.fanOut || a.id.localeCompare(b.id))
    .slice(0, 20);

  const entryPointCandidates = nodes
    .map((node) => ({
      id: node.id,
      score: scoreEntryPoint(node, fanIn, fanOut, fanOutValues),
      name: node.name,
      summary: node.summary || '',
      type: node.type,
    }))
    .filter((candidate) => candidate.score > 0)
    .sort((a, b) => b.score - a.score || a.id.localeCompare(b.id))
    .slice(0, 5);

  const codeEntry = entryPointCandidates.find((candidate) => {
    const node = nodeById.get(candidate.id);
    return node && node.type === 'file';
  });

  const bfsTraversal = codeEntry
    ? bfsFromEntry(codeEntry.id, edges)
    : { startNode: null, order: [], depthMap: {}, byDepth: {} };

  const nodeSummaryIndex = Object.fromEntries(
    nodes.map((node) => [node.id, {
      name: node.name,
      type: node.type,
      summary: node.summary || '',
    }]),
  );

  const output = {
    scriptCompleted: true,
    entryPointCandidates,
    fanInRanking,
    fanOutRanking,
    bfsTraversal,
    nonCodeFiles: categorizeNonCode(nodes),
    clusters: findClusters(nodes, edges),
    layers: {
      count: layers.length,
      list: layers.map((layer) => ({
        id: layer.id,
        name: layer.name,
        description: layer.description,
      })),
    },
    nodeSummaryIndex,
    totalNodes: nodes.length,
    totalEdges: edges.length,
  };

  fs.writeFileSync(outputPath, JSON.stringify(output, null, 2));
}

main();
