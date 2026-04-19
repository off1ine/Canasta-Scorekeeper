function setSelectOptions(selectEl, items, selected = null) {
    selectEl.innerHTML = items.map(o => `<option value="${o.value}">${esc(o.label)}</option>`).join("");
    if (selected === null || selected === undefined) {
        selectEl.selectedIndex = 0;
    } else {
        selectEl.value = String(selected);
        if (selectEl.selectedIndex < 0) selectEl.selectedIndex = 0;
    }
}

function setRoundOptions(rounds, selectedRoundId) {
    const items = rounds.map(r => ({
        value: r.id,
        label: t('Round {n} ({status})', {
            n: r.round_number,
            status: t(r.ended_at ? 'ended' : 'active')
        })
    }));
    setSelectOptions(elRound, items, selectedRoundId);
}

function renderPillGroup(el, players, selected, { allowNone = false } = {}) {
    const options = [];
    if (allowNone) options.push({ value: "", label: t("(none)") });
    for (const p of players) options.push({ value: String(p.id), label: p.name });

    el.innerHTML = options.map(o =>
        `<button type="button" class="pill-option" data-value="${o.value}">${esc(o.label)}</button>`
    ).join("");

    let selVal = "";
    if (selected !== null && selected !== undefined && selected !== "") {
        selVal = String(selected);
        if (!options.some(o => o.value === selVal)) selVal = options[0].value;
    } else {
        selVal = options[0].value;
    }
    setPillSelection(el, selVal);
}

function setPillSelection(el, value) {
    el.dataset.selected = String(value);
    el.querySelectorAll(".pill-option").forEach(btn => {
        btn.classList.toggle("is-active", btn.dataset.value === String(value));
    });
}

function pillSelectedNumber(el) {
    const v = el.dataset.selected ?? "";
    return v === "" ? null : Number(v);
}

function wirePillGroup(el) {
    el.addEventListener("click", (e) => {
        if (el.classList.contains("is-disabled")) return;
        const btn = e.target.closest(".pill-option");
        if (!btn || !el.contains(btn)) return;
        setPillSelection(el, btn.dataset.value);
    });
}

function suggestNextDealer(d) {
    const players = d.players;
    if (!players.length) return null;

    let lastDealer = null;
    if (d.games.length) {
        const lastGame = d.games[d.games.length - 1];
        lastDealer = lastGame.dealer_player_id ? Number(lastGame.dealer_player_id) : null;
    } else if (d.last_dealer_player_id) {
        lastDealer = Number(d.last_dealer_player_id);
    }

    if (lastDealer === null) return Number(players[0].id);
    const idx = players.findIndex(p => Number(p.id) === lastDealer);
    if (idx < 0) return Number(players[0].id);
    return Number(players[(idx + 1) % players.length].id);
}

function computeLeaders(d) {
    const totals = d.players.map(p => ({ pid: Number(p.id), total: d.totals[Number(p.id)] ?? 0 }));
    if (!totals.length || totals.every(t => t.total === 0)) return new Set();
    const isRomme = d.session?.game_type === 'romme';
    const best = isRomme
        ? Math.min(...totals.map(t => t.total))
        : Math.max(...totals.map(t => t.total));
    return new Set(totals.filter(t => t.total === best).map(t => t.pid));
}

let elSession, elRound, elWinner, elMeta, elRoundTitle, elScoreboard, elAddGameInputs;
let elGames, elStatus, elStartRoundBtn, elDealer;
let elSummaryTitle, elSummaryMeta;
let elEditCard, elEditGameTitle, elEditWinner, elEditScoresInputs, elEditStatus, elEditDealer;

let current = { sessionId: null, roundId: null, data: null };
let editState = { gameId: null };

