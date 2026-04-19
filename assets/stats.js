// Chart.js defaults
if (typeof Chart !== 'undefined') {
    Chart.defaults.font.family = "'Inter', system-ui, -apple-system, sans-serif";
    Chart.defaults.font.size = 12;
    Chart.defaults.color = '#6b7280';
    Chart.defaults.borderColor = '#e6e7eb';
}

const CHART_COLORS = [
    '#4f46e5', // indigo (primary)
    '#dc2626', // red
    '#16a34a', // green
    '#d97706', // amber
    '#0ea5e9', // sky
    '#8b5cf6', // violet
    '#ec4899', // pink
    '#f97316', // orange
    '#06b6d4', // cyan
    '#84cc16'  // lime
];

const elMeta = document.getElementById("statsMeta");
const elList = document.getElementById("statsList");
const elRefresh = document.getElementById("refreshBtn");
const elSession = document.getElementById("statsSessionSelect");

const elHSMeta = document.getElementById("highScoresMeta");
const elHSList = document.getElementById("highScoresList");

const elLSMeta = document.getElementById("lowScoresMeta");
const elLSList = document.getElementById("lowScoresList");

const elDIMeta = document.getElementById("dealerImpactMeta");
const elDIList = document.getElementById("dealerImpactList");

const elWinsChartMeta = document.getElementById("winsChartMeta");
const elWinsCanvas = document.getElementById("winsChart");
let winsChart = null;

const elGprMeta = document.getElementById("gprChartMeta");
const elGprCanvas = document.getElementById("gprChart");
let gprChart = null;

const elSpgMeta = document.getElementById("spgChartMeta");
const elSpgCanvas = document.getElementById("spgChart");
let spgChart = null;

const elCumMeta = document.getElementById("cumChartMeta");
const elCumCanvas = document.getElementById("cumChart");
let cumChart = null;

const elSpgTitle = document.getElementById("spgTitle");
const elSpgModeGame = document.getElementById("spgModeGame");
const elSpgModeRound = document.getElementById("spgModeRound");
let spgMode = 'game';
let cachedSpg = null;
let cachedSpr = null;

const WINS_SINCE = '2026-01-01';
const RULE_CHANGE_DATE = '2026-03-21';

let sessionsById = new Map();

// --- formatting helpers ---
function fmtInt(n) { return fmtNum(Math.round(Number(n) || 0)); }
function fmtAvg(n) { return fmtInt(Math.round(Number(n) || 0)); }
function fmtPercent(n) { return `${fmtInt(Math.round(Number(n) || 0))}%`; }

// --- data loading ---
async function loadSessions() {
    const { sessions } = await api("api/sessions.php");
    const active = (sessions || []).filter(s => !s.archived_at);
    sessionsById = new Map(active.map(s => [Number(s.id), s]));

    if (!active.length) {
        elSession.innerHTML = `<option value="">${esc(t('(no sessions)'))}</option>`;
        elSession.disabled = true;
        elMeta.textContent = t("No sessions yet. Create one in Setup.");
        return;
    }

    elSession.disabled = false;
    elSession.innerHTML = active.map(s => `<option value="${s.id}">${esc(s.name)}</option>`).join("");
    elSession.value = String(active[0].id);
}

function getSelectedSessionId() {
    const v = elSession.value.trim();
    return v === "" ? null : Number(v);
}

async function loadStats() {
    elMeta.textContent = t("Loading…");
    elList.innerHTML = "";
    elHSMeta.textContent = t("Loading…");
    elHSList.innerHTML = "";
    elLSMeta.textContent = t("Loading…");
    elLSList.innerHTML = "";
    elDIMeta.textContent = t("Loading…");
    elDIList.innerHTML = "";

    const sessionId = getSelectedSessionId();
    if (!sessionId) {
        elMeta.textContent = t("Select a session.");
        return;
    }

    const { players, high_scores, low_scores, dealer_impact } =
        await api(`api/player_stats.php?session_id=${encodeURIComponent(sessionId)}`);

    const sessionName = sessionsById.get(sessionId)?.name ?? `#${sessionId}`;
    elMeta.textContent = t(
        players.length === 1 ? '{session} · {count} player' : '{session} · {count} players',
        { session: sessionName, count: fmtInt(players.length) }
    );

    renderPlayerStandings(players);
    renderHighScores(high_scores || []);
    renderLowScores(low_scores || []);
    renderDealerImpact(dealer_impact || []);
}

