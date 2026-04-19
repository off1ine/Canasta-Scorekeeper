// ---------- Sessions (unified create/edit + archive) ----------

const elSessionsStatus = document.getElementById("sessionsStatus");
const elSessionsList = document.getElementById("sessionsList");
const elSessionEditStatus = document.getElementById("sessionEditStatus");

const elEditorTitle = document.getElementById("sessionEditorTitle");
const elSaveSessionBtn = document.getElementById("saveSessionBtn");
const elNewSessionModeBtn = document.getElementById("newSessionModeBtn");
const elPlayersBlock = document.getElementById("createPlayersBlock");
const elPropBlock = document.getElementById("editPropagateBlock");
const elSearch = document.getElementById("sessionSearch");
const elShowArchived = document.getElementById("showArchivedSessions");

let allSessionsCache = [];

function setCreateMode() {
    document.getElementById("editSessionId").value = "";
    elEditorTitle.textContent = t("Create new session");
    elSaveSessionBtn.textContent = t("Create session");
    elPlayersBlock.hidden = false;
    elPropBlock.hidden = true;
    document.getElementById("editPropagate").value = "none";
    document.getElementById("playerNames").value = "";
    document.getElementById("editSessName").value = "";
    document.getElementById("editMaxScore").value = "5000";
    elSessionEditStatus.textContent = "";
}

function setEditMode(session) {
    document.getElementById("editSessionId").value = session.id;
    elEditorTitle.textContent = t("Edit session: {name}", { name: session.name });
    elSaveSessionBtn.textContent = t("Save session changes");
    elPlayersBlock.hidden = true;
    elPropBlock.hidden = false;
    document.getElementById("editSessName").value = session.name;
    document.getElementById("editMaxScore").value = session.max_score_per_round ?? 5000;
    document.getElementById("editPropagate").value = "none";
    elSessionEditStatus.textContent = t("Editing session…");
}

async function loadSessionsAdmin() {
    if (!elSessionsStatus) return;

    elSessionsStatus.textContent = t("Loading…");
    const include = elShowArchived?.checked ? 1 : 0;
    const { sessions } = await api(`api/sessions.php?include_archived=${include}`);
    allSessionsCache = sessions || [];
    elSessionsStatus.textContent = t("Sessions: {count}", { count: allSessionsCache.length });

    renderSessionsList();
}