function renderScoreboard(d, ended, roundWinner) {
    elScoreboard.innerHTML = "";

    const leaders = computeLeaders(d);
    const target = Number(d.round.target_score) || 0;
    const allLeaders = leaders.size === d.players.length;

    d.players.forEach(p => {
        const pid = Number(p.id);
        const total = d.totals[pid] ?? 0;
        const meldMin = d.meld_minimums[pid];
        const isLeader = leaders.has(pid) && !allLeaders;
        const pct = target > 0 ? Math.max(0, Math.min(100, (total / target) * 100)) : 0;

        const chips = [];
        if (isLeader) chips.push(`<span class="chip chip-indigo">${esc(t('Leader'))}</span>`);
        if (roundWinner === pid) chips.push(`<span class="chip chip-amber">${esc(t('Round winner'))}</span>`);

        const isRomme = d.session?.game_type === 'romme';
        const meldChipCls = isRomme ? 'chip chip-green' : meldChipClass(meldMin, d.meld_thresholds);
        const meldChip = (meldMin !== null && meldMin !== undefined)
            ? `<span class="${meldChipCls}">${esc(t('Min meld {n}', { n: meldMin }))}</span>`
            : '';

        const row = document.createElement("div");
        row.className = "sb-row" + (isLeader ? " is-leader" : "");
        row.innerHTML = `
            <div class="sb-row-top">
                <div class="sb-name">
                    ${esc(p.name)}
                    ${chips.join('')}
                </div>
                <div class="sb-total tabular-nums">${fmtNum(total)}</div>
            </div>
            <div class="progress">
                <div class="progress-bar${isLeader ? ' is-leader' : ''}" style="width: ${pct}%"></div>
            </div>
            <div class="sb-row-bottom">
                <div class="sb-progress-caption tabular-nums">
                    <span class="sb-total-current">${fmtNum(total)}</span>
                    <span class="sb-total-sep"> / </span>
                    <span class="sb-total-target">${fmtNum(target)}</span>
                </div>
                ${meldChip}
            </div>
        `;
        elScoreboard.appendChild(row);
    });
}

function renderAddGameInputs(d, ended) {
    elAddGameInputs.innerHTML = "";
    d.players.forEach(p => {
        const pid = Number(p.id);
        const field = document.createElement("div");
        field.className = "ag-field";
        field.innerHTML = `
            <label for="scoreInput-${pid}">${esc(p.name)}</label>
            <input id="scoreInput-${pid}" class="scoreInput" type="text" inputmode="text" placeholder="0" data-player="${pid}" ${ended ? "disabled" : ""} />
        `;
        elAddGameInputs.appendChild(field);
    });
    document.getElementById("saveGameBtn").disabled = ended;
    elWinner.classList.toggle("is-disabled", ended);
    elDealer.classList.toggle("is-disabled", ended);
}

function renderEditScoresInputs(d, game) {
    elEditScoresInputs.innerHTML = "";
    const rows = d.scores.filter(s => Number(s.game_id) === Number(game.id));
    const scoreMap = new Map(rows.map(r => [Number(r.player_id), Number(r.score)]));
    d.players.forEach(p => {
        const pid = Number(p.id);
        const sc = scoreMap.has(pid) ? scoreMap.get(pid) : 0;
        const field = document.createElement("div");
        field.className = "ag-field";
        field.innerHTML = `
            <label for="editScoreInput-${pid}">${esc(p.name)}</label>
            <input id="editScoreInput-${pid}" class="editScoreInput" type="text" inputmode="text" value="${sc}" data-player="${pid}" />
        `;
        elEditScoresInputs.appendChild(field);
    });
}

function renderGamesList(d, r) {
    const scoresByGame = new Map();
    d.scores.forEach(s => {
        const gid = Number(s.game_id);
        if (!scoresByGame.has(gid)) scoresByGame.set(gid, []);
        scoresByGame.get(gid).push(s);
    });

    elGames.innerHTML = "";
    if (!d.games.length) {
        elGames.innerHTML = `<div class="muted" style="padding: 8px 0;">${esc(t('No games yet in this round.'))}</div>`;
        return;
    }

    d.games.forEach(g => {
        const rows = scoresByGame.get(Number(g.id)) || [];
        const winnerPid = g.winner_player_id ? Number(g.winner_player_id) : null;
        const winnerScore = winnerPid !== null
            ? (rows.find(x => Number(x.player_id) === winnerPid)?.score ?? null)
            : null;

        const title = g.winner_name
            ? `<span class="game-num">#${g.game_number}</span><span class="game-winner">${esc(t('{name} won', { name: g.winner_name }))}</span>`
            : `<span class="game-num">#${g.game_number}</span><span class="chip">${esc(t('No winner'))}</span>`;

        const metaParts = [esc(g.played_at)];
        if (g.dealer_name) metaParts.push(esc(t('Dealer {name}', { name: g.dealer_name })));

        const scoreBlock = winnerScore !== null
            ? `<div class="game-row-score tabular-nums">${fmtNum(winnerScore)}</div>`
            : '';

        const div = document.createElement("div");
        div.className = "game-row";
        div.setAttribute("role", "button");
        div.setAttribute("tabindex", "0");
        div.setAttribute("aria-label", t('Edit game {n}', { n: g.game_number }));
        div.innerHTML = `
            <div class="game-row-main">
                <div class="game-row-title">${title}</div>
                <div class="muted-sm">${metaParts.join(' · ')}</div>
            </div>
            ${scoreBlock}
            <span class="material-symbols-outlined game-row-chevron" aria-hidden="true">chevron_right</span>
        `;

        const open = () => openEditGame(Number(g.id));
        div.addEventListener("click", open);
        div.addEventListener("keydown", (e) => {
            if (e.key === "Enter" || e.key === " ") { e.preventDefault(); open(); }
        });
        elGames.appendChild(div);
    });
}