function renderPlayerStandings(players) {
    if (!players.length) {
        elList.innerHTML = `<div class="muted" style="padding: 8px 0;">${esc(t('No data yet.'))}</div>`;
        return;
    }
    elList.innerHTML = players.map(p => `
        <div class="stat-row">
            <div class="stat-row-top">
                <div class="stat-row-name">${esc(p.player_name)}</div>
                <div class="stat-row-total tabular-nums">${fmtInt(p.total_points)}</div>
            </div>
            <div class="stat-row-metrics">
                <span class="stat-metric"><span class="stat-metric-label">${esc(t('Games'))}</span><span class="stat-metric-val tabular-nums">${fmtInt(p.games_played)}</span></span>
                <span class="stat-metric"><span class="stat-metric-label">${esc(t('Wins'))}</span><span class="stat-metric-val tabular-nums">${fmtInt(p.won_games)}</span></span>
                <span class="stat-metric"><span class="stat-metric-label">${esc(t('Win rate'))}</span><span class="stat-metric-val tabular-nums">${fmtPercent(p.win_rate_games)}</span></span>
                <span class="stat-metric"><span class="stat-metric-label">${esc(t('Rounds won'))}</span><span class="stat-metric-val tabular-nums">${fmtInt(p.won_rounds)}</span></span>
                <span class="stat-metric"><span class="stat-metric-label">${esc(t('Avg/game'))}</span><span class="stat-metric-val tabular-nums">${fmtAvg(p.avg_points_per_game)}</span></span>
            </div>
        </div>
    `).join("");
}

function rankPillClass(i) {
    if (i === 0) return "rank-pill rank-gold";
    if (i === 1) return "rank-pill rank-silver";
    if (i === 2) return "rank-pill rank-bronze";
    return "rank-pill";
}

function renderScoreRanks(rows, container, metaEl, emptyKey, metaFormatter, useMedals) {
    if (!rows.length) {
        metaEl.textContent = t(emptyKey);
        container.innerHTML = "";
        return;
    }
    metaEl.textContent = metaFormatter(rows.length);
    container.innerHTML = rows.map((row, i) => `
        <div class="rank-row">
            <div class="${useMedals ? rankPillClass(i) : 'rank-pill'}">${i + 1}</div>
            <div class="rank-row-main">
                <div class="rank-row-score tabular-nums">${fmtInt(row.score)}</div>
                <div class="rank-row-meta">
                    <span class="rank-row-player">${esc(row.player_name)}</span>
                    · ${esc(t('R{r} G{g}', { r: fmtInt(row.round_number), g: fmtInt(row.game_number) }))} · ${esc(row.played_at)}
                </div>
            </div>
        </div>
    `).join("");
}

function renderHighScores(rows) {
    renderScoreRanks(rows, elHSList, elHSMeta, 'No high scores yet.',
        n => t(
            n === 1 ? 'Top {n} single-game score' : 'Top {n} single-game scores',
            { n: fmtInt(n) }
        ), true);
}

function renderLowScores(rows) {
    renderScoreRanks(rows, elLSList, elLSMeta, 'No low scores yet.',
        n => t(
            n === 1 ? 'Bottom {n} single-game score' : 'Bottom {n} single-game scores',
            { n: fmtInt(n) }
        ), false);
}