function renderSessionsList() {
    const q = (elSearch?.value || "").trim().toLowerCase();
    const rows = allSessionsCache.filter(s => !q || String(s.name).toLowerCase().includes(q));

    if (!rows.length) {
        elSessionsList.innerHTML = `<div class="muted" style="padding: 8px 0;">${esc(t("No sessions match."))}</div>`;
        return;
    }

    elSessionsList.innerHTML = rows.map(s => {
        const archived = !!s.archived_at;
        const maxBits = s.max_score_per_round ? esc(t("Max {n}", { n: fmtNum(s.max_score_per_round) })) : '';
        const createdBits = s.created_at ? esc(t("Created {date}", { date: s.created_at })) : '';
        const meta = [maxBits, createdBits].filter(Boolean).join(' · ');
        const archiveIcon = archived ? 'unarchive' : 'archive';
        const archiveAttr = archived ? 'data-restore' : 'data-archive';
        const archiveLabel = archived ? t('Restore {name}', { name: s.name }) : t('Archive {name}', { name: s.name });
        return `
            <div class="admin-row">
                <div class="admin-row-main">
                    <div class="admin-row-title">
                        ${esc(s.name)}
                        ${archived ? `<span class="chip">${esc(t('Archived'))}</span>` : ''}
                    </div>
                    ${meta ? `<div class="muted-sm">${meta}</div>` : ''}
                </div>
                <div class="admin-row-actions">
                    <button class="btn btn-icon ghost" type="button" data-edit-session="${s.id}" aria-label="${esc(t('Edit {name}', { name: s.name }))}">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn btn-icon ghost" type="button" ${archiveAttr}="${s.id}" aria-label="${esc(archiveLabel)}">
                        <span class="material-symbols-outlined">${archiveIcon}</span>
                    </button>
                </div>
            </div>
        `;
    }).join("");

    rows.forEach(s => {
        elSessionsList.querySelector(`[data-edit-session="${s.id}"]`)?.addEventListener("click", () => setEditMode(s));

        if (s.archived_at) {
            elSessionsList.querySelector(`[data-restore="${s.id}"]`)?.addEventListener("click", async () => {
                try {
                    await api("api/session_archive.php", {
                        method: "POST",
                        body: JSON.stringify({ session_id: s.id, archived: 0 })
                    });
                    const cur = document.getElementById("editSessionId").value.trim();
                    await loadSessionsAdmin();
                    if (cur && Number(cur) === Number(s.id)) {
                        const fresh = allSessionsCache.find(x => Number(x.id) === Number(s.id));
                        if (fresh) setEditMode(fresh);
                    }
                } catch (e) {
                    elSessionsStatus.textContent = e.message;
                }
            });
        } else {
            elSessionsList.querySelector(`[data-archive="${s.id}"]`)?.addEventListener("click", async () => {
                if (!confirm(t('Archive session "{name}"?', { name: s.name }))) return;
                try {
                    await api("api/session_archive.php", {
                        method: "POST",
                        body: JSON.stringify({ session_id: s.id, archived: 1 })
                    });
                    const cur = document.getElementById("editSessionId").value.trim();
                    await loadSessionsAdmin();
                    if (cur && Number(cur) === Number(s.id)) setCreateMode();
                } catch (e) {
                    elSessionsStatus.textContent = e.message;
                }
            });
        }
    });
}

elSaveSessionBtn?.addEventListener("click", async () => {
    elSessionEditStatus.textContent = "";

    const idVal = document.getElementById("editSessionId").value.trim();
    const isEdit = idVal !== "";
    const name = document.getElementById("editSessName").value.trim();
    const max = Number(document.getElementById("editMaxScore").value || 0);

    if (!name) { elSessionEditStatus.textContent = t("Session name required."); return; }
    if (!Number.isFinite(max) || max <= 0) { elSessionEditStatus.textContent = t("Max score must be > 0."); return; }

    try {
        if (!isEdit) {
            const players = document.getElementById("playerNames").value
                .split("\n").map(s => s.trim()).filter(Boolean);

            if (players.length < 2) { elSessionEditStatus.textContent = t("Enter at least 2 players."); return; }

            const r = await api("api/sessions.php", {
                method: "POST",
                body: JSON.stringify({ name, max_score_per_round: max, players })
            });

            elSessionEditStatus.textContent = t("Created session #{id}. Go to {page}.", { id: r.session_id, page: t("Overview") });
            await loadSessionsAdmin();
            setCreateMode();
        } else {
            const session_id = Number(idVal);
            const propagate = document.getElementById("editPropagate").value;

            if (propagate === "all") {
                if (!confirm(t("This will rewrite target scores for ALL rounds in this session and may change ended states. Continue?"))) {
                    return;
                }
            }

            await api("api/session_update.php", {
                method: "POST",
                body: JSON.stringify({ session_id, name, max_score_per_round: max, propagate })
            });

            await loadSessionsAdmin();
            const fresh = allSessionsCache.find(s => Number(s.id) === session_id);
            if (fresh) setEditMode(fresh);
            elSessionEditStatus.textContent = t("Saved.");
        }
    } catch (e) {
        elSessionEditStatus.textContent = e.message;
    }
});

document.getElementById("clearSessionEditBtn")?.addEventListener("click", () => setCreateMode());
elNewSessionModeBtn?.addEventListener("click", () => setCreateMode());
elSearch?.addEventListener("input", () => renderSessionsList());
elShowArchived?.addEventListener("change", () => loadSessionsAdmin().catch(e => { elSessionsStatus.textContent = e.message; }));