function render() {
    const d = current.data;
    if (!d) return;

    const r = d.round;
    const ended = r.ended_at !== null;
    const roundWinner = r.winner_player_id ? Number(r.winner_player_id) : null;

    const gameCount = d.games.length;
    const target = fmtNum(r.target_score);
    elMeta.textContent = t(
        gameCount === 1
            ? 'Target {target} · {count} game this round'
            : 'Target {target} · {count} games this round',
        { target, count: gameCount }
    );

    const endedLabel = ended
        ? (r.winner_name
            ? t('Ended · Winner: {name}', { name: r.winner_name })
            : t('Ended (no winner)'))
        : t('Active');
    elRoundTitle.textContent = t('Round {n} · {status}', { n: r.round_number, status: endedLabel });

    elSummaryTitle.textContent = t('{session} · Round {n}', { session: d.session.name, n: r.round_number });
    elSummaryMeta.textContent = t(
        gameCount === 1
            ? '{status} · {count} game · Target {target}'
            : '{status} · {count} games · Target {target}',
        { status: endedLabel, count: gameCount, target }
    );

    renderPillGroup(elWinner, d.players, null, { allowNone: true });
    const suggestedDealer = suggestNextDealer(d);
    renderPillGroup(elDealer, d.players, suggestedDealer);

    renderScoreboard(d, ended, roundWinner);
    renderAddGameInputs(d, ended);
    renderGamesList(d, r);
}

async function loadSessions() {
    const { sessions } = await api("api/sessions.php");
    const items = sessions.map(s => ({ value: s.id, label: s.name }));
    setSelectOptions(elSession, items);

    if (!sessions.length) {
        elMeta.textContent = t("No sessions yet. Create one in Setup.");
        return;
    }

    current.sessionId = Number(sessions[0].id);
    elSession.value = String(current.sessionId);
    await loadSessionAndDefaultRound(current.sessionId);
}

async function loadSessionAndDefaultRound(sessionId) {
    const d = await api(`api/session.php?id=${sessionId}`);
    current.data = d;
    current.sessionId = Number(sessionId);
    current.roundId = Number(d.round.id);

    setRoundOptions(d.rounds, current.roundId);
    render();
}

async function loadSessionRound(sessionId, roundId) {
    const d = await api(`api/session.php?id=${sessionId}&round_id=${roundId}`);
    current.data = d;
    current.sessionId = Number(sessionId);
    current.roundId = Number(d.round.id);

    setRoundOptions(d.rounds, current.roundId);
    render();
}

function openEditGame(gameId) {
    const d = current.data;
    const game = d.games.find(x => Number(x.id) === Number(gameId));
    if (!game) return;

    editState.gameId = gameId;
    elEditStatus.textContent = "";
    elEditGameTitle.textContent = `#${game.game_number}`;

    const winnerPid = game.winner_player_id ? Number(game.winner_player_id) : null;
    const dealerPid = game.dealer_player_id ? Number(game.dealer_player_id) : null;
    renderPillGroup(elEditWinner, d.players, winnerPid, { allowNone: true });
    renderPillGroup(elEditDealer, d.players, dealerPid ?? d.players[0]?.id);

    renderEditScoresInputs(d, game);

    elEditCard.hidden = false;
    elEditCard.scrollIntoView({ behavior: "smooth", block: "start" });
}