function renderDealerImpact(di) {
    if (!di.length) {
        elDIMeta.textContent = t("No dealer data yet.");
        elDIList.innerHTML = "";
        return;
    }
    elDIMeta.textContent = t(
        di.length === 1 ? 'Dealer stats for {count} player' : 'Dealer stats for {count} players',
        { count: fmtInt(di.length) }
    );
    elDIList.innerHTML = di.map(d => {
        const deltaSign = d.delta > 0 ? "+" : d.delta < 0 ? "−" : "";
        const deltaClass = d.delta > 0 ? "delta-pos" : d.delta < 0 ? "delta-neg" : "delta-neutral";
        const topWinners = (d.top_winners || []).slice(0, 3).map(w => `${esc(w.winner_name)} ${fmtInt(w.wins)}`).join(" · ");

        return `
            <div class="di-row">
                <div class="di-row-top">
                    <div class="di-row-name">${esc(d.player_name)}</div>
                    <div class="di-row-delta ${deltaClass}">${deltaSign}${fmtPercent(Math.abs(d.delta))}</div>
                </div>
                <div class="di-row-meta">
                    <span>${esc(t('{n} dealt', { n: fmtInt(d.games_dealt) }))}</span>
                    <span>${esc(t('{n} wins as dealer', { n: fmtInt(d.wins_as_dealer) }))}</span>
                    <span>${esc(t('{pct} as dealer', { pct: fmtPercent(d.win_rate_as_dealer) }))}</span>
                    <span>${esc(t('{pct} overall', { pct: fmtPercent(d.overall_win_rate) }))}</span>
                </div>
                ${topWinners ? `<div class="di-row-winners">${esc(t('Wins when dealing: {list}', { list: topWinners }))}</div>` : ''}
            </div>
        `;
    }).join("");
}

// --- charts ---
async function loadWinsChart() {
    elWinsChartMeta.textContent = t("Loading…");
    const sessionId = getSelectedSessionId();
    if (!sessionId) {
        elWinsChartMeta.textContent = "";
        if (winsChart) { winsChart.destroy(); winsChart = null; }
        return;
    }

    const url = `api/wins_over_time.php?since=${WINS_SINCE}&session_id=${encodeURIComponent(sessionId)}`;
    const { dates, series } = await api(url);

    if (!dates.length) {
        elWinsChartMeta.textContent = t('No wins recorded since {since}.', { since: WINS_SINCE });
        if (winsChart) { winsChart.destroy(); winsChart = null; }
        return;
    }

    elWinsChartMeta.textContent = t('Cumulative wins since {since} (rule change: {rule})', { since: WINS_SINCE, rule: RULE_CHANGE_DATE });

    const datasets = series.map((s, i) => ({
        label: s.player_name,
        data: s.data,
        borderColor: CHART_COLORS[i % CHART_COLORS.length],
        backgroundColor: CHART_COLORS[i % CHART_COLORS.length],
        borderWidth: 2.5,
        pointRadius: dates.length > 30 ? 0 : 4,
        pointHitRadius: 10,
        tension: 0.2
    }));

    const ruleChangePlugin = {
        id: 'ruleChangeLine',
        afterDraw(chart) {
            const idx = chart.data.labels.indexOf(RULE_CHANGE_DATE);
            if (idx < 0) return;
            const xScale = chart.scales.x;
            const x = xScale.getPixelForValue(idx);
            const { top, bottom } = chart.chartArea;
            const ctx = chart.ctx;

            ctx.save();
            ctx.beginPath();
            ctx.setLineDash([6, 4]);
            ctx.lineWidth = 1.5;
            ctx.strokeStyle = '#9ca3af';
            ctx.moveTo(x, top);
            ctx.lineTo(x, bottom);
            ctx.stroke();

            ctx.setLineDash([]);
            ctx.font = '11px Inter, system-ui, sans-serif';
            ctx.fillStyle = '#6b7280';
            ctx.textAlign = 'center';
            ctx.fillText(t('Deck change'), x, top - 4);
            ctx.restore();
        }
    };

    if (winsChart) {
        winsChart.data.labels = dates;
        winsChart.data.datasets = datasets;
        winsChart.update();
    } else {
        winsChart = new Chart(elWinsCanvas, {
            type: 'line',
            data: { labels: dates, datasets },
            plugins: [ruleChangePlugin],
            options: {
                responsive: true,
                maintainAspectRatio: true,
                layout: { padding: { top: 18 } },
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: {
                        position: 'bottom',
                        labels: { boxWidth: 10, padding: 14 }
                    }
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 8 } },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 },
                        title: { display: true, text: t('Total wins') }
                    }
                }
            }
        });
    }
}

