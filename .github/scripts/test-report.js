#!/usr/bin/env node
/**
 * Renders a Markdown summary of the unit and integration runs.
 *
 * Reads whatever PHPUnit left in coverage/ (JUnit XML for results, Clover XML
 * for coverage) and prints a report. Every input is optional so a suite that
 * crashed before writing its log degrades to "not reported" instead of taking
 * the reporting step down with it.
 *
 * Coverage from the two suites is merged by line: a line is covered when any
 * suite executed it. Summing the two Clover files instead would double count
 * every file both suites touch.
 *
 * Usage: node .github/scripts/test-report.js [--output <file>]
 */

'use strict';

const fs = require('fs');
const path = require('path');

const ROOT = path.join(__dirname, '..', '..');
const COVERAGE_DIR = process.env.FN_COVERAGE_DIR
    ? path.resolve(process.env.FN_COVERAGE_DIR)
    : path.join(ROOT, 'coverage');

// Lets the workflow find and overwrite its own comment instead of posting a
// new one on every push.
const MARKER = '<!-- fn-structured-data:test-report -->';

const SUITES = [
    { key: 'unit', label: 'Unit' },
    { key: 'integration', label: 'Integration' },
];

function readIfExists(file) {
    try {
        return fs.readFileSync(file, 'utf8');
    } catch (error) {
        if (error.code === 'ENOENT') {
            return null;
        }
        throw error;
    }
}

function decodeEntities(value) {
    return value
        .replace(/&lt;/g, '<')
        .replace(/&gt;/g, '>')
        .replace(/&quot;/g, '"')
        .replace(/&apos;/g, "'")
        .replace(/&#(\d+);/g, (_, code) => String.fromCharCode(Number(code)))
        .replace(/&amp;/g, '&');
}

/**
 * Pulls attributes out of a single XML start tag.
 *
 * Attributes are read one at a time rather than by position so the parser does
 * not care what order PHPUnit happens to emit them in.
 */
function attributes(tag) {
    const found = {};
    const pattern = /([\w:-]+)\s*=\s*"([^"]*)"/g;
    let match;

    while ((match = pattern.exec(tag)) !== null) {
        found[match[1]] = decodeEntities(match[2]);
    }

    return found;
}

function toNumber(value) {
    const number = Number(value);

    return Number.isFinite(number) ? number : 0;
}

function parseJUnit(xml) {
    // The configured suite is the first <testsuite>; nested ones repeat its
    // totals per class and would double count.
    const suiteTag = xml.match(/<testsuite\s[^>]*>/);

    if (!suiteTag) {
        return null;
    }

    const attrs = attributes(suiteTag[0]);
    const problems = [];
    const casePattern = /<testcase\s([^>]*?)(?:\/>|>([\s\S]*?)<\/testcase>)/g;
    let match;

    while ((match = casePattern.exec(xml)) !== null) {
        const body = match[2];

        if (!body) {
            continue;
        }

        const problem = body.match(/<(failure|error)\s([^>]*)>([\s\S]*?)<\/\1>/);

        if (!problem) {
            continue;
        }

        const caseAttrs = attributes(match[1]);
        const name = `${caseAttrs.class || caseAttrs.classname || ''}::${caseAttrs.name || 'unknown'}`;

        // PHPUnit opens the body with the fully qualified test name, then the
        // message, then a diff and file/line. Only the message earns space in a
        // PR comment; the rest is already in the job log.
        const message = decodeEntities(problem[3])
            .split('\n')
            .map((line) => line.trim())
            .filter((line) => line !== '' && line !== name);

        problems.push({
            kind: problem[1],
            name,
            message: message[0] || 'No message reported.',
        });
    }

    return {
        tests: toNumber(attrs.tests),
        assertions: toNumber(attrs.assertions),
        failures: toNumber(attrs.failures),
        errors: toNumber(attrs.errors),
        skipped: toNumber(attrs.skipped),
        warnings: toNumber(attrs.warnings),
        time: toNumber(attrs.time),
        problems,
    };
}