function initElements() {
    elSession = document.getElementById("sessionSelect");
    elRound = document.getElementById("roundSelect");
    elWinner = document.getElementById("winnerSelect");
    elDealer = document.getElementById("dealerSelect");
    elMeta = document.getElementById("sessionMeta");
    elRoundTitle = document.getElementById("roundTitle");
    elScoreboard = document.getElementById("scoreboard");
    elAddGameInputs = document.getElementById("addGameInputs");
    elGames = document.getElementById("gamesList");
    elStatus = document.getElementById("saveStatus");
    elStartRoundBtn = document.getElementById("startRoundBtn");

    elSummaryTitle = document.getElementById("summaryTitle");
    elSummaryMeta = document.getElementById("summaryMeta");

    elEditCard = document.getElementById("editCard");
    elEditGameTitle = document.getElementById("editGameTitle");
    elEditWinner = document.getElementById("editWinnerSelect");
    elEditDealer = document.getElementById("editDealerSelect");
    elEditScoresInputs = document.getElementById("editScoresInputs");
    elEditStatus = document.getElementById("editStatus");

    const required = [
        ["sessionSelect", elSession], ["roundSelect", elRound], ["winnerSelect", elWinner],
        ["dealerSelect", elDealer], ["sessionMeta", elMeta], ["roundTitle", elRoundTitle],
        ["scoreboard", elScoreboard], ["addGameInputs", elAddGameInputs],
        ["gamesList", elGames], ["saveStatus", elStatus], ["startRoundBtn", elStartRoundBtn],
        ["editCard", elEditCard], ["editWinnerSelect", elEditWinner],
        ["editDealerSelect", elEditDealer], ["editScoresInputs", elEditScoresInputs]
    ];
    const missing = required.filter(([, el]) => !el).map(([name]) => name);
    if (missing.length) {
        const msg = t('Overview page is missing elements: {list}', { list: missing.join(", ") });
        if (elMeta) elMeta.textContent = msg;
        throw new Error(msg);
    }

    [elWinner, elDealer, elEditWinner, elEditDealer].forEach(wirePillGroup);
}

function setupEventListeners() {
    document.getElementById("saveGameBtn").addEventListener("click", async () => {
        elStatus.textContent = "";
        const inputs = Array.from(document.querySelectorAll(".scoreInput"));
        const scores = inputs.map(i => ({
            player_id: Number(i.dataset.player),
            score: Number(i.value || 0)
        }));

        const winner_player_id = pillSelectedNumber(elWinner);
        const dealer_player_id = pillSelectedNumber(elDealer);

        try {
            await api("api/game_add.php", {
                method: "POST",
                body: JSON.stringify({ session_id: current.sessionId, scores, winner_player_id, dealer_player_id })
            });
            await loadSessionRound(current.sessionId, current.roundId);
            elStatus.textContent = t("Game added.");
        } catch (e) {
            elStatus.textContent = e.message;
        }
    });

    elSession.addEventListener("change", async () => {
        const sessionId = Number(elSession.value);
        current.sessionId = sessionId;
        await loadSessionAndDefaultRound(sessionId);
    });

    elRound.addEventListener("change", async () => {
        const roundId = Number(elRound.value);
        current.roundId = roundId;
        await loadSessionRound(current.sessionId, roundId);
    });

    elStartRoundBtn.addEventListener("click", async () => {
        elStatus.textContent = "";
        if (!confirm(t("End the current active round (if any) and start a new one?"))) return;

        try {
            await api("api/round_start.php", {
                method: "POST",
                body: JSON.stringify({ session_id: current.sessionId })
            });
            await loadSessionAndDefaultRound(current.sessionId);
            elStatus.textContent = t("New round started.");
        } catch (e) {
            elStatus.textContent = e.message;
        }
    });

    document.getElementById("cancelEditBtn").addEventListener("click", () => {
        editState.gameId = null;
        elEditCard.hidden = true;
        elEditStatus.textContent = "";
    });

    document.getElementById("saveEditBtn").addEventListener("click", async () => {
        if (!editState.gameId) return;

        elEditStatus.textContent = "";
        const game_id = editState.gameId;

        const inputs = Array.from(document.querySelectorAll(".editScoreInput"));
        const scores = inputs.map(i => ({
            player_id: Number(i.dataset.player),
            score: Number(i.value || 0)
        }));

        const winner_player_id = pillSelectedNumber(elEditWinner);
        const dealer_player_id = pillSelectedNumber(elEditDealer);

        try {
            await api("api/game_update.php", {
                method: "POST",
                body: JSON.stringify({ game_id, scores, winner_player_id, dealer_player_id })
            });
            await loadSessionRound(current.sessionId, current.roundId);
            elEditStatus.textContent = t("Saved.");
            elEditCard.hidden = true;
            editState.gameId = null;
        } catch (e) {
            elEditStatus.textContent = e.message;
        }
    });
}

function init() {
    initElements();
    setupEventListeners();

    loadSessions().catch(err => {
        elMeta.textContent = err.message;
        console.error(err);
    });
}

document.addEventListener("DOMContentLoaded", () => {
    init();
});