function renderSpgChart() {
    const src = spgMode === 'round' ? cachedSpr : cachedSpg;

    elSpgTitle.textContent = spgMode === 'round' ? t('Score per round') : t('Score per game');
    elSpgModeGame.classList.toggle('active', spgMode === 'game');
    elSpgModeRound.classList.toggle('active', spgMode === 'round');

    if (!src || !src.labels.length) {
        elSpgMeta.textContent = spgMode === 'round' ? t('No round data yet.') : t('No game data yet.');
        if (spgChart) { spgChart.destroy(); spgChart = null; }
        return;
    }

    elSpgMeta.textContent = t(
        spgMode === 'round' ? '{n} rounds' : '{n} games',
        { n: fmtInt(src.labels.length) }
    );

    const datasets = [];
    src.series.forEach((s, i) => {
        const color = CHART_COLORS[i % CHART_COLORS.length];
        datasets.push({
            label: s.player_name,
            data: s.data,
            borderColor: color,
            backgroundColor: color,
            borderWidth: 2,
            pointRadius: src.labels.length > 40 ? 0 : 3,
            pointHitRadius: 10,
            tension: 0.2,
            spanGaps: true
        });

        if (spgMode === 'round') {
            const pts = [];
            s.data.forEach((v, x) => { if (v !== null) pts.push({ x, y: v }); });
            if (pts.length >= 2) {
                const n = pts.length;
                const sumX = pts.reduce((a, p) => a + p.x, 0);
                const sumY = pts.reduce((a, p) => a + p.y, 0);
                const sumXY = pts.reduce((a, p) => a + p.x * p.y, 0);
                const sumX2 = pts.reduce((a, p) => a + p.x * p.x, 0);
                const slope = (n * sumXY - sumX * sumY) / (n * sumX2 - sumX * sumX);
                const intercept = (sumY - slope * sumX) / n;
                const trendData = s.data.map((_, x) => Math.round(intercept + slope * x));

                datasets.push({
                    label: t('{name} trend', { name: s.player_name }),
                    data: trendData,
                    borderColor: color,
                    borderWidth: 1.25,
                    borderDash: [6, 4],
                    pointRadius: 0,
                    pointHitRadius: 0,
                    tension: 0
                });
            }
        }
    });

    if (spgChart) {
        spgChart.data.labels = src.labels;
        spgChart.data.datasets = datasets;
        spgChart.update();
    } else {
        spgChart = new Chart(elSpgCanvas, {
            type: 'line',
            data: { labels: src.labels, datasets },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14 } }
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 12 } },
                    y: { title: { display: true, text: t('Score') } }
                }
            }
        });
    }
}

elSpgModeGame.addEventListener('click', () => {
    if (spgMode === 'game') return;
    spgMode = 'game';
    renderSpgChart();
});
elSpgModeRound.addEventListener('click', () => {
    if (spgMode === 'round') return;
    spgMode = 'round';
    renderSpgChart();
});