/**
 * Reads a Clover report into a file -> line -> hit-count map.
 *
 * Only type="stmt" lines are counted, which reproduces PHPUnit's own
 * statements/coveredstatements metric exactly. Clover also emits a
 * type="method" line per method declaration; those are tracked separately from
 * statements, and including them would both inflate the total and disagree with
 * the percentage PHPUnit prints, because a partially covered method still
 * records a hit on its declaration line.
 */
function parseClover(xml) {
    const files = new Map();
    const filePattern = /<file\s([^>]*)>([\s\S]*?)<\/file>/g;
    let match;

    while ((match = filePattern.exec(xml)) !== null) {
        const name = attributes(match[1]).name;

        if (!name) {
            continue;
        }

        const lines = files.get(name) || new Map();
        const linePattern = /<line\s([^>]*?)\/?>/g;
        let lineMatch;

        while ((lineMatch = linePattern.exec(match[2])) !== null) {
            const attrs = attributes(lineMatch[1]);
            const num = toNumber(attrs.num);

            if (!num || attrs.type !== 'stmt') {
                continue;
            }

            lines.set(num, Math.max(lines.get(num) || 0, toNumber(attrs.count)));
        }

        files.set(name, lines);
    }

    return files;
}

function mergeCoverage(reports) {
    const merged = new Map();

    for (const report of reports) {
        for (const [file, lines] of report) {
            const target = merged.get(file) || new Map();

            for (const [num, count] of lines) {
                target.set(num, Math.max(target.get(num) || 0, count));
            }

            merged.set(file, target);
        }
    }

    return merged;
}

/**
 * Turns an absolute Clover path into a repository-relative one.
 *
 * Clover records the path as seen by the PHP process that produced it, which is
 * not always this checkout (a container mount, for example). Falling back to
 * the src/ segment keeps the table readable instead of printing a wall of
 * ../../.. for those.
 */
function displayPath(file) {
    const relative = path.relative(ROOT, file);

    if (relative && !relative.startsWith('..')) {
        return relative;
    }

    const marker = file.lastIndexOf(`${path.sep}src${path.sep}`);

    return marker === -1 ? file : file.slice(marker + 1);
}

function summarizeCoverage(merged) {
    const files = [];
    let total = 0;
    let covered = 0;

    for (const [file, lines] of merged) {
        let fileTotal = 0;
        let fileCovered = 0;

        for (const count of lines.values()) {
            fileTotal += 1;

            if (count > 0) {
                fileCovered += 1;
            }
        }

        total += fileTotal;
        covered += fileCovered;

        files.push({
            name: displayPath(file),
            total: fileTotal,
            covered: fileCovered,
            percent: fileTotal === 0 ? 100 : (fileCovered / fileTotal) * 100,
        });
    }

    files.sort((a, b) => a.percent - b.percent || a.name.localeCompare(b.name));

    return {
        files,
        total,
        covered,
        percent: total === 0 ? 0 : (covered / total) * 100,
    };
}

/**
 * Neutralizes angle brackets so an assertion message containing something like
 * <script> or <div> is shown as text rather than swallowed as HTML by the
 * Markdown renderer.
 */
function escapeInline(value) {
    const collapsed = value.replace(/\s+/g, ' ').trim();
    const truncated = collapsed.length > 300 ? `${collapsed.slice(0, 297)}...` : collapsed;

    return truncated.replace(/&/g, '&amp;').replace(/</g, '&lt;').replace(/>/g, '&gt;');
}

function formatPercent(value) {
    return `${value.toFixed(2)}%`;
}

function formatSeconds(value) {
    return `${value.toFixed(2)}s`;
}