setCreateMode();
loadSessionsAdmin().catch(e => { if (elSessionsStatus) elSessionsStatus.textContent = e.message; });


// ---------- Meld thresholds ----------

const elThresholds = document.getElementById("thresholds");
const elTStatus = document.getElementById("tStatus");

async function loadThresholds() {
    const { thresholds } = await api("api/meld_thresholds.php");
    if (!thresholds.length) {
        elThresholds.innerHTML = `<div class="muted" style="padding: 8px 0;">${esc(t("No thresholds yet."))}</div>`;
        return;
    }

    elThresholds.innerHTML = thresholds.map(th => {
        const range = `${fmtNum(th.score_from)} – ${th.score_to === null ? '∞' : fmtNum(th.score_to)}`;
        const chipCls = meldChipClass(th.meld_minimum, thresholds);
        return `
            <div class="admin-row">
                <div class="admin-row-main">
                    <div class="admin-row-title">${range}</div>
                </div>
                <div class="admin-row-actions">
                    <span class="${chipCls}">${esc(t("Min {n}", { n: th.meld_minimum }))}</span>
                    <button class="btn btn-icon ghost" type="button" data-edit="${th.id}" aria-label="${esc(t('Edit threshold'))}">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn btn-icon ghost danger-text" type="button" data-del="${th.id}" aria-label="${esc(t('Delete threshold'))}">
                        <span class="material-symbols-outlined">delete</span>
                    </button>
                </div>
            </div>
        `;
    }).join("");

    thresholds.forEach(th => {
        elThresholds.querySelector(`[data-edit="${th.id}"]`).addEventListener("click", () => {
            document.getElementById("tId").value = th.id;
            document.getElementById("tFrom").value = th.score_from;
            document.getElementById("tTo").value = th.score_to ?? "";
            document.getElementById("tMeld").value = th.meld_minimum;
            elTStatus.textContent = t("Editing threshold…");
        });
        elThresholds.querySelector(`[data-del="${th.id}"]`).addEventListener("click", async () => {
            if (!confirm(t("Delete threshold {from}–{to}?", { from: th.score_from, to: th.score_to ?? '∞' }))) return;
            try {
                await api("api/meld_thresholds.php", {
                    method: "DELETE",
                    body: JSON.stringify({ id: th.id })
                });
                await loadThresholds();
                elTStatus.textContent = t("Deleted.");
            } catch (e) {
                elTStatus.textContent = e.message;
            }
        });
    });
}

document.getElementById("saveThresholdBtn").addEventListener("click", async () => {
    elTStatus.textContent = "";
    const idVal = document.getElementById("tId").value.trim();
    const id = idVal ? Number(idVal) : 0;
    const from = Number(document.getElementById("tFrom").value || 0);
    const toRaw = document.getElementById("tTo").value.trim();
    const score_to = toRaw === "" ? null : Number(toRaw);
    const meld_minimum = Number(document.getElementById("tMeld").value || 0);

    try {
        await api("api/meld_thresholds.php", {
            method: "POST",
            body: JSON.stringify({ id, score_from: from, score_to, meld_minimum })
        });
        await loadThresholds();
        elTStatus.textContent = t("Saved.");
    } catch (e) {
        elTStatus.textContent = e.message;
    }
});

document.getElementById("clearThresholdBtn").addEventListener("click", () => {
    document.getElementById("tId").value = "";
    document.getElementById("tFrom").value = 0;
    document.getElementById("tTo").value = "";
    document.getElementById("tMeld").value = 50;
    elTStatus.textContent = "";
});

loadThresholds().catch(e => elThresholds.textContent = e.message);


// ---------- Users (admin) ----------

const elUsersStatus = document.getElementById("usersStatus");
const elUsersList = document.getElementById("usersList");
const elUserEditStatus = document.getElementById("userEditStatus");

function isPin(s) { return /^\d{4}$/.test(s); }