function renderGprChart(gpr) {
    if (!gpr.labels.length) {
        elGprMeta.textContent = t("No round data yet.");
        if (gprChart) { gprChart.destroy(); gprChart = null; }
        return;
    }

    elGprMeta.textContent = t('{n} rounds', { n: fmtInt(gpr.labels.length) });

    const gprAvg = gpr.data.reduce((a, b) => a + b, 0) / gpr.data.length;

    const gprData = {
        labels: gpr.labels,
        datasets: [{
            label: t('Games'),
            data: gpr.data,
            borderColor: CHART_COLORS[0],
            backgroundColor: CHART_COLORS[0],
            borderWidth: 2.5,
            pointRadius: gpr.labels.length > 30 ? 0 : 4,
            pointHitRadius: 10,
            tension: 0.2
        }, {
            label: t('Avg ({value})', { value: Math.round(gprAvg * 10) / 10 }),
            data: gpr.data.map(() => gprAvg),
            borderColor: '#9ca3af',
            borderWidth: 1.25,
            borderDash: [6, 4],
            pointRadius: 0,
            pointHitRadius: 0
        }]
    };

    if (gprChart) {
        gprChart.data = gprData;
        gprChart.update();
    } else {
        gprChart = new Chart(elGprCanvas, {
            type: 'line',
            data: gprData,
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: { legend: { display: false } },
                scales: {
                    x: { ticks: { maxTicksLimit: 15 } },
                    y: {
                        beginAtZero: true,
                        ticks: { stepSize: 1, precision: 0 },
                        title: { display: true, text: t('Games') }
                    }
                }
            }
        });
    }
}

function renderCumChart(spg) {
    if (!spg.labels.length) {
        elCumMeta.textContent = t("No game data yet.");
        if (cumChart) { cumChart.destroy(); cumChart = null; }
        return;
    }

    elCumMeta.textContent = t('{n} games', { n: fmtInt(spg.labels.length) });

    const cumDatasets = spg.series.map((s, i) => {
        let running = 0;
        const cumData = s.data.map(v => {
            if (v !== null) running += v;
            return running;
        });
        return {
            label: s.player_name,
            data: cumData,
            borderColor: CHART_COLORS[i % CHART_COLORS.length],
            backgroundColor: CHART_COLORS[i % CHART_COLORS.length],
            borderWidth: 2.5,
            pointRadius: spg.labels.length > 40 ? 0 : 3,
            pointHitRadius: 10,
            tension: 0.2
        };
    });

    if (cumChart) {
        cumChart.data.labels = spg.labels;
        cumChart.data.datasets = cumDatasets;
        cumChart.update();
    } else {
        cumChart = new Chart(elCumCanvas, {
            type: 'line',
            data: { labels: spg.labels, datasets: cumDatasets },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { position: 'bottom', labels: { boxWidth: 10, padding: 14 } }
                },
                scales: {
                    x: { ticks: { maxTicksLimit: 12 } },
                    y: {
                        beginAtZero: true,
                        title: { display: true, text: t('Cumulative points') }
                    }
                }
            }
        });
    }
}

async function loadCharts() {
    elGprMeta.textContent = t("Loading…");
    elSpgMeta.textContent = t("Loading…");
    elCumMeta.textContent = t("Loading…");

    const sessionId = getSelectedSessionId();
    if (!sessionId) {
        elGprMeta.textContent = "";
        elSpgMeta.textContent = "";
        elCumMeta.textContent = "";
        if (gprChart) { gprChart.destroy(); gprChart = null; }
        if (spgChart) { spgChart.destroy(); spgChart = null; }
        if (cumChart) { cumChart.destroy(); cumChart = null; }
        return;
    }

    const { games_per_round, score_per_game, score_per_round } =
        await api(`api/chart_data.php?session_id=${encodeURIComponent(sessionId)}`);

    renderGprChart(games_per_round);

    cachedSpg = score_per_game;
    cachedSpr = score_per_round;
    renderSpgChart();

    renderCumChart(score_per_game);
}

elRefresh.addEventListener("click", () => {
    loadStats().catch(e => elMeta.textContent = e.message);
    loadWinsChart().catch(e => elWinsChartMeta.textContent = e.message);
    loadCharts().catch(e => elGprMeta.textContent = e.message);
});

elSession.addEventListener("change", () => {
    loadStats().catch(e => elMeta.textContent = e.message);
    loadWinsChart().catch(e => elWinsChartMeta.textContent = e.message);
    loadCharts().catch(e => elGprMeta.textContent = e.message);
});

(async function init() {
    try {
        await loadSessions();
        await Promise.all([
            loadStats(),
            loadWinsChart(),
            loadCharts()
        ]);
    } catch (e) {
        elMeta.textContent = e.message;
    }
})();
