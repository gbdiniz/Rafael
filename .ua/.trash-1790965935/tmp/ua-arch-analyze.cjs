#!/usr/bin/env node
'use strict';

const fs = require('fs');
const path = require('path');

const PATTERN_MAP = {
  routes: 'api',
  api: 'api',
  controllers: 'api',
  endpoints: 'api',
  handlers: 'api',
  controller: 'api',
  routers: 'api',
  blueprints: 'api',
  serializers: 'api',
  services: 'service',
  core: 'service',
  lib: 'service',
  domain: 'service',
  logic: 'service',
  actions: 'service',
  jobs: 'service',
  mailers: 'service',
  channels: 'service',
  signals: 'service',
  internal: 'service',
  composables: 'service',
  contracts: 'types',
  models: 'data',
  db: 'data',
  data: 'data',
  persistence: 'data',
  repository: 'data',
  entities: 'data',
  migrations: 'data',
  factories: 'data',
  seeders: 'data',
  database: 'data',
  components: 'ui',
  views: 'ui',
  pages: 'ui',
  ui: 'ui',
  layouts: 'ui',
  screens: 'ui',
  resources: 'ui',
  css: 'ui',
  js: 'ui',
  middleware: 'middleware',
  plugins: 'middleware',
  interceptors: 'middleware',
  guards: 'middleware',
  utils: 'utility',
  helpers: 'utility',
  common: 'utility',
  shared: 'utility',
  tools: 'utility',
  support: 'utility',
  providers: 'config',
  config: 'config',
  constants: 'config',
  env: 'config',
  settings: 'config',
  management: 'config',
  commands: 'config',
  __tests__: 'test',
  test: 'test',
  tests: 'test',
  spec: 'test',
  specs: 'test',
  types: 'types',
  interfaces: 'types',
  schemas: 'types',
  contracts: 'types',
  dtos: 'types',
  dto: 'types',
  request: 'types',
  response: 'types',
  hooks: 'hooks',
  store: 'state',
  state: 'state',
  reducers: 'state',
  actions: 'state',
  slices: 'state',
  assets: 'assets',
  static: 'assets',
  public: 'assets',
  docs: 'documentation',
  documentation: 'documentation',
  wiki: 'documentation',
  deploy: 'infrastructure',
  deployment: 'infrastructure',
  infra: 'infrastructure',
  infrastructure: 'infrastructure',
  '.github': 'ci-cd',
  '.gitlab': 'ci-cd',
  '.circleci': 'ci-cd',
  k8s: 'infrastructure',
  kubernetes: 'infrastructure',
  helm: 'infrastructure',
  charts: 'infrastructure',
  terraform: 'infrastructure',
  tf: 'infrastructure',
  docker: 'infrastructure',
  sql: 'data',
  schema: 'data',
  bin: 'entry',
  cmd: 'entry',
  livewire: 'ui',
  http: 'api',
  console: 'config',
  routes: 'api',
};