async function loadUsers() {
    if (!elUsersStatus) return;
    elUsersStatus.textContent = t("Loading…");
    const { users } = await api("api/users.php");
    elUsersStatus.textContent = t("Users: {count}", { count: users.length });

    elUsersList.innerHTML = users.map(u => {
        const adminChip = u.is_admin ? `<span class="chip chip-indigo">${esc(t('Admin'))}</span>` : '';
        const statusChip = u.is_active
            ? `<span class="chip chip-green">${esc(t('Active'))}</span>`
            : `<span class="chip">${esc(t('Inactive'))}</span>`;
        const lastLogin = u.last_login_at
            ? esc(t('Last login {date}', { date: u.last_login_at }))
            : esc(t('Never logged in'));
        return `
            <div class="admin-row">
                <div class="admin-row-main">
                    <div class="admin-row-title">
                        ${esc(u.username)}
                        ${adminChip}
                        ${statusChip}
                    </div>
                    <div class="muted-sm">${lastLogin}</div>
                </div>
                <div class="admin-row-actions">
                    <button class="btn btn-icon ghost" type="button" data-edit-user="${u.id}" aria-label="${esc(t('Edit {name}', { name: u.username }))}">
                        <span class="material-symbols-outlined">edit</span>
                    </button>
                    <button class="btn btn-icon ghost danger-text" type="button" data-del-user="${u.id}" aria-label="${esc(t('Deactivate {name}', { name: u.username }))}">
                        <span class="material-symbols-outlined">person_off</span>
                    </button>
                </div>
            </div>
        `;
    }).join("");

    users.forEach(u => {
        elUsersList.querySelector(`[data-edit-user="${u.id}"]`).addEventListener("click", () => {
            document.getElementById("userId").value = u.id;
            document.getElementById("userName").value = u.username;
            document.getElementById("userPin").value = "";
            document.getElementById("userIsAdmin").checked = !!u.is_admin;
            document.getElementById("userIsActive").checked = !!u.is_active;
            elUserEditStatus.textContent = t("Editing user…");
        });

        elUsersList.querySelector(`[data-del-user="${u.id}"]`).addEventListener("click", async () => {
            if (!confirm(t('Deactivate user "{name}"?', { name: u.username }))) return;
            try {
                await api("api/users.php", { method: "DELETE", body: JSON.stringify({ id: u.id }) });
                await loadUsers();
                elUserEditStatus.textContent = t("User deactivated.");
            } catch (e) {
                elUserEditStatus.textContent = e.message;
            }
        });
    });
}

document.getElementById("saveUserBtn")?.addEventListener("click", async () => {
    elUserEditStatus.textContent = "";
    const idVal = document.getElementById("userId").value.trim();
    const id = idVal ? Number(idVal) : 0;

    const username = document.getElementById("userName").value.trim();
    const pin = document.getElementById("userPin").value.trim();
    const is_admin = document.getElementById("userIsAdmin").checked ? 1 : 0;
    const is_active = document.getElementById("userIsActive").checked ? 1 : 0;

    if (!username) { elUserEditStatus.textContent = t("Username required."); return; }
    if (pin !== "" && !isPin(pin)) { elUserEditStatus.textContent = t("PIN must be exactly 4 digits."); return; }

    try {
        await api("api/users.php", {
            method: "POST",
            body: JSON.stringify({ id, username, pin, is_admin, is_active })
        });
        await loadUsers();
        elUserEditStatus.textContent = t("Saved.");
        document.getElementById("clearUserBtn").click();
    } catch (e) {
        elUserEditStatus.textContent = e.message;
    }
});

document.getElementById("clearUserBtn")?.addEventListener("click", () => {
    document.getElementById("userId").value = "";
    document.getElementById("userName").value = "";
    document.getElementById("userPin").value = "";
    document.getElementById("userIsAdmin").checked = false;
    document.getElementById("userIsActive").checked = true;
    elUserEditStatus.textContent = "";
});

loadUsers().catch(e => { if (elUsersStatus) elUsersStatus.textContent = e.message; });