function renderMarkdown(results, coverage) {
    const reported = SUITES.filter((suite) => results[suite.key]);
    const lines = [MARKER, '## Test results', ''];

    if (reported.length === 0) {
        lines.push(
            'No test results were reported. Both suites failed before writing a JUnit log; check the job logs.',
            ''
        );
    } else {
        const totals = { tests: 0, assertions: 0, failures: 0, errors: 0, skipped: 0, time: 0 };

        lines.push('| Suite | Tests | Assertions | Failures | Errors | Skipped | Time |');
        lines.push('|---|---:|---:|---:|---:|---:|---:|');

        for (const suite of SUITES) {
            const result = results[suite.key];

            if (!result) {
                lines.push(`| ${suite.label} | not reported | | | | | |`);
                continue;
            }

            for (const key of Object.keys(totals)) {
                totals[key] += result[key];
            }

            lines.push(
                `| ${suite.label} | ${result.tests} | ${result.assertions} | ${result.failures} |` +
                    ` ${result.errors} | ${result.skipped} | ${formatSeconds(result.time)} |`
            );
        }

        lines.push(
            `| **Total** | **${totals.tests}** | **${totals.assertions}** | **${totals.failures}** |` +
                ` **${totals.errors}** | **${totals.skipped}** | **${formatSeconds(totals.time)}** |`
        );
        lines.push('');

        const failing = totals.failures + totals.errors;

        lines.push(
            failing === 0
                ? `All ${totals.tests} tests passed.`
                : `${failing} of ${totals.tests} tests did not pass.`
        );
        lines.push('');
    }

    const problems = SUITES.flatMap((suite) =>
        (results[suite.key]?.problems || []).map((problem) => ({ ...problem, suite: suite.label }))
    );

    if (problems.length > 0) {
        lines.push('### Failures', '');

        for (const problem of problems.slice(0, 20)) {
            lines.push(
                `- **${problem.suite}** \`${problem.name}\` (${problem.kind}): ${escapeInline(problem.message)}`
            );
        }

        if (problems.length > 20) {
            lines.push(`- ...and ${problems.length - 20} more; see the job log.`);
        }

        lines.push('');
    }

    lines.push('## Coverage', '');

    if (!coverage || coverage.total === 0) {
        lines.push(
            'Coverage was not reported. This usually means no coverage driver was available on the runner.',
            ''
        );
    } else {
        lines.push(
            `**${formatPercent(coverage.percent)}** of lines covered ` +
                `(${coverage.covered} of ${coverage.total}), merged across the suites that ran.`,
            '',
            '<details><summary>Coverage by file</summary>',
            '',
            '| File | Covered | Lines | % |',
            '|---|---:|---:|---:|'
        );

        for (const file of coverage.files) {
            lines.push(`| \`${file.name}\` | ${file.covered} | ${file.total} | ${formatPercent(file.percent)} |`);
        }

        lines.push('', '</details>', '');
    }

    const runId = process.env.GITHUB_RUN_ID;
    const repository = process.env.GITHUB_REPOSITORY;
    const server = process.env.GITHUB_SERVER_URL || 'https://github.com';
    const sha = process.env.GITHUB_SHA;
    const footer = [];

    if (sha) {
        footer.push(`commit \`${sha.slice(0, 7)}\``);
    }

    if (runId && repository) {
        footer.push(`[workflow run](${server}/${repository}/actions/runs/${runId})`);
    }

    if (footer.length > 0) {
        lines.push(`<sub>${footer.join(' &middot; ')}</sub>`);
    }

    return `${lines.join('\n').trimEnd()}\n`;
}

function main() {
    const results = {};
    const clovers = [];

    for (const suite of SUITES) {
        const junit = readIfExists(path.join(COVERAGE_DIR, `junit-${suite.key}.xml`));

        if (junit) {
            results[suite.key] = parseJUnit(junit);
        }

        const clover = readIfExists(path.join(COVERAGE_DIR, `clover-${suite.key}.xml`));

        if (clover) {
            clovers.push(parseClover(clover));
        }
    }

    const coverage = clovers.length > 0 ? summarizeCoverage(mergeCoverage(clovers)) : null;
    const markdown = renderMarkdown(results, coverage);

    const outputIndex = process.argv.indexOf('--output');

    if (outputIndex !== -1) {
        const target = process.argv[outputIndex + 1];

        if (!target) {
            console.error('--output requires a file path.');
            process.exit(1);
        }

        fs.mkdirSync(path.dirname(path.resolve(target)), { recursive: true });
        fs.writeFileSync(target, markdown);
        console.log(`Wrote test report to ${target}.`);

        return;
    }

    process.stdout.write(markdown);
}

main();