const FILE_PATTERNS = [
  { test: (fp) => /\.(test|spec)\./i.test(fp) || /test_.*\.py$/i.test(fp) || /_test\.go$/i.test(fp) || /Test\.java$/i.test(fp) || /_spec\.rb$/i.test(fp) || /Test\.php$/i.test(fp) || /Tests\.cs$/i.test(fp), label: 'test' },
  { test: (fp) => /\.d\.ts$/i.test(fp), label: 'types' },
  { test: (fp) => /(^|\/)index\.(ts|js)$/i.test(fp), label: 'entry' },
  { test: (fp) => /(^|\/)__init__\.py$/i.test(fp), label: 'entry' },
  { test: (fp) => /(^|\/)manage\.py$/i.test(fp), label: 'entry' },
  { test: (fp) => /(^|\/)wsgi\.py$/i.test(fp) || /(^|\/)asgi\.py$/i.test(fp), label: 'config' },
  { test: (fp) => /(^|\/)main\.go$/i.test(fp) && /cmd\//i.test(fp), label: 'entry' },
  { test: (fp) => /(^|\/)main\.rs$/i.test(fp) || /(^|\/)lib\.rs$/i.test(fp), label: 'entry' },
  { test: (fp) => /Application\.java$/i.test(fp) || /Program\.cs$/i.test(fp), label: 'entry' },
  { test: (fp) => /(^|\/)config\.ru$/i.test(fp), label: 'entry' },
  { test: (fp) => /(^|\/)Cargo\.toml$/i.test(fp) || /(^|\/)go\.mod$/i.test(fp) || /(^|\/)Gemfile$/i.test(fp) || /(^|\/)pom\.xml$/i.test(fp) || /(^|\/)build\.gradle$/i.test(fp) || /(^|\/)composer\.json$/i.test(fp), label: 'config' },
  { test: (fp) => /(^|\/)Dockerfile$/i.test(fp) || /docker-compose/i.test(fp), label: 'infrastructure' },
  { test: (fp) => /\.tf$/i.test(fp) || /\.tfvars$/i.test(fp), label: 'infrastructure' },
  { test: (fp) => /\.github\/workflows\//i.test(fp) || /(^|\/)gitlab-ci\.yml$/i.test(fp) || /(^|\/)Jenkinsfile$/i.test(fp), label: 'ci-cd' },
  { test: (fp) => /\.sql$/i.test(fp), label: 'data' },
  { test: (fp) => /\.(graphql|gql|proto)$/i.test(fp), label: 'types' },
  { test: (fp) => /\.(md|rst)$/i.test(fp), label: 'documentation' },
  { test: (fp) => /(^|\/)Makefile$/i.test(fp), label: 'infrastructure' },
  { test: (fp) => /(^|\/)vite\.config\./i.test(fp) || /(^|\/)tailwind\.config\./i.test(fp) || /(^|\/)package\.json$/i.test(fp) || /(^|\/)phpunit\.xml$/i.test(fp) || /(^|\/)pest\.php$/i.test(fp), label: 'config' },
  { test: (fp) => /(^|\/)artisan$/i.test(fp) || /(^|\/)bootstrap\//i.test(fp), label: 'config' },
  { test: (fp) => /(^|\/)public\/index\.php$/i.test(fp), label: 'entry' },
];

function getFilePath(node) {
  return node.filePath || node.id.replace(/^[^:]+:/, '');
}

function computeCommonPrefix(paths) {
  if (paths.length === 0) return '';
  let prefix = paths[0];
  for (let i = 1; i < paths.length; i++) {
    const p = paths[i];
    while (prefix && !p.startsWith(prefix)) {
      prefix = prefix.slice(0, -1);
    }
    if (!prefix) break;
  }
  if (prefix && !prefix.endsWith('/')) {
    const lastSlash = prefix.lastIndexOf('/');
    prefix = lastSlash >= 0 ? prefix.slice(0, lastSlash + 1) : '';
  }
  return prefix;
}

function getDirectoryGroup(filePath, commonPrefix) {
  let relative = filePath;
  if (commonPrefix && filePath.startsWith(commonPrefix)) {
    relative = filePath.slice(commonPrefix.length);
  }
  const parts = relative.split('/').filter(Boolean);
  if (parts.length <= 1) {
    const ext = path.extname(filePath);
    if (/\.(test|spec)\./i.test(filePath)) return 'test';
    if (ext === '.md' || ext === '.rst') return 'documentation';
    if (/config/i.test(filePath)) return 'config';
    return 'root';
  }
  return parts[0];
}

function classifyDirectory(groupName) {
  const lower = groupName.toLowerCase();
  if (PATTERN_MAP[lower]) return PATTERN_MAP[lower];
  for (const [key, label] of Object.entries(PATTERN_MAP)) {
    if (lower.includes(key)) return label;
  }
  return null;
}

function classifyFile(filePath) {
  for (const { test, label } of FILE_PATTERNS) {
    if (test(filePath)) return label;
  }
  return null;
}

function main() {
  const inputPath = process.argv[2];
  const outputPath = process.argv[3];
  if (!inputPath || !outputPath) {
    console.error('Usage: node ua-arch-analyze.js <input.json> <output.json>');
    process.exit(1);
  }

  let input;
  try {
    input = JSON.parse(fs.readFileSync(inputPath, 'utf8'));
  } catch (err) {
    console.error(`Failed to read input: ${err.message}`);
    process.exit(1);
  }

  const { fileNodes, importEdges = [], allEdges = [] } = input;
  const paths = fileNodes.map(getFilePath);
  const commonPrefix = computeCommonPrefix(paths);

  const directoryGroups = {};
  for (const node of fileNodes) {
    const fp = getFilePath(node);
    const group = getDirectoryGroup(fp, commonPrefix);
    if (!directoryGroups[group]) directoryGroups[group] = [];
    directoryGroups[group].push(node.id);
  }

  const nodeTypeGroups = {};
  for (const node of fileNodes) {
    if (!nodeTypeGroups[node.type]) nodeTypeGroups[node.type] = [];
    nodeTypeGroups[node.type].push(node.id);
  }

  const fileFanOut = {};
  const fileFanIn = {};
  for (const node of fileNodes) {
    fileFanOut[node.id] = 0;
    fileFanIn[node.id] = 0;
  }
  for (const edge of importEdges) {
    if (fileFanOut[edge.source] !== undefined) fileFanOut[edge.source]++;
    if (fileFanIn[edge.target] !== undefined) fileFanIn[edge.target]++;
  }

  const nodeToGroup = {};
  for (const [group, ids] of Object.entries(directoryGroups)) {
    for (const id of ids) nodeToGroup[id] = group;
  }

  const interGroupMap = {};
  for (const edge of importEdges) {
    const fromG = nodeToGroup[edge.source];
    const toG = nodeToGroup[edge.target];
    if (!fromG || !toG) continue;
    const key = `${fromG}->${toG}`;
    interGroupMap[key] = (interGroupMap[key] || 0) + 1;
  }
  const interGroupImports = Object.entries(interGroupMap).map(([key, count]) => {
    const [from, to] = key.split('->');
    return { from, to, count };
  });

  const intraGroupDensity = {};
  for (const group of Object.keys(directoryGroups)) {
    let internalEdges = 0;
    let totalEdges = 0;
    for (const edge of importEdges) {
      const fromG = nodeToGroup[edge.source];
      const toG = nodeToGroup[edge.target];
      if (fromG === group || toG === group) {
        totalEdges++;
        if (fromG === group && toG === group) internalEdges++;
      }
    }
    intraGroupDensity[group] = {
      internalEdges,
      totalEdges,
      density: totalEdges > 0 ? Math.round((internalEdges / totalEdges) * 1000) / 1000 : 0,
    };
  }

  const crossCategoryMap = {};
  for (const edge of allEdges) {
    const sourceNode = fileNodes.find((n) => n.id === edge.source);
    const targetNode = fileNodes.find((n) => n.id === edge.target);
    if (!sourceNode || !targetNode) continue;
    const key = `${sourceNode.type}|${targetNode.type}|${edge.type}`;
    crossCategoryMap[key] = (crossCategoryMap[key] || 0) + 1;
  }
  const crossCategoryEdges = Object.entries(crossCategoryMap).map(([key, count]) => {
    const [fromType, toType, edgeType] = key.split('|');
    return { fromType, toType, edgeType, count };
  });

  const patternMatches = {};
  for (const [group, ids] of Object.entries(directoryGroups)) {
    const dirPattern = classifyDirectory(group);
    if (dirPattern) {
      patternMatches[group] = dirPattern;
      continue;
    }
    const filePatterns = ids
      .map((id) => {
        const node = fileNodes.find((n) => n.id === id);
        return node ? classifyFile(getFilePath(node)) : null;
      })
      .filter(Boolean);
    if (filePatterns.length > 0) {
      const counts = {};
      for (const p of filePatterns) counts[p] = (counts[p] || 0) + 1;
      patternMatches[group] = Object.entries(counts).sort((a, b) => b[1] - a[1])[0][0];
    } else {
      patternMatches[group] = 'unknown';
    }
  }

  const infraPatterns = [
    /Dockerfile/i, /docker-compose/i, /\.tf$/i, /k8s/i, /kubernetes/i, /helm/i,
  ];
  const ciPatterns = [/\.github\/workflows\//i, /gitlab-ci\.yml$/i, /Jenkinsfile$/i];
  const infraFiles = fileNodes
    .filter((n) => {
      const fp = getFilePath(n);
      return n.type === 'service' || n.type === 'pipeline' ||
        infraPatterns.some((p) => p.test(fp)) || ciPatterns.some((p) => p.test(fp));
    })
    .map(getFilePath);

  const deploymentTopology = {
    hasDockerfile: fileNodes.some((n) => /Dockerfile/i.test(getFilePath(n))),
    hasCompose: fileNodes.some((n) => /docker-compose/i.test(getFilePath(n))),
    hasK8s: fileNodes.some((n) => /k8s|kubernetes/i.test(getFilePath(n))),
    hasTerraform: fileNodes.some((n) => /\.tf$/i.test(getFilePath(n))),
    hasCI: fileNodes.some((n) => ciPatterns.some((p) => p.test(getFilePath(n))) || n.type === 'pipeline'),
    infraFiles,
  };

  const schemaFiles = fileNodes.filter((n) => n.type === 'schema' || /\.(graphql|gql|proto|sql)$/i.test(getFilePath(n))).map(getFilePath);
  const migrationFiles = fileNodes.filter((n) => /migrations\//i.test(getFilePath(n))).map(getFilePath);
  const dataModelFiles = fileNodes.filter((n) => /\/Models\//i.test(getFilePath(n)) || n.type === 'table').map(getFilePath);
  const apiHandlerFiles = fileNodes.filter((n) =>
    /\/(Controllers|Http|Livewire|routes)\//i.test(getFilePath(n)) ||
    /routes\/.*\.php$/i.test(getFilePath(n)),
  ).map(getFilePath);

  const dataPipeline = { schemaFiles, migrationFiles, dataModelFiles, apiHandlerFiles };

  const docGroups = new Set();
  for (const node of fileNodes) {
    if (node.type === 'document' || /\.(md|rst)$/i.test(getFilePath(node))) {
      const group = nodeToGroup[node.id];
      if (group) docGroups.add(group);
    }
  }
  const totalGroups = Object.keys(directoryGroups).length;
  const docCoverage = {
    groupsWithDocs: docGroups.size,
    totalGroups,
    coverageRatio: totalGroups > 0 ? Math.round((docGroups.size / totalGroups) * 1000) / 1000 : 0,
    undocumentedGroups: Object.keys(directoryGroups).filter((g) => !docGroups.has(g)),
  };

  const pairCounts = {};
  for (const { from, to, count } of interGroupImports) {
    const key = [from, to].sort().join('|');
    if (!pairCounts[key]) pairCounts[key] = {};
    pairCounts[key][`${from}->${to}`] = count;
  }
  const dependencyDirection = [];
  for (const [pair, directions] of Object.entries(pairCounts)) {
    const entries = Object.entries(directions);
    if (entries.length === 1) {
      const [dir, count] = entries[0];
      const [dependent, dependsOn] = dir.split('->');
      dependencyDirection.push({ dependent, dependsOn, count });
    } else if (entries.length === 2) {
      const [[dir1, c1], [dir2, c2]] = entries;
      const [d1, dep1] = dir1.split('->');
      if (c1 > c2) dependencyDirection.push({ dependent: d1, dependsOn: dep1, count: c1 });
      else if (c2 > c1) {
        const [d2, dep2] = dir2.split('->');
        dependencyDirection.push({ dependent: d2, dependsOn: dep2, count: c2 });
      }
    }
  }
  dependencyDirection.sort((a, b) => b.count - a.count);

  const filesPerGroup = {};
  for (const [g, ids] of Object.entries(directoryGroups)) filesPerGroup[g] = ids.length;
  const nodeTypeCounts = {};
  for (const [t, ids] of Object.entries(nodeTypeGroups)) nodeTypeCounts[t] = ids.length;

  const result = {
    scriptCompleted: true,
    commonPrefix,
    directoryGroups,
    nodeTypeGroups,
    crossCategoryEdges,
    interGroupImports,
    intraGroupDensity,
    patternMatches,
    deploymentTopology,
    dataPipeline,
    docCoverage,
    dependencyDirection,
    fileStats: {
      totalFileNodes: fileNodes.length,
      filesPerGroup,
      nodeTypeCounts,
    },
    fileFanIn,
    fileFanOut,
  };

  fs.writeFileSync(outputPath, JSON.stringify(result, null, 2));
}

main();
