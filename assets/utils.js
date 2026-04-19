async function api(url, opts = {}) {
    const res = await fetch(url, {
        headers: { "Content-Type": "application/json" },
        ...opts
    });

    if (res.status === 401) {
        const next = encodeURIComponent(window.location.pathname + window.location.search);
        window.location.href = `login.php?next=${next}`;
        throw new Error("Not authenticated");
    }

    const data = await res.json();
    if (!res.ok) throw new Error(data.error || "Request failed");
    return data;
}

function esc(s) {
    return String(s).replace(/[&<>"']/g, c => ({
        "&": "&amp;", "<": "&lt;", ">": "&gt;", '"': "&quot;", "'": "&#039;"
    }[c]));
}

function fmtNum(n) {
    return Number(n).toLocaleString('en-US');
}

function meldChipClass(meldMin, thresholds) {
    if (meldMin === null || meldMin === undefined) return "chip";
    if (!thresholds || !thresholds.length) return "chip";

    const min = Number(meldMin);
    const matches = thresholds.filter(t => Number(t.meld_minimum) === min);
    if (matches.length && matches.every(t => Number(t.score_from) < 0)) return "chip";

    const positiveMins = [...new Set(
        thresholds
            .filter(t => Number(t.score_from) >= 0)
            .map(t => Number(t.meld_minimum))
    )].sort((a, b) => a - b);

    if (!positiveMins.length) return "chip";
    if (positiveMins.length === 1) return "chip chip-green";
    const idx = positiveMins.indexOf(min);
    if (idx < 0) return "chip";
    const last = positiveMins.length - 1;
    if (idx === 0) return "chip chip-green";
    if (idx === last) return "chip chip-red";
    return idx * 2 > last ? "chip chip-orange" : "chip chip-amber";
}
